<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use App\Models\ProductDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Product;

class StockOutController extends Controller
{
    // List all stock outs
    public function index(Request $request)
    {
        $query = StockOut::with(['user', 'inventoryUser', 'destinationBranch', 'items.product']);

        if ($request->category) {
            $query->byCategory($request->category);
        }

        if ($request->search) {
            $query->search($request->search);
        }

        // Filter by Type (HP vs Non-HP)
        if ($request->type === 'hp') {
            $query->whereHas('items');
        } elseif ($request->type === 'non-hp') {
            $query->whereNotNull('non_hp_items');
        }

        // DATE FILTER
        if ($request->category !== 'recap_harian') {
            if ($request->month && $request->year) {
                $query->whereMonth('created_at', $request->month)
                    ->whereYear('created_at', $request->year);
            }
        }

        // DATE FILTER FOR INVENTORY ROLE
        $user = Auth::user();
        if ($user && $user->hasRole('inventory')) {
            $startDate = \Carbon\Carbon::now()->subMonth()->startOfMonth();
            $query->where('created_at', '>=', $startDate);
        }

        $results = $query->latest()->paginate($request->per_page ?? 20);

        // For Non-HP items, we need to load product details manually since they are in JSON
        if ($request->type === 'non-hp' || !$request->type) {
            // Collect all product IDs from non_hp_items
            $productIds = [];
            foreach ($results->items() as $item) {
                if ($item->non_hp_items) {
                    foreach ($item->non_hp_items as $nonHpItem) {
                        $productIds[] = $nonHpItem['product_id'];
                    }
                }
            }

            if (!empty($productIds)) {
                $products = Product::whereIn('id', array_unique($productIds))->get()->keyBy('id');

                // Attach product data to the non_hp_items
                foreach ($results->items() as $item) {
                    if ($item->non_hp_items) {
                        $enrichedItems = [];
                        foreach ($item->non_hp_items as $nonHpItem) {
                            $prod = $products[$nonHpItem['product_id']] ?? null;
                            $nonHpItem['product_name'] = $prod ? $prod->name : 'Unknown Product';
                            $nonHpItem['product_sku'] = $prod ? $prod->sku : '-';
                            $enrichedItems[] = $nonHpItem;
                        }
                        $item->non_hp_items = $enrichedItems;
                    }
                }
            }
        }

        return response()->json($results);
    }

    // Create stock out
    public function store(Request $request)
    {
        // Base validation
        $rules = [
            'category' => [
                'required',
                Rule::in([
                    'pindah_cabang',
                    'kesalahan_input',
                    'retur',
                    'shopee',
                    'giveaway',
                    'hadiah',
                    'brand_ambassador',
                    'event',
                    'promo',
                    'inventaris'
                ])
            ],
            'product_detail_ids' => 'required_without:non_hp_items|array',
            'product_detail_ids.*' => 'exists:product_details,id',
            'non_hp_items' => 'required_without:product_detail_ids|array',
            'non_hp_items.*.product_id' => 'required|exists:products,id',
            'non_hp_items.*.quantity' => 'required|integer|min:1',

            // Pindah Cabang
            'destination_branch_id' => 'required_if:category,pindah_cabang|nullable|exists:branches,id',
            'receiver_name' => 'required_if:category,pindah_cabang|nullable|string|max:255',
            'transfer_notes' => 'nullable|string',

            // Kesalahan Input
            'deletion_reason' => 'required_if:category,kesalahan_input|nullable|string',

            // Retur
            'retur_officer' => 'required_if:category,retur|nullable|string|max:255',
            'inventory_user_id' => 'sometimes|nullable|exists:users,id',
            'retur_seal' => 'nullable|string|max:255',
            'retur_issue' => 'required_if:category,retur|nullable|string',
            'customer_name' => 'required_if:category,retur|nullable|string|max:255',
            'customer_phone' => 'required_if:category,retur|nullable|string|max:50',
            'return_destination_id' => 'required_if:category,retur|nullable|exists:warehouses,id',
            'proof_image' => 'nullable|image|max:10240', // Max 10MB
        ];

        // Shopee: Per-item validation
        if ($request->category === 'shopee') {
            // Add Selling Price validation
            $rules['selling_price'] = 'required|numeric|min:0';

            // Allow simplified frontend without shopee_items array for now if not provided
            if ($request->has('shopee_items')) {
                $rules['shopee_items'] = 'required|array|min:1';
                $rules['shopee_items.*.product_detail_id'] = 'required|exists:product_details,id';
                $rules['shopee_items.*.tracking_no'] = 'required|string|max:100';
            }

            // Validate Global Fields
            $rules['shopee_receiver'] = 'required|string|max:255';
            $rules['shopee_phone'] = 'required|string|max:50';
            $rules['shopee_address'] = 'required|string';
            $rules['shopee_province'] = 'nullable|string'; // Made nullable as per frontend might not send it
            $rules['shopee_city'] = 'nullable|string';     // Made nullable
            $rules['shopee_district'] = 'nullable|string';
            $rules['shopee_village'] = 'nullable|string';
            $rules['shopee_postal_code'] = 'nullable|string|max:10';
        }

        // Giveaway Validation
        if ($request->category === 'giveaway') {
            $rules['giveaway_receiver'] = 'required|string|max:255';
            $rules['giveaway_phone'] = 'required|string|max:50';
            $rules['giveaway_address'] = 'required|string';
            $rules['giveaway_province'] = 'required|string';
            $rules['giveaway_city'] = 'required|string';
            $rules['giveaway_district'] = 'nullable|string';
            $rules['giveaway_village'] = 'nullable|string';
            $rules['giveaway_postal_code'] = 'nullable|string|max:10';
            $rules['giveaway_notes'] = 'nullable|string';
        }

        $request->validate($rules);

        DB::beginTransaction();

        try {
            // Verify HP items availability
            $productDetails = collect();
            if ($request->product_detail_ids) {
                $productDetails = ProductDetail::whereIn('id', $request->product_detail_ids)
                    ->where('status', 'available')
                    ->get();

                if ($productDetails->count() !== count($request->product_detail_ids)) {
                    throw new \Exception('Beberapa barang HP sudah tidak tersedia atau sudah keluar stok.');
                }
            }

            // Verify Non-HP items availability and Deduct
            $user = Auth::user();
            if ($request->non_hp_items) {
                foreach ($request->non_hp_items as $item) {
                    $product = Product::findOrFail($item['product_id']);

                    // Identify Inventory Source based on User
                    $invQuery = Inventory::where('product_id', $item['product_id']);

                    if ($user->branch_id) {
                        $invQuery->where('placement_type', 'branch')->where('placement_id', $user->branch_id);
                    } elseif ($user->warehouse_id) {
                        $invQuery->where('placement_type', 'warehouse')->where('placement_id', $user->warehouse_id);
                    } elseif ($user->online_shop_id) {
                        $invQuery->where('placement_type', 'online_shop')->where('placement_id', $user->online_shop_id);
                    } else if (!$user->hasRole('super_admin')) {
                        throw new \Exception("Anda tidak memiliki lokasi fisik untuk mengurangi stok.");
                    }

                    $inventory = $invQuery->first();

                    if (!$inventory || $inventory->quantity < $item['quantity']) {
                        throw new \Exception("Stok tidak cukup untuk produk: {$product->name}");
                    }

                    // Deduct
                    $inventory->decrement('quantity', $item['quantity']);

                    // Log
                    InventoryLog::create([
                        'product_id' => $item['product_id'],
                        'type' => 'out',
                        'quantity' => $item['quantity'],
                        'balance_after' => $inventory->quantity,
                        'description' => "Stock Out ({$request->category})",
                        'reference_id' => 'OUT-' . time(), // Temp ref
                        'user_id' => $user->id,
                        'branch_id' => $user->branch_id ?? null,
                        'warehouse_id' => $user->warehouse_id ?? null,
                        'online_shop_id' => $user->online_shop_id ?? null,
                    ]);
                }
            }

            // Handle File Upload
            $proofImagePath = null;
            if ($request->hasFile('proof_image')) {
                $proofImagePath = $request->file('proof_image')->store('stock-outs/proofs', 'public');
            }

            // For Shopee, store per-item data (Model casts to JSON automatically)
            $shopeeItemsData = null;
            if ($request->category === 'shopee' && $request->shopee_items) {
                $shopeeItemsData = $request->shopee_items; // Don't json_encode - Model 'array' cast handles it
            }

            // Create stock out record
            $stockOut = StockOut::create([
                'receipt_id' => StockOut::generateReceiptId(),
                'category' => $request->category,
                'user_id' => Auth::id(),
                'inventory_user_id' => $request->inventory_user_id,
                'selling_price' => $request->selling_price,
                // Pindah Cabang
                'destination_branch_id' => $request->destination_branch_id,
                'receiver_name' => $request->receiver_name,
                'transfer_notes' => $request->transfer_notes,
                // Kesalahan Input
                'deletion_reason' => $request->deletion_reason,
                // Retur
                'retur_officer' => $request->retur_officer,
                'retur_seal' => $request->retur_seal,
                'retur_issue' => $request->retur_issue,
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'return_destination_id' => $request->return_destination_id,
                'proof_image' => $proofImagePath,

                // Shopee (per-item data stored as JSON)
                'shopee_items_data' => $shopeeItemsData,
                'shopee_receiver' => $request->shopee_receiver,
                'shopee_phone' => $request->shopee_phone,
                'shopee_address' => $request->shopee_address,
                'shopee_province' => $request->shopee_province,
                'shopee_city' => $request->shopee_city,
                'shopee_district' => $request->shopee_district,
                'shopee_village' => $request->shopee_village,
                'shopee_postal_code' => $request->shopee_postal_code,

                // Giveaway
                'giveaway_receiver' => $request->giveaway_receiver,
                'giveaway_phone' => $request->giveaway_phone,
                'giveaway_address' => $request->giveaway_address,
                'giveaway_province' => $request->giveaway_province,
                'giveaway_city' => $request->giveaway_city,
                'giveaway_district' => $request->giveaway_district,
                'giveaway_village' => $request->giveaway_village,
                'giveaway_postal_code' => $request->giveaway_postal_code,
                'giveaway_notes' => $request->giveaway_notes,

                'notes' => $request->notes, // Generic notes
                'non_hp_items' => $request->non_hp_items, // Stored as JSON
            ]);

            // Attach items and update status
            $newStatus = $this->getStatusByCategory($request->category);

            $pricePerItem = 0;
            if ($request->category === 'shopee' && $request->selling_price && $productDetails->count() > 0) {
                $pricePerItem = $request->selling_price / $productDetails->count();
            }

            foreach ($productDetails as $detail) {
                /** @var \App\Models\ProductDetail $detail */
                $stockOut->items()->attach($detail->id);

                $updateData = ['status' => $newStatus];

                // Update selling price if applicable (Shopee)
                if ($request->category === 'shopee') {
                    $updateData['selling_price'] = $pricePerItem;
                }

                // If pindah_cabang, move location immediately
                if ($request->category === 'pindah_cabang') {
                    $updateData['placement_type'] = 'branch';
                    $updateData['placement_id'] = $request->destination_branch_id;
                }

                // If retur, move item to the warehouse
                if ($request->category === 'retur' && $request->return_destination_id) {
                    $updateData['placement_type'] = 'warehouse';
                    $updateData['placement_id'] = $request->return_destination_id;
                }

                $detail->update($updateData);
            }

            DB::commit();

            return response()->json([
                'message' => 'Stok berhasil dikeluarkan',
                'data' => $stockOut->load(['items.product', 'user'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // Get single stock out
    public function show($id)
    {
        $stockOut = StockOut::with(['items.product', 'user', 'inventoryUser', 'destinationBranch'])
            ->where('id', $id)
            ->orWhere('receipt_id', $id)
            ->firstOrFail();

        return response()->json($stockOut);
    }

    // Get Shopee History
    public function shopeeHistory(Request $request)
    {
        $query = StockOut::with(['items.product', 'user', 'inventoryUser'])
            ->where('category', 'shopee');

        if ($request->has('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('receipt_id', 'like', "%{$search}%")
                    ->orWhere('shopee_tracking_no', 'like', "%{$search}%") // legacy
                    ->orWhere('shopee_receiver', 'like', "%{$search}%")    // legacy
                    ->orWhere('shopee_items_data', 'like', "%{$search}%"); // search in JSON
            });
        }

        $history = $query->latest()->paginate(20);

        return response()->json($history);
    }

    // Track by IMEI or Receipt ID
    public function track(Request $request)
    {
        try {
            $query = $request->q;
            if (!$query || strlen($query) < 3) {
                return response()->json(['message' => 'Query minimal 3 karakter'], 422);
            }

            $results = [];

            // 1. Search STOCK IN
            $productDetails = ProductDetail::with(['product', 'distributor', 'user'])
                ->where('imei', 'like', "{$query}%") // Cari yang berawalan 123...
                ->orWhere('imei', $query)            // Atau persis sama
                ->get();

            foreach ($productDetails as $detail) {
                $results[] = [
                    'type' => 'stock_in',
                    'id' => 'IN-' . $detail->id,
                    'imei' => $detail->imei,
                    'product_name' => $detail->product?->name,
                    'status' => $detail->status,
                    'created_at' => $detail->created_at->format('Y-m-d H:i:s'), // Simpan format asli untuk sorting
                ];
            }

            // 2. Search STOCK OUT (Optimized Query)
            $stockOuts = StockOut::with(['items.product', 'user', 'destinationBranch'])
                ->where('receipt_id', 'like', "{$query}%")
                ->orWhere('shopee_tracking_no', $query) // No Resi biasanya harus spesifik
                ->orWhere('shopee_items_data', 'like', "%{$query}%")
                ->orWhereHas('items', function ($q) use ($query) {
                    $q->where('imei', 'like', "{$query}%");
                })
                ->get()
                ->unique('id');

            foreach ($stockOuts as $out) {
                try {
                    // 1. Ambil data Shopee Items
                    $shopeeItems = $out->shopee_items_data;

                    // Pastikan $shopeeItems adalah array (handle cast otomatis Laravel atau manual)
                    if (is_string($shopeeItems)) {
                        $shopeeItems = json_decode($shopeeItems, true);
                    }
                    $shopeeItems = $shopeeItems ?? [];

                    $shopeeReceivers = [];
                    $shopeeTrackingNos = [];

                    // 2. Logic Ekstraksi Data
                    if (count($shopeeItems) > 0) {
                        foreach ($shopeeItems as $item) {
                            if (!empty($item['receiver']))
                                $shopeeReceivers[] = $item['receiver'];
                            if (!empty($item['tracking_no']))
                                $shopeeTrackingNos[] = $item['tracking_no'];
                        }
                    }

                    // 3. Fallback ke field legacy jika array kosong (untuk data lama)
                    if (empty($shopeeReceivers) && $out->shopee_receiver) {
                        $shopeeReceivers[] = $out->shopee_receiver;
                    }
                    if (empty($shopeeTrackingNos) && $out->shopee_tracking_no) {
                        $shopeeTrackingNos[] = $out->shopee_tracking_no;
                    }

                    // 4. Masukkan ke hasil akhir
                    $results[] = [
                        'type' => 'stock_out',
                        'id' => $out->receipt_id,
                        'category' => $out->category,
                        'items' => $out->items->map(fn($i) => [
                            'imei' => $i->imei,
                            'product_name' => $i->product?->name,
                        ])->toArray(), // Jangan lupa tambahkan logic non_hp_items yang tadi jika perlu

                        // INI YANG PENTING: Gabungkan array jadi string untuk ditampilkan di UI
                        'shopee_receiver' => implode(', ', array_unique($shopeeReceivers)) ?: null,
                        'shopee_tracking_no' => implode(', ', array_unique($shopeeTrackingNos)) ?: null,

                        'destination_branch' => $out->destinationBranch?->name,
                        'receiver_name' => $out->receiver_name,
                        'customer_name' => $out->customer_name,
                        'processed_by' => $out->user?->name ?? $out->user?->username,
                        'created_at' => $out->created_at,
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }

            usort($results, function ($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            return response()->json([
                'query' => $query,
                'count' => count($results),
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    // Helper: Get status based on category
    private function getStatusByCategory(string $category): string
    {
        return match ($category) {
            'pindah_cabang' => 'available', // Direct transfer: available at destination immediately
            'kesalahan_input' => 'deleted',
            'retur' => 'returned',
            'shopee' => 'sold',
            default => 'out'
        };
    }
}
