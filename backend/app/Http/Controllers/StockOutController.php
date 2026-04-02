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
use App\Traits\VerifiesPin;

class StockOutController extends Controller
{
    use VerifiesPin;
    // List all stock outs
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = StockOut::with(['user', 'inventoryUser', 'destinationBranch', 'destination', 'items.product', 'paymentMethod']);

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

        // LOCATION FILTER (ISOLATION)
        // Only show stock outs created by users in the same location
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'audit', 'analist', 'owner'];
        if ($user && !$user->hasRole($unrestrictedRoles)) {
            $query->whereHas('user', function ($q) use ($user) {
                if ($user->branch_id) {
                    $q->where('branch_id', $user->branch_id);
                } elseif ($user->warehouse_id) {
                    $q->where('warehouse_id', $user->warehouse_id);
                } elseif ($user->online_shop_id) {
                    $q->where('online_shop_id', $user->online_shop_id);
                }
            });
        }

        // AUDIT BRANCH FILTER
        if ($request->branch_id && $user->hasRole($unrestrictedRoles)) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        // DATE FILTER
        if ($request->category !== 'recap_harian') {
            if ($request->month && $request->year) {
                $query->whereMonth('reporting_date', $request->month)
                    ->whereYear('reporting_date', $request->year);
            }
        }

        // DATE FILTER FOR INVENTORY ROLE
        $user = Auth::user();
        if ($user && $user->hasRole('inventory')) {
            $startDate = \Carbon\Carbon::now()->subMonth()->startOfMonth()->toDateString();
            $query->where('reporting_date', '>=', $startDate);
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
                    'penjualan_offline',
                    'orderan_online',
                    'profit',
                    'pindah_cabang_masuk',
                    'pindah_cabang',
                    'retur',
                    'kesalahan_input',
                    'barang_masuk',
                    'refund',
                    'angkat_barang',
                    'tukar_tambah',
                    'giveaway_customer',
                    'hadiah',
                    'brand_ambassador',
                    'event_sponsorship',
                    'promo',
                    'inventaris',
                    'penjualan',
                    'shopee',
                    'bundling',
                    'tukar_unit',
                    'downgrade',
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
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_wa' => 'nullable|string|max:50',
            'transaction_pin' => 'nullable|string|max:10',
            'return_destination_id' => 'required_if:category,retur|nullable|exists:warehouses,id',
            'proof_image' => 'nullable|image|max:10240', // Max 10MB
            'split_payments' => 'nullable|string', // JSON string from frontend
            'is_bundle' => 'nullable|boolean',
            'bundle_description' => 'nullable|string',
            'global_discount_value' => 'nullable|numeric|min:0',
            'global_discount_type' => 'nullable|string|in:fixed,percentage',
            'total_discount' => 'nullable|numeric|min:0',
            'hp_items_meta' => 'nullable|array',
            'hp_items_meta.*.selling_price' => 'nullable|numeric',
            'hp_items_meta.*.item_discount' => 'nullable|numeric',
            'hp_items_meta.*.distributed_discount' => 'nullable|numeric',
        ];

        // Mandatory fields for Sales
        $salesCategories = ['penjualan', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'penjualan_offline', 'shopee', 'orderan_online'];
        if (in_array($request->category, $salesCategories)) {
            // Shopee/Online uses shopee_receiver as customer name
            if ($request->category === 'shopee' || $request->category === 'orderan_online') {
                $rules['shopee_receiver'] = 'required|string|max:255';
                $rules['shopee_phone'] = 'required|string|max:50';
                $rules['shopee_address'] = 'required|string';
            } else {
                $rules['customer_name'] = 'required|string|max:255';
                $rules['customer_wa'] = 'required|string|max:50';
            }

            $rules['notes'] = 'nullable|string';

            // proof_image is required for offline sales, but optional for online sales
            $isOnlineSale = in_array($request->category, ['shopee', 'orderan_online']);
            $rules['proof_image'] = ($isOnlineSale ? 'nullable' : 'required') . '|image|max:10240';

            // Only require PIN if user has it enabled
            $rules['transaction_pin'] = 'nullable|string|max:10';
        }

        // Shopee: Per-item validation (Specific to bulk stock out if used)
        if ($request->category === 'shopee' || $request->category === 'orderan_online') {
            if ($request->has('shopee_items')) {
                $rules['shopee_items'] = 'nullable|array';
                $rules['shopee_items.*.product_detail_id'] = 'required|exists:product_details,id';
                $rules['shopee_items.*.tracking_no'] = 'required|string|max:100';
                $rules['shopee_items.*.selling_price'] = 'required|numeric|min:0';
            }

            if ($request->has('non_hp_items')) {
                $rules['non_hp_items.*.selling_price'] = 'required|numeric|min:0';
            }

            $rules['shopee_tracking_no'] = 'required_if:category,shopee,orderan_online|nullable|string|max:100';
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

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError) return $pinError;

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
                            /** @var ProductDetail $detail */
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
            if (($request->category === 'shopee' || $request->category === 'orderan_online') && $request->shopee_items) {
                $shopeeItemsData = $request->shopee_items;
            }

            // Calculate Total Selling Price
            $totalSellingPrice = 0;
            if ($request->category === 'shopee' || $request->category === 'orderan_online') {
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
                $totalSellingPrice = floatval($request->selling_price ?? 0);

                // Fallback Hitung Total dari Item secara otomatis jika 0 (terutama untuk Pindah Cabang)
                if ($totalSellingPrice == 0) {
                    // Kalkulasi HP
                    if (isset($productDetails) && $productDetails->count() > 0) {
                        foreach ($productDetails as $pd) {
                            $itemPrice = floatval($pd->selling_price);

                            // Jika ProductDetail belum diset selling_price, ambil dari Master Data Harga (ProductPrice)
                            if ($itemPrice == 0 && $pd->product) {
                                $productType = \App\Models\ProductType::where('name', $pd->product->name)->first();
                                if ($productType) {
                                    $priceData = \App\Models\ProductPrice::where('product_type_id', $productType->id)
                                        ->where('condition', $pd->condition)
                                        ->where('ram', $pd->ram)
                                        ->where('storage', $pd->storage)
                                        ->first();
                                    if ($priceData) {
                                        $itemPrice = floatval($priceData->price);
                                    }
                                }
                            }
                            $totalSellingPrice += $itemPrice;
                        }
                    }

                    // Kalkulasi Non-HP
                    if ($request->non_hp_items) {
                        foreach ($request->non_hp_items as $item) {
                            $prod = \App\Models\Product::find($item['product_id']);
                            if ($prod) {
                                $totalSellingPrice += (floatval($prod->price) * intval($item['quantity']));
                            }
                        }
                    }
                }
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

            $destinationType = $request->destination_type;
            $destinationId = $request->destination_id;

            // Calculate Reporting Date based on business logic
            $reportingDate = StockOut::calculateReportingDate(
                $request->category,
                $user->branch ?: ($user->onlineShop ?: null)
            );

            // Membuat record stock out
            $stockOut = StockOut::create([
                'receipt_id' => StockOut::generateReceiptId(),
                'category' => $request->category,
                'reporting_date' => $reportingDate, // Save the calculated business date
                'sub_category' => $request->sub_category, 
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
                'customer_wa' => $request->customer_wa,
                'transaction_pin' => $request->transaction_pin,
                'return_destination_id' => $request->return_destination_id,
                'proof_image' => $proofImagePath,
                'is_bundle' => $request->is_bundle ?? false,
                'bundle_description' => $request->bundle_description,

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
                'shopee_tracking_no' => $request->shopee_tracking_no,

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
                'non_hp_items' => $request->non_hp_items,
                'sales_account' => $request->sales_account,
                'payment_method_id' => $request->payment_method_id,
                'paid_amount' => $request->paid_amount ?? 0,
                'split_payments' => is_string($request->split_payments) ? json_decode($request->split_payments, true) : $request->split_payments,
                'global_discount_value' => $request->global_discount_value ?? 0,
                'global_discount_type' => $request->global_discount_type ?? 'fixed',
                'total_discount' => $request->total_discount ?? 0,
            ]);

            // Pre-generate PDF in the background to speed up WhatsApp sharing later
            if ($stockOut->category === 'penjualan') {
                dispatch(function () use ($stockOut) {
                    try {
                        \App\Http\Controllers\WhatsAppShareController::getDriveLink($stockOut->id);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning("Pre-generation failed for StockOut ID {$stockOut->id}: " . $e->getMessage());
                    }
                })->afterResponse();
            }

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
                            'selling_price' => $item['selling_price'] ?? 0,
                            'item_discount' => $item['item_discount'] ?? 0,
                            'distributed_discount' => $item['distributed_discount'] ?? 0,
                            'received_quantity' => 0,
                            'returned_quantity' => 0,
                        ]);
                    }
                }
            }

            // Handle bundled items (if any)
            if ($request->items) {
                // If the items are passed as a bundle structure, we need to extract the product_detail_ids
                $allBundleItemIds = [];
                $allBundleNonHp = [];

                foreach ($request->items as $item) {
                    if (isset($item['is_bundle']) && $item['is_bundle'] && isset($item['bundle_items'])) {
                        foreach ($item['bundle_items'] as $bItem) {
                            if (isset($bItem['imei']) && $bItem['imei']) {
                                $allBundleItemIds[] = $bItem['id'];
                            } else {
                                $allBundleNonHp[] = [
                                    'product_id' => $bItem['product_id'],
                                    'quantity' => $bItem['quantity'] ?? 1,
                                    'selling_price' => $bItem['price'] ?? 0
                                ];
                            }
                        }
                    }
                }

                // Add to productDetails if not already there
                if (!empty($allBundleItemIds)) {
                    $bundleDetails = ProductDetail::whereIn('id', $allBundleItemIds)
                        ->where('status', 'available')
                        ->get();

                    if ($bundleDetails->count() !== count($allBundleItemIds)) {
                        throw new \Exception('Beberapa barang bundling sudah tidak tersedia.');
                    }
                    $productDetails = $productDetails->merge($bundleDetails);
                }

                // Process Non-HP bundle items
                foreach ($allBundleNonHp as $bNonHp) {
                    $product = Product::findOrFail($bNonHp['product_id']);
                    $invQuery = Inventory::where('product_id', $bNonHp['product_id']);

                    if ($user->branch_id) {
                        $invQuery->where('placement_type', 'branch')->where('placement_id', $user->branch_id);
                    } elseif ($user->warehouse_id) {
                        $invQuery->where('placement_type', 'warehouse')->where('placement_id', $user->warehouse_id);
                    } elseif ($user->online_shop_id) {
                        $invQuery->where('placement_type', 'online_shop')->where('placement_id', $user->online_shop_id);
                    }

                    $inventory = $invQuery->first();
                    if (!$inventory || $inventory->quantity < $bNonHp['quantity']) {
                        throw new \Exception("Stok bundling tidak cukup untuk produk: {$product->name}");
                    }
                    $inventory->decrement('quantity', $bNonHp['quantity']);

                    InventoryLog::create([
                        'product_id' => $bNonHp['product_id'],
                        'type' => 'out',
                        'quantity' => $bNonHp['quantity'],
                        'balance_after' => $inventory->quantity,
                        'description' => "Stock Out Bundling ({$request->category})",
                        'reference_id' => 'OUT-BUN-' . time(),
                        'user_id' => $user->id,
                        'branch_id' => $user->branch_id ?? null,
                        'warehouse_id' => $user->warehouse_id ?? null,
                        'online_shop_id' => $user->online_shop_id ?? null,
                    ]);

                    StockOutNonHpItem::create([
                        'stock_out_id' => $stockOut->id,
                        'product_id' => $bNonHp['product_id'],
                        'quantity' => $bNonHp['quantity'],
                        'received_quantity' => 0,
                        'returned_quantity' => 0,
                    ]);
                }
            }

            // Attach items and update status
            $newStatus = $this->getStatusByCategory($request->category);

            foreach ($productDetails as $detail) {
                /** @var \App\Models\ProductDetail $detail */
                $hpMeta = $request->hp_items_meta[$detail->id] ?? null;

                $stockOut->items()->attach($detail->id, [
                    'selling_price' => $hpMeta['selling_price'] ?? $detail->selling_price,
                    'item_discount' => $hpMeta['item_discount'] ?? 0,
                    'distributed_discount' => $hpMeta['distributed_discount'] ?? 0,
                ]);

                $updateStatus = $newStatus;
                $updateData = ['status' => $updateStatus];

                // If specialized price sent (like Shopee legacy)
                if ($hpMeta && isset($hpMeta['selling_price'])) {
                    $updateData['selling_price'] = $hpMeta['selling_price'] - ($hpMeta['item_discount'] ?? 0) - ($hpMeta['distributed_discount'] ?? 0);
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
        $stockOut = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'destinationBranch', 'destination', 'paymentMethod'])
            ->where('id', $id)
            ->orWhere('receipt_id', $id)
            ->firstOrFail();

        return response()->json($stockOut);
    }

    // Get Shopee History
    public function shopeeHistory(Request $request)
    {
        $user = Auth::user();
        $query = StockOut::with(['items.product', 'user', 'inventoryUser'])
            ->whereIn('category', ['shopee', 'orderan_online']);

        // LOCATION FILTER (ISOLATION)
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'audit', 'analist', 'owner'];
        if ($user && !$user->hasRole($unrestrictedRoles)) {
            $query->whereHas('user', function ($q) use ($user) {
                if ($user->branch_id) {
                    $q->where('branch_id', $user->branch_id);
                } elseif ($user->warehouse_id) {
                    $q->where('warehouse_id', $user->warehouse_id);
                } elseif ($user->online_shop_id) {
                    $q->where('online_shop_id', $user->online_shop_id);
                }
            });
        }

        if ($request->has('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('receipt_id', 'like', "%{$search}%")
                    ->orWhere('shopee_tracking_no', 'like', "%{$search}%") // legacy
                    ->orWhere('shopee_receiver', 'like', "%{$search}%")    // legacy
                    ->orWhere('shopee_items_data', 'like', "%{$search}%"); // search in JSON
            });
        }

        // DATE FILTERS
        if ($request->date) {
            $query->where('reporting_date', $request->date);
        }
        if ($request->has('month') && !empty($request->month)) {
            $query->whereMonth('reporting_date', $request->month);
        }
        if ($request->has('year') && !empty($request->year)) {
            $query->whereYear('reporting_date', $request->year);
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

            $allEvents = [];

            // 1. Search STOCK IN (Registration Events)
            $productDetails = ProductDetail::with(['product', 'distributor', 'user'])
                ->where('imei', $query)
                ->get();

            foreach ($productDetails as $detail) {
                $allEvents[] = [
                    'type' => 'stock_in',
                    'sub_type' => 'registration',
                    'id' => 'IN-' . $detail->id,
                    'imei' => $detail->imei,
                    'product_name' => $detail->product?->name,
                    'status' => $detail->status,
                    'placement_type' => $detail->placement_type,
                    'placement_id' => $detail->placement_id,
                    'placement_name' => match ($detail->placement_type) {
                        'branch' => \App\Models\Branch::find($detail->placement_id)?->name ?? 'Unknown Branch',
                        'warehouse' => \App\Models\Warehouse::find($detail->placement_id)?->name ?? 'Unknown Warehouse',
                        'online_shop' => \App\Models\OnlineShop::find($detail->placement_id)?->name ?? 'Unknown Shop',
                        default => $detail->placement_type . ' #' . $detail->placement_id
                    },
                    'created_at' => $detail->created_at->toDateTimeString(),
                    'timestamp' => $detail->created_at->timestamp,
                    'distributor' => $detail->distributor?->name,
                    'input_by' => $detail->user?->name,
                    'ram' => $detail->ram,
                    'storage' => $detail->storage,
                    'selling_price' => $detail->selling_price,
                    'condition' => $detail->condition,
                ];
            }

            // 2. Search STOCK OUT (Execution & Arrival Events)
            $stockOuts = StockOut::with(['items.product', 'user', 'inventoryUser', 'destinationBranch', 'destination', 'confirmedBy'])
                ->where('receipt_id', $query)
                ->orWhere('shopee_tracking_no', $query)
                ->orWhereHas('items', function ($q) use ($query) {
                    $q->where('imei', $query);
                })
                ->orWhere('shopee_items_data', 'like', "%\"{$query}\"%")
                ->get()
                ->unique('id');

            foreach ($stockOuts as $out) {
                // Determine shopee info
                $sData = $out->shopee_items_data;
                $shopeeItems = is_string($sData) ? json_decode($sData, true) : (is_array($sData) ? $sData : []);
                $shopeeTrackingNos = [];
                $shopeeReceivers = [];

                foreach ($shopeeItems as $item) {
                    $tNo = is_array($item) ? ($item['tracking_no'] ?? null) : ($item->tracking_no ?? null);
                    if ($tNo)
                        $shopeeTrackingNos[] = $tNo;
                    $rec = is_array($item) ? ($item['receiver'] ?? null) : ($item->receiver ?? null);
                    if ($rec)
                        $shopeeReceivers[] = $rec;
                }

                if (empty($shopeeReceivers) && $out->shopee_receiver)
                    $shopeeReceivers[] = $out->shopee_receiver;
                if (empty($shopeeTrackingNos) && $out->shopee_tracking_no)
                    $shopeeTrackingNos[] = $out->shopee_tracking_no;

                $mergedItems = $out->items->map(fn($i) => [
                    'type' => 'hp',
                    'imei' => $i->imei,
                    'product_name' => $i->product?->name,
                    'quantity' => 1,
                ])->toArray();

                // Non-HP enrich
                $nhData = $out->non_hp_items;
                $nonHpItems = is_string($nhData) ? json_decode($nhData, true) : (is_array($nhData) ? $nhData : []);
                if (!empty($nonHpItems)) {
                    $productIds = array_column($nonHpItems, 'product_id');
                    $products = \App\Models\Product::whereIn('id', $productIds)->pluck('name', 'id');
                    foreach ($nonHpItems as $nhp) {
                        $mergedItems[] = [
                            'type' => 'non-hp',
                            'product_name' => $products[$nhp['product_id']] ?? 'Unknown Product',
                            'quantity' => $nhp['quantity'] ?? 1,
                            'tracking_no' => $nhp['tracking_no'] ?? null,
                        ];
                    }
                }

                // Filter items to only include the searched item if it's an IMEI or Tracking No
                $filteredItems = array_filter($mergedItems, function ($item) use ($query) {
                    if (isset($item['imei']) && stripos($item['imei'], $query) !== false)
                        return true;
                    if (isset($item['tracking_no']) && stripos($item['tracking_no'], $query) !== false)
                        return true;
                    return false;
                });

                // Only use filtered items if there's a match inside items, otherwise show all (meaning the match was on receipt ID)
                if (count($filteredItems) > 0 && stripos($out->receipt_id, $query) === false && stripos($out->shopee_tracking_no, $query) === false) {
                    $mergedItems = array_values($filteredItems);
                }

                // Event 1: The STOCK OUT itself
                $allEvents[] = [
                    'type' => 'stock_out',
                    'sub_type' => 'departure',
                    'id' => $out->receipt_id,
                    'category' => $out->category,
                    'items' => $mergedItems,
                    'shopee_receiver' => implode(', ', array_unique($shopeeReceivers)) ?: null,
                    'shopee_tracking_no' => implode(', ', array_unique($shopeeTrackingNos)) ?: null,
                    'destination' => $out->destination ? ['name' => $out->destination->name, 'type' => $out->destination_type] : null,
                    'receiver_name' => $out->receiver_name,
                    'customer_name' => $out->customer_name,
                    'customer_wa' => $out->customer_wa,
                    'notes' => $out->notes,
                    'transaction_pin' => $out->transaction_pin,
                    'processed_by' => $out->inventoryUser ? ($out->inventoryUser->full_name ?? $out->inventoryUser->name) : ($out->user?->name ?? $out->user?->username),
                    'status' => $out->status,
                    'created_at' => $out->created_at->toDateTimeString(),
                    'timestamp' => $out->created_at->timestamp,
                ];

                // Event 2: The ARRIVAL (if confirmed transfer)
                if ($out->category === 'pindah_cabang' && $out->status === 'received' && $out->confirmed_at) {
                    $allEvents[] = [
                        'type' => 'stock_in',
                        'sub_type' => 'arrival',
                        'id' => $out->receipt_id, // Link to same receipt
                        'imei' => $query, // Contextual imei
                        'product_name' => $out->items->first()?->product?->name ?? 'Mixed Items',
                        'status' => 'available',
                        'placement_type' => $out->destination_type,
                        'placement_id' => $out->destination_id,
                        'placement_name' => $out->destination?->name ?? 'Destination',
                        'created_at' => $out->confirmed_at->toDateTimeString(),
                        'timestamp' => $out->confirmed_at->timestamp,
                        'input_by' => $out->confirmedBy?->name ?? 'Unknown',
                        'condition' => $out->items->first()?->condition ?? '-',
                        'selling_price' => $out->items->first()?->selling_price ?? 0,
                        'is_arrival' => true,
                    ];
                }

                // Event 3: RETUR ARRIVAL (if retur confirmed/accepted)
                if ($out->category === 'retur' && $out->status === 'received' && $out->confirmed_at) {
                    // Find the item to get current placement
                    $returItem = $out->items->first();
                    $allEvents[] = [
                        'type' => 'stock_in',
                        'sub_type' => 'retur_arrival',
                        'id' => $out->receipt_id,
                        'imei' => $query,
                        'product_name' => $returItem?->product?->name ?? 'Unknown',
                        'status' => 'available',
                        'placement_type' => $returItem?->placement_type,
                        'placement_id' => $returItem?->placement_id,
                        'placement_name' => match ($returItem?->placement_type) {
                            'warehouse' => \App\Models\Warehouse::find($returItem?->placement_id)?->name ?? 'Gudang',
                            'branch' => \App\Models\Branch::find($returItem?->placement_id)?->name ?? 'Cabang',
                            'online_shop' => \App\Models\OnlineShop::find($returItem?->placement_id)?->name ?? 'Toko Online',
                            default => 'Unknown'
                        },
                        'created_at' => $out->confirmed_at->toDateTimeString(),
                        'timestamp' => $out->confirmed_at->timestamp,
                        'input_by' => $out->confirmedBy?->name ?? 'Unknown',
                        'condition' => $returItem?->condition ?? '-',
                        'selling_price' => $returItem?->selling_price ?? 0,
                        'is_arrival' => true,
                        'is_retur_return' => true,
                    ];
                }
            }

            // Sort chronologically (Newest first for timeline flow)
            usort($allEvents, function ($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });

            return response()->json([
                'query' => $query,
                'count' => count($allEvents),
                'data' => array_values($allEvents)
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
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
            'inventory_user_id' => 'sometimes|nullable|exists:users,id',
            'transaction_pin' => 'nullable|string|size:4'
        ]);

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError) return $pinError;

        DB::beginTransaction();
        try {
            $stockOut = StockOut::with(['items', 'nonHpItems'])->findOrFail($id);

            if ($stockOut->status !== 'pending') {
                throw new \Exception('Transfer ini sudah diproses.');
            }

            // Determine User for Confirmation (Inventory Account or Logged In User)
            $confirmingUserId = Auth::id();
            if ($request->has('inventory_user_id') && $request->inventory_user_id) {
                // Verify ownership/validity
                $invUser = \App\Models\User::where('id', $request->inventory_user_id)
                    ->where('created_by', Auth::id())
                    ->whereHas('roles', function ($q) {
                        $q->where('name', 'inventory');
                    })
                    ->first();

                if ($invUser) {
                    $confirmingUserId = $invUser->id;
                }
            }

            // Calculate Destination Location from Confirming User
            $destUser = \App\Models\User::find($confirmingUserId);
            $destPlacementType = null;
            $destPlacementId = null;

            if ($destUser->branch_id) {
                $destPlacementType = 'branch';
                $destPlacementId = $destUser->branch_id;
            } elseif ($destUser->warehouse_id) {
                $destPlacementType = 'warehouse';
                $destPlacementId = $destUser->warehouse_id;
            } elseif ($destUser->online_shop_id) {
                $destPlacementType = 'online_shop';
                $destPlacementId = $destUser->online_shop_id;
            }

            // 1. Process HP Items
            $acceptedItemIds = $request->items ?? [];
            foreach ($stockOut->items as $item) {
                if (in_array($item->id, $acceptedItemIds)) {
                    // Accepted: Status Available, Placement Updated to Receiver's Location
                    // This ensures even if 'store' logic missed placement update, 'confirm' fixes it.
                    $item->update([
                        'status' => 'available',
                        'user_id' => $confirmingUserId,
                        'placement_type' => $destPlacementType,
                        'placement_id' => $destPlacementId
                    ]);
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
                    } elseif ($sender->online_shop_id) {
                        $item->update(['placement_type' => 'online_shop', 'placement_id' => $sender->online_shop_id, 'status' => 'available']);
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
                        // Used $destUser calculated above

                        $locationField = $destUser->branch_id ? 'branch_id' : ($destUser->warehouse_id ? 'warehouse_id' : 'online_shop_id');
                        $locationId = $destUser->branch_id ?? $destUser->warehouse_id ?? $destUser->online_shop_id;
                        $placementType = $destPlacementType; // Use calculated type

                        if ($acceptedQty > 0) {
                            $inventory = Inventory::firstOrCreate(
                                [
                                    'product_id' => $submittedItem['product_id'],
                                    'placement_type' => $placementType,
                                    'placement_id' => $locationId,
                                    'user_id' => $confirmingUserId, // Separate inventory by user
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
                                'user_id' => $confirmingUserId,
                                $locationField => $locationId
                            ]);
                        }

                        // Handle Rejection (Return remainder to sender)
                        $rejectedQty = $record->quantity - $acceptedQty;
                        if ($rejectedQty > 0) {
                            // Need to find sender's inventory and increment.
                            $sender = $stockOut->user; // Creator
                            // Use Sender's ID for user_id to return ownership
                            $senderUserId = $sender->id;

                            $senderLocationId = $sender->branch_id ?? $sender->warehouse_id ?? $sender->online_shop_id;
                            $senderType = $sender->branch_id ? 'branch' : ($sender->warehouse_id ? 'warehouse' : 'online_shop');

                            $senderInv = Inventory::firstOrCreate(
                                [
                                    'product_id' => $submittedItem['product_id'],
                                    'placement_type' => $senderType,
                                    'placement_id' => $senderLocationId,
                                    'user_id' => $senderUserId
                                ],
                                ['quantity' => 0]
                            );
                            $senderInv->increment('quantity', $rejectedQty);

                            // Optional: Log Rejection/Return?
                        }
                    }
                }
            }

            // Determine Final Status
            // Check if ANY item was accepted
            $totalAccepted = count($acceptedItemIds);

            if ($request->non_hp_items) {
                foreach ($request->non_hp_items as $submittedItem) {
                    $totalAccepted += ($submittedItem['received_quantity'] ?? 0);
                }
            }

            // If nothing accepted -> Rejected
            // Else -> Received (Partially or Fully)
            $finalStatus = ($totalAccepted > 0) ? 'received' : 'rejected';

            $stockOut->update([
                'status' => $finalStatus,
                'confirmed_at' => now(),
                'confirmed_by' => $confirmingUserId
            ]);

            DB::commit();
            return response()->json(['message' => 'Transfer berhasil dikonfirmasi']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // History of Transfers (Incoming and Outgoing)
    public function historyIncoming(Request $request)
    {
        $user = Auth::user();
        if (!$user)
            return response()->json(['message' => 'Unauthorized'], 401);

        $type = $request->query('type'); // 'outgoing' or 'incoming'

        $query = StockOut::with(['items.product', 'nonHpItems', 'user', 'inventoryUser', 'destinationBranch', 'destination', 'confirmedBy'])
            ->where('category', 'pindah_cabang');

        if ($type === 'outgoing') {
            $query->where('status', 'pending');
        } else {
            $query->whereIn('status', ['received', 'rejected']);
        }

        // Filter by Destination or Source
        $query->where(function ($q) use ($user, $type) {
            if ($type === 'outgoing') {
                $hasFilter = false;

                $branchIds = $user->getAccessibleBranchIds();
                if (!empty($branchIds)) {
                    $q->orWhereHas('user', function ($sub) use ($branchIds) {
                        $sub->whereIn('branch_id', $branchIds);
                    });
                    $hasFilter = true;
                }

                $warehouseIds = $user->getAccessibleWarehouseIds();
                if (!empty($warehouseIds)) {
                    $q->orWhereHas('user', function ($sub) use ($warehouseIds) {
                        $sub->whereIn('warehouse_id', $warehouseIds);
                    });
                    $hasFilter = true;
                }

                $onlineShopIds = $user->getAccessibleOnlineShopIds();
                if (!empty($onlineShopIds)) {
                    $q->orWhereHas('user', function ($sub) use ($onlineShopIds) {
                        $sub->whereIn('online_shop_id', $onlineShopIds);
                    });
                    $hasFilter = true;
                }

                if ($user->hasRole('super_admin')) {
                    $q->orWhereRaw('1 = 1'); // Show all for super admin
                } elseif (!$hasFilter) {
                    $q->whereRaw('0 = 1'); // Restrict if no locations assigned
                }
            } else {
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

                if (!$hasFilter) {
                    if ($user->hasRole('super_admin')) {
                        $q->orWhereRaw('1 = 1');
                    } else {
                        $q->whereRaw('0 = 1');
                    }
                }

                // Also include transfers confirmed by this user
                $q->orWhere('confirmed_by', $user->id);
            }
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
