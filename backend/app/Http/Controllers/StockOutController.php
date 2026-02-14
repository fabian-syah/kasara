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
use App\Models\StockOutNonHpItem;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\OnlineShop;
use App\Models\Distributor;

class StockOutController extends Controller
{
    // List all stock outs
    public function index(Request $request)
    {
        $query = StockOut::with(['user', 'inventoryUser', 'destinationBranch', 'destination', 'items.product']);

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
                    'orderan_online',
                    'shopee',
                    'giveaway',
                    'keluar',
                    'hadiah',
                    'brand_ambassador',
                    'event',
                    'promo',
                    'inventaris'
                ])
            ],
            'sub_category' => 'required_if:category,keluar|nullable|string',
            'product_detail_ids' => 'required_without:non_hp_items|array',
            'product_detail_ids.*' => 'exists:product_details,id',
            'non_hp_items' => 'required_without:product_detail_ids|array',
            'non_hp_items.*.product_id' => 'required|exists:products,id',
            'non_hp_items.*.quantity' => 'required|integer|min:1',

            // Pindah Cabang
            'destination_type' => 'required_if:category,pindah_cabang|nullable|in:branch,warehouse,online_shop,distributor',
            'destination_id' => 'required_if:category,pindah_cabang|nullable|integer',
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
            // Shopee specific Items validation
            if ($request->has('shopee_items')) {
                $rules['shopee_items'] = 'nullable|array';
                $rules['shopee_items.*.product_detail_id'] = 'required|exists:product_details,id';
                $rules['shopee_items.*.tracking_no'] = 'required|string|max:100';
                $rules['shopee_items.*.selling_price'] = 'required|numeric|min:0'; // Per-item SRP
            }

            if ($request->has('non_hp_items')) {
                $rules['non_hp_items.*.selling_price'] = 'required|numeric|min:0'; // Per-item SRP for Non-HP
            }

            // Validate Global Fields
            $rules['shopee_receiver'] = 'required|string|max:255';
            $rules['shopee_phone'] = 'nullable|string|max:50'; // Optional
            $rules['shopee_address'] = 'required|string';
            $rules['shopee_province'] = 'nullable|string';
            $rules['shopee_city'] = 'nullable|string';
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

                    // === SPECIAL HANDLER FOR HP BULK STOCK OUT (e.g. Kesalahan Input) ===
                    if ($product->type === 'hp') {
                        // ... (keep existing HP bulk logic) ...
                        $hpQuery = ProductDetail::where('product_id', $product->id)
                            ->where('status', 'available');

                        if ($user->branch_id) {
                            $hpQuery->where('placement_type', 'branch')->where('placement_id', $user->branch_id);
                        } elseif ($user->warehouse_id) {
                            $hpQuery->where('placement_type', 'warehouse')->where('placement_id', $user->warehouse_id);
                        } elseif ($user->online_shop_id) {
                            $hpQuery->where('placement_type', 'online_shop')->where('placement_id', $user->online_shop_id);
                        }

                        $availableCount = $hpQuery->count();
                        if ($availableCount < $item['quantity']) {
                            throw new \Exception("Stok HP tidak cukup untuk: {$product->name}. Tersedia: $availableCount");
                        }

                        // Fetch LATEST items to remove (LIFO for rollback/error correction)
                        $itemsToRemove = $hpQuery->latest()->take($item['quantity'])->get();

                        foreach ($itemsToRemove as $detail) {
                            $detail->delete();
                        }
                        continue;
                    }
                    // === END HP HANDLER ===

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
                $shopeeItemsData = $request->shopee_items;
            }

            // Calculate Total Selling Price
            $totalSellingPrice = 0;
            if ($request->category === 'shopee') {
                if ($request->shopee_items) {
                    foreach ($request->shopee_items as $item) {
                        $totalSellingPrice += ($item['selling_price'] ?? 0);
                    }
                }
                if ($request->non_hp_items) {
                    foreach ($request->non_hp_items as $item) {
                        $unitPrice = $item['selling_price'] ?? 0;
                        $totalSellingPrice += ($unitPrice * $item['quantity']);
                    }
                }
            } else {
                $totalSellingPrice = $request->selling_price;
            }

            // Map Destination Type
            $destinationType = null;
            if ($request->category === 'pindah_cabang') {
                $destinationType = match ($request->destination_type) {
                    'branch' => 'branch', // Stored as simple string or mapped in model? 
                    'warehouse' => 'warehouse',
                    'online_shop' => 'online_shop',
                    'distributor' => 'distributor',
                    default => null
                };
            }

            // Create stock out record
            // Pastikan destinationType diambil dari request dengan benar
            // --- Tambahkan logika ini tepat sebelum StockOut::create ---
            $destinationType = $request->destination_type;
            $destinationId = $request->destination_id;

            // Membuat record stock out
            $stockOut = StockOut::create([
                'receipt_id' => StockOut::generateReceiptId(),
                'category' => $request->category,
                'sub_category' => $request->sub_category, // Ambil sub_category jika kategori adalah 'keluar'
                'user_id' => Auth::id(),
                'inventory_user_id' => $request->inventory_user_id,
                'selling_price' => $totalSellingPrice,

                // FIX: Mapping lokasi tujuan agar terbaca di History & Akun Gudang
                'destination_type' => ($request->category === 'pindah_cabang') ? $destinationType : null,
                'destination_id' => ($request->category === 'pindah_cabang') ? $destinationId : null,

                // Status: Jika pindah cabang harus 'pending' supaya muncul di menu Transfer Masuk tujuan
                'status' => ($request->category === 'pindah_cabang') ? 'pending' : 'received',

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

                // Shopee / Orderan Online
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

                'notes' => $request->notes,
                'non_hp_items' => $request->non_hp_items, // Data JSON Non-HP
            ]);

            // Create StockOutNonHpItem records
            if ($request->non_hp_items) {
                foreach ($request->non_hp_items as $item) {
                    $prod = Product::find($item['product_id']);
                    // Exclude HP type (handled by deletion above)
                    if ($prod && $prod->type !== 'hp') {
                        StockOutNonHpItem::create([
                            'stock_out_id' => $stockOut->id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'received_quantity' => 0,
                            'returned_quantity' => 0,
                        ]);
                    }
                }
            }

            // Attach items and update status
            $newStatus = $this->getStatusByCategory($request->category);

            foreach ($productDetails as $detail) {
                /** @var \App\Models\ProductDetail $detail */
                $stockOut->items()->attach($detail->id);

                $updateData = ['status' => $newStatus];

                // Update selling price if applicable (Shopee - HP items)
                if ($request->category === 'shopee') {
                    // Find the selling price for this specific item from shopee_items array
                    $itemData = collect($request->shopee_items)->firstWhere('product_detail_id', $detail->id);
                    if ($itemData && isset($itemData['selling_price'])) {
                        $updateData['selling_price'] = $itemData['selling_price'];
                    }
                }

                // If pindah_cabang, move location immediately BUT set status to in_transit
                if ($request->category === 'pindah_cabang') {
                    $updateData['status'] = 'in_transit'; // OTW

                    // Update Placement to Destination
                    if ($request->destination_type === 'branch') {
                        $updateData['placement_type'] = 'branch';
                        $updateData['placement_id'] = $request->destination_id;
                    } elseif ($request->destination_type === 'warehouse') {
                        $updateData['placement_type'] = 'warehouse';
                        $updateData['placement_id'] = $request->destination_id;
                    } elseif ($request->destination_type === 'online_shop') {
                        $updateData['placement_type'] = 'online_shop';
                        $updateData['placement_id'] = $request->destination_id;
                    }
                }

                // If retur, move item to the warehouse
                if ($request->category === 'retur' && $request->return_destination_id) {
                    $updateData['placement_type'] = 'warehouse';
                    $updateData['placement_id'] = $request->return_destination_id;
                }

                $detail->update($updateData);
            }

            DB::commit();

            // Dispatch Event for Real-time Update
            try {
                event(new \App\Events\StockOutEvent($stockOut->load(['items.product', 'user', 'destinationBranch', 'inventoryUser'])));
            } catch (\Exception $e) {
                \Log::error("Failed to broadcast StockOutEvent: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Stok berhasil dikeluarkan',
                'data' => $stockOut
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // Get single stock out
    public function show($id)
    {
        $stockOut = StockOut::with(['items.product', 'user', 'inventoryUser', 'destinationBranch', 'destination'])
            ->where('id', $id)
            ->orWhere('receipt_id', $id)
            ->firstOrFail();

        return response()->json($stockOut);
    }

    // Get Shopee History
    public function shopeeHistory(Request $request)
    {
        $query = StockOut::with(['items.product', 'user', 'inventoryUser'])
            ->whereIn('category', ['shopee', 'orderan_online']);

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

        // Enrich non_hp_items with product names
        $productIds = [];
        foreach ($history->items() as $item) {
            if ($item->non_hp_items) {
                foreach ($item->non_hp_items as $nonHpItem) {
                    $productIds[] = $nonHpItem['product_id'];
                }
            }
        }

        if (!empty($productIds)) {
            $products = Product::whereIn('id', array_unique($productIds))->get()->keyBy('id');

            foreach ($history->items() as $item) {
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

            // 1. Search STOCK IN - Pakai Exact Match (Hapus LIKE)
            $productDetails = ProductDetail::with(['product', 'distributor', 'user'])
                ->where('imei', $query) // Harus persis sama
                ->get();

            foreach ($productDetails as $detail) {
                $results[] = [
                    'type' => 'stock_in',
                    'id' => 'IN-' . $detail->id,
                    'imei' => $detail->imei,
                    'product_name' => $detail->product?->name,
                    'status' => $detail->status,
                    'placement_type' => $detail->placement_type,
                    'placement_id' => $detail->placement_id,
                    // Resolving placement name for display (Same logic as StockOut)
                    'placement_name' => match ($detail->placement_type) {
                        'branch' => \App\Models\Branch::find($detail->placement_id)?->name ?? 'Unknown Branch',
                        'warehouse' => \App\Models\Warehouse::find($detail->placement_id)?->name ?? 'Unknown Warehouse',
                        'online_shop' => \App\Models\OnlineShop::find($detail->placement_id)?->name ?? 'Unknown Shop',
                        default => $detail->placement_type . ' #' . $detail->placement_id
                    },
                    // Pastikan format string untuk sorting
                    'created_at' => $detail->created_at->toDateTimeString(),
                    'distributor' => $detail->distributor?->name,
                    'input_by' => $detail->user?->name,
                    'ram' => $detail->ram,
                    'storage' => $detail->storage,
                    'selling_price' => $detail->selling_price,
                    'condition' => $detail->condition,
                ];
            }

            // 2. Search STOCK OUT - Pakai Exact Match untuk IMEI dan Resi
            $stockOuts = StockOut::with(['items.product', 'user', 'destinationBranch', 'destination'])
                ->where('receipt_id', $query) // Exact Match ID Resi Internal
                ->orWhere('shopee_tracking_no', $query) // Exact Match No Resi Shopee
                ->orWhereHas('items', function ($q) use ($query) {
                    $q->where('imei', $query); // Exact Match IMEI
                })
                // Opsional: Jika masih ingin mencari di dalam JSON Shopee tapi secara spesifik
                // Kita bisa biarkan ini jika query-nya memang nomor resi lengkap
                ->orWhere('shopee_items_data', 'like', "%\"{$query}\"%")
                ->get()
                ->unique('id');

            foreach ($stockOuts as $out) {
                try {
                    $shopeeItems = $out->shopee_items_data; // Restore assignment!
                    if (is_string($shopeeItems))
                        $shopeeItems = json_decode($shopeeItems, true); // Safety
                    $shopeeItems = $shopeeItems ?? [];

                    $shopeeReceivers = [];
                    $shopeeTrackingNos = [];

                    // 1. Extract from Shopee Items (IMEI)
                    if (count($shopeeItems) > 0) {
                        \Illuminate\Support\Facades\Log::info("Track {$out->receipt_id} - Shopee Items: " . json_encode($shopeeItems));
                        foreach ($shopeeItems as $item) {
                            $tNo = is_array($item) ? ($item['tracking_no'] ?? null) : ($item->tracking_no ?? null);
                            if (!empty($tNo))
                                $shopeeTrackingNos[] = $tNo;

                            $rec = is_array($item) ? ($item['receiver'] ?? null) : ($item->receiver ?? null);
                            if (!empty($rec))
                                $shopeeReceivers[] = $rec;
                        }
                    }

                    // 2. Extract from Non-HP Items
                    $nonHpItems = $out->non_hp_items ?? [];
                    if (is_string($nonHpItems))
                        $nonHpItems = json_decode($nonHpItems, true); // Safety

                    if (!empty($nonHpItems)) {
                        foreach ($nonHpItems as $item) {
                            $tNo = is_array($item) ? ($item['tracking_no'] ?? null) : ($item->tracking_no ?? null);
                            if (!empty($tNo))
                                $shopeeTrackingNos[] = $tNo;
                        }
                    }

                    if (empty($shopeeReceivers) && $out->shopee_receiver) {
                        $shopeeReceivers[] = $out->shopee_receiver;
                    }
                    if (empty($shopeeTrackingNos) && $out->shopee_tracking_no) {
                        $shopeeTrackingNos[] = $out->shopee_tracking_no;
                    }

                    // Prepare Items List (Merge HP and Non-HP)
                    $mergedItems = $out->items->map(fn($i) => [
                        'type' => 'hp',
                        'imei' => $i->imei,
                        'product_name' => $i->product?->name,
                        'quantity' => 1,
                    ])->toArray();

                    // Enrich Non-HP Items with Product Name
                    if (!empty($nonHpItems)) {
                        $productIds = array_column($nonHpItems, 'product_id');
                        if (!empty($productIds)) {
                            $products = \App\Models\Product::whereIn('id', $productIds)->pluck('name', 'id');
                            foreach ($nonHpItems as $item) {
                                $mergedItems[] = [
                                    'type' => 'non-hp',
                                    'imei' => '-',
                                    'product_name' => $products[$item['product_id']] ?? 'Unknown Product',
                                    'quantity' => $item['quantity'] ?? 1,
                                    'tracking_no' => $item['tracking_no'] ?? null,
                                ];
                            }
                        }
                    }

                    $results[] = [
                        'type' => 'stock_out',
                        'id' => $out->receipt_id,
                        'category' => $out->category,
                        'items' => $mergedItems,
                        'shopee_receiver' => implode(', ', array_unique($shopeeReceivers)) ?: null,
                        'shopee_tracking_no' => implode(', ', array_unique($shopeeTrackingNos)) ?: null,
                        'destination_branch' => $out->destinationBranch?->name,
                        'destination' => $out->destination ? ['name' => $out->destination->name, 'type' => $out->destination_type] : null,
                        'receiver_name' => $out->receiver_name,
                        'customer_name' => $out->customer_name,
                        'processed_by' => $out->user?->name ?? $out->user?->username,
                        'created_at' => $out->created_at->toDateTimeString(),
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Urutkan: Terbaru di paling atas (Status keluar biasanya paling baru)
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
            ], 500);
        }
    }

    // List incoming transfers for current user's location
    public function indexIncoming()
    {
        $user = Auth::user();
        if (!$user)
            return response()->json(['data' => []]);

        $query = StockOut::with(['items.product', 'nonHpItems', 'user', 'inventoryUser', 'destinationBranch', 'destination'])
            ->where('category', 'pindah_cabang')
            ->where('status', 'pending');

        // Filter by Destination
        $query->where(function ($q) use ($user) {
            $hasFilter = false;

            // Branch
            $branchIds = $user->getAccessibleBranchIds();
            if (!empty($branchIds)) {
                $q->orWhere(function ($sub) use ($branchIds) {
                    $sub->where('destination_type', 'branch')
                        ->whereIn('destination_id', $branchIds);
                });
                $hasFilter = true;
            }

            // Warehouse
            $warehouseIds = $user->getAccessibleWarehouseIds();
            if (!empty($warehouseIds)) {
                $q->orWhere(function ($sub) use ($warehouseIds) {
                    $sub->where('destination_type', 'warehouse')
                        ->whereIn('destination_id', $warehouseIds);
                });
                $hasFilter = true;
            }

            // Online Shop
            $onlineShopIds = $user->getAccessibleOnlineShopIds();
            if (!empty($onlineShopIds)) {
                $q->orWhere(function ($sub) use ($onlineShopIds) {
                    $sub->where('destination_type', 'online_shop')
                        ->whereIn('destination_id', $onlineShopIds);
                });
                $hasFilter = true;
            }

            // If no specific location assigned, restrict access unless Super Admin
            if (!$hasFilter) {
                if ($user->hasRole('super_admin')) {
                    // Show all (do nothing, let base query stand)
                    $q->orWhereRaw('1 = 1');
                } else {
                    // Block access
                    $q->whereRaw('0 = 1');
                }
            }
        });

        $transfers = $query->latest()->get();

        // Enrich Non-HP Items
        foreach ($transfers as $transfer) {
            if ($transfer->non_hp_items) {
                // ... same logic to enrich names ...
                $nonHpItems = is_string($transfer->non_hp_items) ? json_decode($transfer->non_hp_items, true) : $transfer->non_hp_items;
                $pIds = array_column($nonHpItems, 'product_id');
                if (!empty($pIds)) {
                    $products = Product::whereIn('id', $pIds)->pluck('name', 'id');
                    foreach ($nonHpItems as &$item) {
                        $item['product_name'] = $products[$item['product_id']] ?? 'Unknown';
                    }
                    $transfer->non_hp_items = $nonHpItems;
                }
            }
        }

        return response()->json(['data' => $transfers]);
    }

    // Confirm Incoming Transfer
    public function confirm(Request $request, $id)
    {
        $request->validate([
            'items' => 'nullable|array', // List of Accepted Item IDs (HP)
            'non_hp_items' => 'nullable|array', // List of Accepted Quantities
        ]);

        DB::beginTransaction();
        try {
            $stockOut = StockOut::with(['items', 'nonHpItems'])->findOrFail($id);

            if ($stockOut->status !== 'pending') {
                throw new \Exception('Transfer ini sudah diproses.');
            }

            // 1. Process HP Items
            $acceptedItemIds = $request->items ?? [];
            foreach ($stockOut->items as $item) {
                if (in_array($item->id, $acceptedItemIds)) {
                    // Accepted: Status Available, Placement Updated (Already set to destination in store, just update status)
                    $item->update(['status' => 'available']);
                } else {
                    // Rejected: Return to Sender
                    // We need to know who sent it. $stockOut->user_id -> User -> Branch/Warehouse?
                    // Or check stockOut log?
                    // Easiest: Set placement back to Source.
                    // Problem: We didn't store explicit source_type/id in StockOut, only implied by creator.
                    // Let's assume creator's CURRENT location? No, insecure.
                    // We should have stored source.
                    // Fallback: If rejection happens, set status 'returned' and keep placement? 
                    // Or set placement to $stockOut->user->branch_id?

                    // For now, let's assume ALL accepted for simplicity or minimal rejection flow.
                    // If rejected, change status to 'in_transit' (return trip) or 'available' at SOURCE.

                    $sender = $stockOut->user; // Creator
                    // Revert placement
                    if ($sender->branch_id) {
                        $item->update(['placement_type' => 'branch', 'placement_id' => $sender->branch_id, 'status' => 'available']);
                    } elseif ($sender->warehouse_id) {
                        $item->update(['placement_type' => 'warehouse', 'placement_id' => $sender->warehouse_id, 'status' => 'available']);
                    }
                }
            }

            // 2. Process Non-HP Items
            if ($request->non_hp_items) {
                foreach ($request->non_hp_items as $submittedItem) {
                    $record = StockOutNonHpItem::where('stock_out_id', $stockOut->id)
                        ->where('product_id', $submittedItem['product_id'])
                        ->first();

                    if ($record) {
                        $acceptedQty = $submittedItem['received_quantity'];
                        $record->update(['received_quantity' => $acceptedQty]);

                        // Add to Inventory at Destination
                        $user = Auth::user(); // Receiver
                        $locationField = $user->branch_id ? 'branch_id' : ($user->warehouse_id ? 'warehouse_id' : 'online_shop_id');
                        $locationId = $user->branch_id ?? $user->warehouse_id ?? $user->online_shop_id;
                        $placementType = $user->branch_id ? 'branch' : ($user->warehouse_id ? 'warehouse' : 'online_shop');

                        if ($acceptedQty > 0) {
                            $inventory = Inventory::firstOrCreate(
                                [
                                    'product_id' => $submittedItem['product_id'],
                                    'placement_type' => $placementType,
                                    'placement_id' => $locationId,
                                ],
                                ['quantity' => 0]
                            );
                            $inventory->increment('quantity', $acceptedQty);

                            // Log In
                            InventoryLog::create([
                                'product_id' => $submittedItem['product_id'],
                                'type' => 'in',
                                'quantity' => $acceptedQty,
                                'balance_after' => $inventory->quantity,
                                'description' => "Transfer Masuk (Ref: {$stockOut->receipt_id})",
                                'user_id' => $user->id,
                                $locationField => $locationId
                            ]);
                        }

                        // Handle Rejection (Return remainder)
                        $rejectedQty = $record->quantity - $acceptedQty;
                        if ($rejectedQty > 0) {
                            // Return to sender... similar logic to HP items.
                            // Need to find sender's inventory and increment.
                            $sender = $stockOut->user;
                            $senderLocationField = $sender->branch_id ? 'branch_id' : ($sender->warehouse_id ? 'warehouse_id' : 'online_shop_id');
                            $senderLocationId = $sender->branch_id ?? $sender->warehouse_id ?? $sender->online_shop_id;
                            $senderType = $sender->branch_id ? 'branch' : ($sender->warehouse_id ? 'warehouse' : 'online_shop');

                            $senderInv = Inventory::firstOrCreate(
                                [
                                    'product_id' => $submittedItem['product_id'],
                                    'placement_type' => $senderType,
                                    'placement_id' => $senderLocationId,
                                ],
                                ['quantity' => 0]
                            );
                            $senderInv->increment('quantity', $rejectedQty);
                        }
                    }
                }
            }

            $stockOut->update([
                'status' => 'received',
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id()
            ]);

            DB::commit();
            return response()->json(['message' => 'Transfer berhasil dikonfirmasi']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // History of Incoming Transfers (Completed)
    public function historyIncoming(Request $request)
    {
        $user = Auth::user();
        if (!$user)
            return response()->json(['message' => 'Unauthorized'], 401);

        $query = StockOut::with(['items.product', 'nonHpItems', 'user', 'inventoryUser', 'destinationBranch', 'destination'])
            ->where('category', 'pindah_cabang')
            ->whereIn('status', ['received', 'rejected']);

        // Filter by Destination
        $query->where(function ($q) use ($user) {
            $hasFilter = false;

            // Branch
            $branchIds = $user->getAccessibleBranchIds();
            if (!empty($branchIds)) {
                $q->orWhere(function ($sub) use ($branchIds) {
                    $sub->where('destination_type', 'branch')
                        ->whereIn('destination_id', $branchIds);
                });
                $hasFilter = true;
            }

            // Warehouse
            $warehouseIds = $user->getAccessibleWarehouseIds();
            if (!empty($warehouseIds)) {
                $q->orWhere(function ($sub) use ($warehouseIds) {
                    $sub->where('destination_type', 'warehouse')
                        ->whereIn('destination_id', $warehouseIds);
                });
                $hasFilter = true;
            }

            // Online Shop
            $onlineShopIds = $user->getAccessibleOnlineShopIds();
            if (!empty($onlineShopIds)) {
                $q->orWhere(function ($sub) use ($onlineShopIds) {
                    $sub->where('destination_type', 'online_shop')
                        ->whereIn('destination_id', $onlineShopIds);
                });
                $hasFilter = true;
            }

            // If no specific location assigned, restrict access unless Super Admin
            if (!$hasFilter) {
                if ($user->hasRole('super_admin')) {
                    $q->orWhereRaw('1 = 1');
                } else {
                    $q->whereRaw('0 = 1');
                }
            }

            // Also include transfers confirmed by this user
            $q->orWhere('confirmed_by', $user->id);
        });

        // Search
        if ($request->has('q') && !empty($request->q)) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('receipt_id', 'like', "%$q%")
                    ->orWhere('receiver_name', 'like', "%$q%")
                    ->orWhere('shopee_tracking_no', 'like', "%$q%");
            });
        }

        $transfers = $query->latest()->paginate(20);

        // Enrich Non-HP Items
        foreach ($transfers as $transfer) {
            if ($transfer->non_hp_items) {
                $nonHpItems = is_string($transfer->non_hp_items) ? json_decode($transfer->non_hp_items, true) : $transfer->non_hp_items;
                $pIds = array_column($nonHpItems, 'product_id');
                if (!empty($pIds)) {
                    $products = Product::whereIn('id', $pIds)->pluck('name', 'id');
                    foreach ($nonHpItems as &$item) {
                        $item['product_name'] = $products[$item['product_id']] ?? 'Unknown';
                    }
                    $transfer->non_hp_items = $nonHpItems;
                }
            }
        }

        return response()->json($transfers);
    }

    // Helper: Get status based on category
    private function getStatusByCategory(string $category): string
    {
        return match ($category) {
            'pindah_cabang' => 'available', // Direct transfer: available at destination immediately (handled by placement_id update)
            'kesalahan_input' => 'sold',    // Fallback if soft delete logic above doesn't cover this path (though standard logic updates status later). But 'sold' is safer than 'deleted'.
            'retur' => 'service',           // Valid enum: ['available', 'sold', 'transfer', 'service', 'booked']. Return often needs check.
            default => 'sold'               // 'orderan_online', 'shopee', 'giveaway', 'keluar', etc. -> 'sold'
        };
    }
}
