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
    use \App\Traits\VerifiesPin;
    // List all stock outs
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = StockOut::with(['user', 'inventoryUser', 'destinationBranch', 'destination', 'items.product.brandRelation', 'nonHpDetails.product.brandRelation', 'paymentMethod']);

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
            $query->where(function($q) {
                $q->whereHas('nonHpDetails')
                  ->orWhereNotNull('non_hp_items');
            });
        }

        // LOCATION FILTER (ISOLATION)
        // Only show stock outs created by users in the same location
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner'];
        if ($user && !$user->hasRole($unrestrictedRoles)) {
            $query->whereHas('user', function ($q) use ($user) {
                $bIds = $user->getAccessibleBranchIds();
                $wIds = $user->getAccessibleWarehouseIds();
                $osIds = $user->getAccessibleOnlineShopIds();
                
                $q->where(function($sq) use ($bIds, $wIds, $osIds) {
                    if (!empty($bIds)) $sq->orWhereIn('branch_id', $bIds);
                    if (!empty($wIds)) $sq->orWhereIn('warehouse_id', $wIds);
                    if (!empty($osIds)) $sq->orWhereIn('online_shop_id', $osIds);
                });
            });
        }

        // AUDIT BRANCH FILTER
        if ($request->branch_id && $user->hasRole($unrestrictedRoles)) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        // DATE FILTER
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        if ($request->category !== 'recap_harian') {
            if ($request->date) {
                $query->where('reporting_date', $request->date);
            } elseif ($request->month && $request->year) {
                $m = (int) $request->month;
                $y = (int) $request->year;

                // Role-based Month/Year Restriction
                if ($user && !$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
                    $currentMonth = (int) $logicalNow->format('m');
                    $currentYear = (int) $logicalNow->format('Y');
                    
                    $lastMonthTemp = $logicalNow->copy()->subMonth();
                    $lastMonth = (int) $lastMonthTemp->format('m');
                    $lastMonthYear = (int) $lastMonthTemp->format('Y');

                    // Year must be current year
                    if ($y < $currentYear) {
                        $m = $currentMonth;
                        $y = $currentYear;
                    } elseif ($y == $currentYear) {
                        // Month must be current or previous
                        if ($m < $lastMonth && !($currentMonth == 1 && $m == 12 && $y == $currentYear)) {
                             $m = $currentMonth;
                        }
                    }
                }
                $query->whereMonth('reporting_date', $m)
                    ->whereYear('reporting_date', $y);
            } elseif ($request->start_date && $request->end_date) {
                $query->whereBetween('reporting_date', [$request->start_date, $request->end_date]);
            }
        }

        // DATE FILTER FOR INVENTORY ROLE
        if ($user && $user->hasRole('inventory')) {
            $limitDate = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();
            $query->where('reporting_date', '>=', $limitDate);
        }

        // Filter by Type (HP vs Non-HP)
        if ($request->type === 'hp') {
            $query->whereHas('items');
        } elseif ($request->type === 'non-hp') {
            $query->where(function($q) {
                $q->whereHas('nonHpDetails')
                  ->orWhere(function($sub) {
                      $sub->whereNotNull('non_hp_items')
                          ->where('non_hp_items', 'not like', '[]')
                          ->where('non_hp_items', 'not like', '{}')
                          ->where('non_hp_items', 'not like', '""');
                  });
            });
        }

        $results = $query->with(['items.product', 'items.distributor', 'nonHpDetails.product', 'nonHpDetails.distributor', 'user', 'inventoryUser', 'destination', 'destinationBranch'])
            ->latest()
            ->paginate($request->per_page ?? 20);

        // Transform results to handle bundling consolidation
        $results->getCollection()->transform(function ($stockOut) {
            $details = [];

            // 1. Collect HP Items
            foreach ($stockOut->items as $item) {
                $details[] = [
                    'name' => ($stockOut->is_bundle ? '📦 ' : '') . ($item->product?->name ?? 'Unknown HP'),
                    'qty' => 1,
                    'price' => (float) ($item->pivot?->selling_price ?? 0),
                    'type' => 'HP',
                    'is_hp' => true,
                    'imei' => $item->imei ?? '-',
                    'notes' => $item->pivot?->notes,
                    'distributor_name' => $item->distributor?->name ?? 'KOSONG'
                ];
            }

            // 2. Collect Non-HP Items (Relationship)
            foreach ($stockOut->nonHpDetails as $detail) {
                $details[] = [
                    'name' => ($stockOut->is_bundle ? '📦 ' : '') . ($detail->product?->name ?? 'Item'),
                    'qty' => $detail->quantity,
                    'price' => (float) $detail->selling_price,
                    'type' => 'Item',
                    'is_hp' => false,
                    'imei' => '-',
                    'notes' => $detail->notes,
                    'distributor_name' => $detail->distributor?->name ?? 'KOSONG'
                ];
            }

            // 3. Collect Legacy Non-HP Items (JSON Fallback)
            if ($stockOut->non_hp_items && empty($details)) {
                $jsonItems = is_string($stockOut->non_hp_items) ? json_decode($stockOut->non_hp_items, true) : $stockOut->non_hp_items;
                if (is_array($jsonItems)) {
                    foreach ($jsonItems as $ji) {
                        $details[] = [
                            'name' => $ji['product_name'] ?? 'Item',
                            'qty' => $ji['quantity'] ?? 1,
                            'price' => (float) ($ji['selling_price'] ?? 0),
                            'type' => 'Item',
                            'is_hp' => false,
                            'imei' => '-',
                            'notes' => $ji['notes'] ?? null
                        ];
                    }
                }
            }

            // Consolidate Bundles if applicable
            if ($stockOut->is_bundle) {
                $grouped = [];
                $bundles = [];
                $fallbackBundleName = $stockOut->bundle_description ?: 'Paket Bundling';
                
                // Historical component-aware matching logic
                $bundleComponents = [];
                if ($fallbackBundleName) {
                    $descPart = str_replace('Paket Bundling:', '', $fallbackBundleName);
                    $bundleComponents = array_map('trim', explode(',', $descPart));
                }

                foreach ($details as $d) {
                    $bundleTag = $d['notes'] ?? null;
                    $cleanName = str_replace('📦 ', '', $d['name']);
                    
                    $isPartOfBundle = false;
                    $groupKey = $bundleTag;

                    if ($bundleTag && ($bundleTag === $fallbackBundleName || str_contains(strtolower($bundleTag), 'bundle') || str_contains(strtolower($bundleTag), 'paket'))) {
                        $isPartOfBundle = true;
                    } else if (!$bundleTag && !empty($bundleComponents)) {
                        foreach ($bundleComponents as $idx => $comp) {
                            if (!empty($comp) && (str_contains(strtolower($cleanName), strtolower($comp)) || str_contains(strtolower($comp), strtolower($cleanName)))) {
                                $isPartOfBundle = true;
                                $groupKey = $fallbackBundleName;
                                unset($bundleComponents[$idx]);
                                break;
                            }
                        }
                    }

                    if ($isPartOfBundle && $groupKey) {
                        if (!isset($bundles[$groupKey])) {
                            $bundles[$groupKey] = [
                                'name' => '📦 ' . $groupKey,
                                'qty' => 0,
                                'price' => 0,
                                'type' => 'Bundle',
                                'is_hp' => false,
                                'imei' => [],
                                'is_bundle_row' => true,
                                'bundle_composition' => []
                            ];
                        }
                        $bundles[$groupKey]['qty'] += $d['qty'];
                        $bundles[$groupKey]['price'] += ($d['price'] * $d['qty']);
                        $bundles[$groupKey]['bundle_composition'][] = $d['type'] === 'HP' ? 'IMEI' : 'NON-IMEI';
                        if ($d['imei'] && $d['imei'] !== '-') {
                            $bundles[$groupKey]['imei'][] = $d['imei'];
                            $bundles[$groupKey]['is_hp'] = true;
                        }
                    } else {
                        $grouped[] = $d;
                    }
                }

                foreach ($bundles as $bName => $bData) {
                    $bData['imei'] = implode(', ', array_unique($bData['imei'])) ?: '-';
                    $composition = array_unique($bData['bundle_composition']);
                    $bData['name'] .= ' [' . implode(' + ', $composition) . ']';
                    $grouped[] = $bData;
                }
                $details = $grouped;
            }

            // Update the object with consolidated items
            $stockOut->consolidated_items = $details;
            
            // For backward compatibility with views that use product_names/imeis strings
            $stockOut->product_names = collect($details)->pluck('name')->implode(', ');
            $stockOut->imeis = collect($details)->pluck('imei')->filter(fn($i) => $i !== '-')->implode(', ');
            
            // Unified Recipient Label
            $stockOut->recipient_label = $stockOut->customer_name ?: ($stockOut->receiver_name ?: ($stockOut->shopee_receiver ?: ($stockOut->giveaway_receiver ?: '-')));

            return $stockOut;
        });

        return response()->json($results);
    }

    // Create stock out
    public function store(Request $request)
    {
        // Sanitize inventory_user_id from frontend (e.g. 'system' for non-hp)
        if ($request->inventory_user_id === 'system') {
            $request->merge(['inventory_user_id' => null]);
        }

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
                    'penjualan_store',
                    'shopee',
                    'orderan_online',
                    'bundling',
                    'tukar_unit',
                    'downgrade',
                    'cancel_penjualan',
                    'hilang',
                ])
            ],
            'missing_category' => 'required_if:category,hilang|nullable|string',
            'person_in_charge' => 'required_if:category,hilang|nullable|string',
            'loss_chronology' => 'required_if:category,hilang|nullable|string',
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
            'proof_image' => 'nullable|image|max:20480', // Max 20MB
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
        $salesCategories = ['penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'penjualan_offline', 'shopee', 'orderan_online'];
        if (in_array($request->category, $salesCategories)) {
            // Shopee/Online uses shopee_receiver as customer name
            if ($request->category === 'shopee' || $request->category === 'orderan_online') {
                $rules['shopee_receiver'] = 'required|string|max:255';
                $rules['shopee_phone'] = 'required|string|max:50';
                $rules['shopee_address'] = 'required|string';
            } else {
                // Untuk kategori Penjualan Offline dsb
                $rules['customer_name'] = 'nullable|string|max:255';
                $rules['customer_wa'] = 'nullable|string|max:50';
            }

            $rules['notes'] = 'nullable|string';

            // proof_image always optional
            $rules['proof_image'] = 'nullable|image|max:20480';

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

            $rules['shopee_tracking_no'] = [
                'required_if:category,shopee,orderan_online',
                'nullable',
                'string',
                'max:100',
                Rule::unique('stock_outs', 'shopee_tracking_no')->whereNull('deleted_at')
            ];
        }

        // Giveaway Validation
        if ($request->category === 'giveaway_customer') {
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

        // Event / Sponsorship Validation
        if ($request->category === 'event_sponsorship') {
            $rules['event_receiver'] = 'required|string|max:255';
            $rules['event_phone'] = 'required|string|max:50';
            $rules['event_notes'] = 'nullable|string';
        }

        $messages = [
            'shopee_tracking_no.unique' => 'No. Resi ini sudah pernah digunakan. Mohon cek kembali data Anda.',
        ];

        $request->validate($rules, $messages);

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError) return $pinError;

        DB::beginTransaction();

        try {
            $user = Auth::user();
            if (!$user) {
                throw new \Exception('User tidak terautentikasi.');
            }

            \Log::info("DEBUG STOCK-OUT: Starting for user " . $user->id, [
                'category' => $request->category,
                'has_product_detail_ids' => !empty($request->product_detail_ids),
                'has_non_hp_items' => !empty($request->non_hp_items)
            ]);

            // Resolve Location for Reporting Date
            $userLocation = null;
            try {
                $userLocation = $user->branch ?: ($user->onlineShop ?: null);
            } catch (\Throwable $e) {
                \Log::error("DEBUG STOCK-OUT: Failed to resolve user location: " . $e->getMessage());
            }

            $reportingDate = StockOut::calculateReportingDate(
                $request->category,
                $userLocation
            );

            \Log::info("DEBUG STOCK-OUT: Reporting date resolved: " . $reportingDate);

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
            $nonHpDistMap = []; // Temporary storage for distributor inheritance
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

                    // Ambil semua stok barang ini di lokasi tersebut
                    // Prioritas: Ambil yang jumlahnya paling sedikit dulu agar baris-baris stok kecil di dashboard cepat bersih
                    $inventories = $invQuery->where('quantity', '>', 0)->orderBy('quantity', 'asc')->get();
                    $totalAvailable = $inventories->sum('quantity');

                    if ($totalAvailable < $item['quantity']) {
                        throw new \Exception("Stok tidak cukup untuk produk: {$product->name}. Tersedia: $totalAvailable");
                    }

                    $remainingToDeduct = $item['quantity'];

                    foreach ($inventories as $inventory) {
                        if ($remainingToDeduct <= 0) break;

                        $deductAmount = min($inventory->quantity, $remainingToDeduct);
                        $inventory->decrement('quantity', $deductAmount);
                        $remainingToDeduct -= $deductAmount;
                        
                        // Capture the distributor from the first deducted batch for this product
                        if (!isset($nonHpDistMap[$item['product_id']])) {
                            $distId = $inventory->distributor_id;
                            
                            // If ID is missing, try to find a distributor with a matching name from the latest log
                            if (!$distId) {
                                $lastLog = InventoryLog::where('product_id', $item['product_id'])
                                    ->where('type', 'in')
                                    ->whereNotNull('distributor_id')
                                    ->latest()
                                    ->first();
                                $distId = $lastLog ? $lastLog->distributor_id : null;
                            }
                            
                            if ($distId) {
                                $nonHpDistMap[$item['product_id']] = $distId;
                            }
                        }

                        // Log Transaction for this specific inventory record
                        InventoryLog::create([
                            'product_id' => $item['product_id'],
                            'type' => 'out',
                            'quantity' => $deductAmount,
                            'balance_after' => $inventory->quantity,
                            'description' => "Stock Out ({$request->category})",
                            'reference_id' => 'OUT-' . time() . '-' . $inventory->id,
                            'user_id' => $user->id,
                            'distributor_id' => $distId ?? $inventory->distributor_id,
                            'branch_id' => $user->branch_id ?? null,
                            'warehouse_id' => $user->warehouse_id ?? null,
                            'online_shop_id' => $user->online_shop_id ?? null,
                        ]);
                    }

                    // Log general transaction for the StockOut record will be handled by the loop above
                    // Skip the original single-log/single-deduct logic
                    continue; 
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

                // Fallback to top-level selling_price if no per-item or non-hp prices were calculated
                if ($totalSellingPrice == 0 && $request->has('selling_price')) {
                    $totalSellingPrice = floatval($request->selling_price);
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
                
                // Event
                'event_receiver' => $request->event_receiver,
                'event_phone' => $request->event_phone,
                'event_notes' => $request->event_notes,

                'notes' => $request->notes,
                'non_hp_items' => $request->non_hp_items,
                'sales_account' => $request->sales_account,
                'payment_method_id' => $request->payment_method_id,
                'paid_amount' => $request->paid_amount ?? 0,
                'split_payments' => is_string($request->split_payments) ? json_decode($request->split_payments, true) : $request->split_payments,
                'global_discount_value' => $request->global_discount_value ?? 0,
                'global_discount_type' => $request->global_discount_type ?? 'fixed',
                'total_discount' => $request->total_discount ?? 0,
                'missing_category' => $request->missing_category,
                'person_in_charge' => $request->person_in_charge,
                'loss_chronology' => $request->loss_chronology,
            ]);

            // Pre-generate PDF in the background to speed up WhatsApp sharing later
            if ($stockOut->category === 'penjualan_store') {
                dispatch(function () use ($stockOut) {
                    try {
                        \App\Http\Controllers\WhatsAppShareController::getDriveLink($stockOut->id);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning("Pre-generation failed for StockOut ID {$stockOut->id}: " . $e->getMessage());
                    }
                })->afterResponse();
            }

            // Create StockOutNonHpItem records (Riwayat Detail)
            if ($request->non_hp_items) {
                foreach ($request->non_hp_items as $item) {
                    $prod = Product::find($item['product_id']);
                    if ($prod) {
                        // Use the distributor captured during stock deduction earlier
                        $distId = $nonHpDistMap[$item['product_id']] ?? $prod->distributor_id ?? null;

                        StockOutNonHpItem::create([
                            'stock_out_id' => $stockOut->id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'selling_price' => $item['selling_price'] ?? 0,
                            'item_discount' => $item['item_discount'] ?? 0,
                            'distributed_discount' => $item['distributed_discount'] ?? 0,
                            'received_quantity' => ($request->category === 'pindah_cabang') ? 0 : $item['quantity'],
                            'returned_quantity' => 0,
                            'distributor_id' => $distId, // Captured from inventory
                            'notes' => $item['bundle_name'] ?? null
                        ]);
                    }
                }
            }

            // Bundling logic handled via metadata tags above

            // Attach items and update status
            $newStatus = $this->getStatusByCategory($request->category);

            foreach ($productDetails as $detail) {
                /** @var \App\Models\ProductDetail $detail */
                $hpMeta = $request->hp_items_meta[$detail->id] ?? null;
                
                // Priority: hp_items_meta (explicit) > $detail->selling_price
                $finalSellingPrice = $hpMeta['selling_price'] ?? $detail->selling_price;

                $stockOut->items()->attach($detail->id, [
                    'selling_price' => $finalSellingPrice,
                    'item_discount' => $hpMeta['item_discount'] ?? 0,
                    'distributed_discount' => $hpMeta['distributed_discount'] ?? 0,
                    'distributor_id' => $detail->distributor_id, // Capture HP distributor permanently
                    'notes' => $hpMeta['bundle_name'] ?? null
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

                // Log HP Movement to InventoryLog
                InventoryLog::create([
                    'product_id' => $detail->product_id,
                    'type' => 'out',
                    'quantity' => 1,
                    'balance_after' => 0, // Simplified for HP
                    'description' => strtoupper($request->category) . " (" . ($detail->imei ?? '-') . ")" . ($request->customer_name ? " - " . $request->customer_name : ""),
                    'reference_id' => $stockOut->receipt_id,
                    'user_id' => $user->id,
                    'distributor_id' => $detail->distributor_id,
                    'branch_id' => $user->branch_id ?? null,
                    'warehouse_id' => $user->warehouse_id ?? null,
                    'online_shop_id' => $user->online_shop_id ?? null,
                ]);
            }

            DB::commit();

            // Bust Inventory Cache
            \Illuminate\Support\Facades\Cache::increment('inv_version');

            \Log::info("DEBUG STOCK-OUT: Success! Receipt ID: " . $stockOut->receipt_id);

            return response()->json([
                'message' => 'Stock out successful',
                'id' => $stockOut->id,
                'receipt_id' => $stockOut->receipt_id,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error("DEBUG STOCK-OUT CRASH: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat stock out: ' . $e->getMessage(),
                'debug_info' => [
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile())
                ]
            ], 500);
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
        $query = StockOut::with(['items.product.brandRelation', 'user', 'inventoryUser', 'nonHpDetails.product.brandRelation'])
            ->whereIn('category', ['shopee', 'orderan_online', 'cancel_penjualan']);

        // LOCATION FILTER (ISOLATION)
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner'];
        if ($user && !$user->hasRole($unrestrictedRoles)) {
            $query->whereHas('user', function ($q) use ($user) {
                 $bIds = $user->getAccessibleBranchIds();
                 $wIds = $user->getAccessibleWarehouseIds();
                 $osIds = $user->getAccessibleOnlineShopIds();
                 
                 $q->where(function($sq) use ($bIds, $wIds, $osIds) {
                     if (!empty($bIds)) $sq->orWhereIn('branch_id', $bIds);
                     if (!empty($wIds)) $sq->orWhereIn('warehouse_id', $wIds);
                     if (!empty($osIds)) $sq->orWhereIn('online_shop_id', $osIds);
                 });
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
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        if ($request->date) {
            $d = $request->date;
            if ($user && !$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner'])) {
                $today = $logicalNow->toDateString();
                $sevenDaysAgo = $logicalNow->copy()->subDays(7)->toDateString();
                if ($d < $sevenDaysAgo) {
                    $d = $today;
                }
            }
            $query->whereDate('reporting_date', $d);
        }
        if ($request->has('month') && !empty($request->month) && $request->has('year') && !empty($request->year)) {
            $m = (int) $request->month;
            $y = (int) $request->year;

            if ($user && !$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner'])) {
                $currentMonth = (int) $logicalNow->format('m');
                $currentYear = (int) $logicalNow->format('Y');
                
                $lastMonthTemp = $logicalNow->copy()->subMonth();
                $lastMonth = (int) $lastMonthTemp->format('m');
                $lastMonthYear = (int) $lastMonthTemp->format('Y');

                if ($y < $currentYear) {
                    $m = $currentMonth;
                    $y = $currentYear;
                } elseif ($y == $currentYear) {
                    if ($m < $lastMonth) {
                         $m = $currentMonth;
                    }
                }
            }
            $query->whereMonth('reporting_date', $m);
            $query->whereYear('reporting_date', $y);
        } elseif ($request->has('month') && !empty($request->month)) {
             // Fallback if only month is provided
             $m = (int) $request->month;
             $y = (int) $logicalNow->format('Y');
             $query->whereMonth('reporting_date', $m)->whereYear('reporting_date', $y);
        }

        $history = $query->latest()->paginate(20);

        // Enrich non_hp_items with product names
        $productIds = [];
        foreach ($history->items() as $item) {
            // Priority 1: Use relational data (NEW system)
            if ($item->nonHpDetails && $item->nonHpDetails->count() > 0) {
                $itemConverted = [];
                foreach ($item->nonHpDetails as $detail) {
                    $itemConverted[] = [
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product?->name ?? 'Unknown',
                        'product_brand' => $detail->product?->brand ?? $detail->product?->brandRelation?->name ?? '-',
                        'product_sku' => $detail->product?->sku ?? '-',
                        'quantity' => $detail->quantity,
                        'selling_price' => $detail->selling_price,
                    ];
                }
                $item->non_hp_items = $itemConverted;
            } else if ($item->non_hp_items) {
                // Priority 2: Collect IDs for legacy JSON data enrichment
                foreach ($item->non_hp_items as $nonHpItem) {
                    if (isset($nonHpItem['product_id'])) {
                        $productIds[] = $nonHpItem['product_id'];
                    }
                }
            }
        }

        if (!empty($productIds)) {
            $products = Product::whereIn('id', array_unique($productIds))->get()->keyBy('id');

            foreach ($history->items() as $item) {
                // Only enrich if it's legacy JSON data and wasn't already enriched by relational logic
                if ($item->non_hp_items && (!$item->nonHpDetails || $item->nonHpDetails->count() === 0)) {
                    $enrichedItems = [];
                    foreach ($item->non_hp_items as $nonHpItem) {
                        $prod = $products[$nonHpItem['product_id']] ?? null;
                        $nonHpItem['product_name'] = $prod?->name ?? 'Unknown Product';
                        $nonHpItem['product_brand'] = $prod?->brand ?? $prod?->brandRelation?->name ?? '-';
                        $nonHpItem['product_sku'] = $prod?->sku ?? '-';
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
        // NEW: Integrated Expedition Tracking Hijack
        if ($request->has('noresi')) {
            $noResi = $request->noresi;
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(10)->get("https://cekresi.com/?noresi=" . $noResi);
                $html = $response->body();
                
                // Clean HTML
                $html = str_replace('<head>', '<head><base href="https://cekresi.com/"><style>header,footer,nav,.navbar,.ad-section,.sidebar{display:none!important;}body{background:white!important;}</style>', $html);
                
                return response()->json(['tracking_html' => $html]);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Gagal melacak: ' . $e->getMessage()], 500);
            }
        }

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
            $stockOuts = StockOut::with(['items.product', 'items.distributor', 'user', 'inventoryUser', 'destinationBranch', 'destination', 'confirmedBy'])
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
                    'notes' => $i->pivot?->notes
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
                            'notes' => $nhp['notes'] ?? null
                        ];
                    }
                }

                // Consolidate Bundles if applicable
                if ($out->is_bundle) {
                    $grouped = [];
                    $bundles = [];
                    $fallbackBundleName = $out->bundle_description ?: 'Paket Bundling';
                    
                    // Historical component-aware matching logic
                    $bundleComponents = [];
                    if ($fallbackBundleName) {
                        $descPart = str_replace('Paket Bundling:', '', $fallbackBundleName);
                        $bundleComponents = array_map('trim', explode(',', $descPart));
                    }

                    foreach ($mergedItems as $item) {
                        $bundleTag = $item['notes'] ?? null;
                        $cleanName = $item['product_name'] ?? '';
                        
                        $isPartOfBundle = false;
                        $groupKey = $bundleTag;

                        if ($bundleTag && ($bundleTag === $fallbackBundleName || str_contains(strtolower($bundleTag), 'bundle') || str_contains(strtolower($bundleTag), 'paket'))) {
                            $isPartOfBundle = true;
                        } else if (!$bundleTag && !empty($bundleComponents)) {
                            foreach ($bundleComponents as $idx => $comp) {
                                if (!empty($comp) && (str_contains(strtolower($cleanName), strtolower($comp)) || str_contains(strtolower($comp), strtolower($cleanName)))) {
                                    $isPartOfBundle = true;
                                    $groupKey = $fallbackBundleName;
                                    unset($bundleComponents[$idx]);
                                    break;
                                }
                            }
                        }

                        if ($isPartOfBundle && $groupKey) {
                            if (!isset($bundles[$groupKey])) {
                                $bundles[$groupKey] = [
                                    'product_name' => '📦 ' . $groupKey,
                                    'quantity' => 0,
                                    'type' => 'bundle',
                                    'imei' => [],
                                    'bundle_composition' => []
                                ];
                            }
                            $bundles[$groupKey]['quantity'] += $item['quantity'];
                            $bundles[$groupKey]['bundle_composition'][] = $item['type'] === 'hp' ? 'IMEI' : 'NON-IMEI';
                            if (isset($item['imei']) && $item['imei'] !== '-') {
                                $bundles[$groupKey]['imei'][] = $item['imei'];
                            }
                        } else {
                            $grouped[] = $item;
                        }
                    }

                    foreach ($bundles as $bName => $bData) {
                        $composition = array_unique($bData['bundle_composition']);
                        $bData['product_name'] .= ' [' . implode(' + ', $composition) . ']';
                        $bData['imei'] = implode(', ', array_unique($bData['imei'])) ?: '-';
                        $grouped[] = $bData;
                    }
                    $mergedItems = $grouped;
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
                    'status' => ($out->category === 'pindah_cabang' && $out->status === 'rejected') ? 'pending' : $out->status,
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

                // Event 2b: The REJECTION (if rejected transfer)
                if ($out->category === 'pindah_cabang' && $out->status === 'rejected' && $out->confirmed_at) {
                    $allEvents[] = [
                        'type' => 'stock_out',
                        'sub_type' => 'rejection',
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
                        'processed_by' => $out->confirmedBy?->name ?? 'Unknown',
                        'status' => 'rejected', // Red "Ditolak" badge
                        'created_at' => $out->confirmed_at->toDateTimeString(),
                        'timestamp' => $out->confirmed_at->timestamp,
                        'is_rejection' => true,
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

                // Event 4: RETURN TO SENDER / TERIMA BALIK TRANSFER (if transfer rejected and received back by sender)
                if ($out->category === 'pindah_cabang') {
                    $item = $out->items->first(function ($i) use ($query, $out) {
                        if ($query === $out->receipt_id) return true;
                        return stripos($i->imei, $query) !== false;
                    });

                    if ($item) {
                        $returnLog = \App\Models\InventoryLog::with('user')
                            ->where('reference_id', (string)$item->id)
                            ->where(function ($q) use ($out) {
                                $q->where('description', 'like', "Terima Balik Transfer (Resi: {$out->receipt_id})%")
                                  ->orWhere('description', 'like', "Transfer Ditolak/Retur dari #{$out->receipt_id}%");
                            })
                            ->first();

                        if ($returnLog) {
                            $placementName = 'Unknown Location';
                            if ($returnLog->branch_id) {
                                $placementName = \App\Models\Branch::find($returnLog->branch_id)?->name;
                            } elseif ($returnLog->warehouse_id) {
                                $placementName = \App\Models\Warehouse::find($returnLog->warehouse_id)?->name;
                            } elseif ($returnLog->online_shop_id) {
                                $placementName = \App\Models\OnlineShop::find($returnLog->online_shop_id)?->name;
                            } elseif ($returnLog->distributor_id) {
                                $placementName = \App\Models\Distributor::find($returnLog->distributor_id)?->name;
                            }

                            $allEvents[] = [
                                'type' => 'stock_in',
                                'sub_type' => 'return_transfer_arrival',
                                'id' => $out->receipt_id,
                                'imei' => $item->imei,
                                'product_name' => $item->product?->name ?? 'Unknown',
                                'status' => 'available',
                                'placement_type' => $item->placement_type,
                                'placement_id' => $item->placement_id,
                                'placement_name' => $placementName ?? 'Original Location',
                                'created_at' => $returnLog->created_at->toDateTimeString(),
                                'timestamp' => $returnLog->created_at->timestamp,
                                'input_by' => $returnLog->user?->name ?? 'Unknown',
                                'condition' => $item->condition ?? '-',
                                'selling_price' => $item->selling_price ?? 0,
                                'distributor' => $item->distributor?->name ?? '-',
                                'storage' => $item->storage ?? '-',
                                'rejected_by' => $out->confirmedBy?->name ?? 'Unknown',
                                'is_arrival' => true,
                                'is_return_transfer' => true,
                            ];
                        }
                    }
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

        $query = StockOut::with(['items.product.brandRelation', 'items.distributor', 'nonHpItems.product.brandRelation', 'nonHpItems.distributor', 'user.branch', 'user.warehouse', 'user.onlineShop', 'inventoryUser.branch', 'inventoryUser.warehouse', 'inventoryUser.onlineShop', 'destinationBranch', 'destination'])
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

            // Distributor
            $distributorIds = $user->getAccessibleDistributorIds();
            if (!empty($distributorIds)) {
                $q->orWhere(function ($sub) use ($distributorIds) {
                    $sub->where('destination_type', 'distributor')
                        ->whereIn('destination_id', $distributorIds);
                });
                $hasFilter = true;
            }

            if (!$hasFilter) {
                if ($user->hasRole(['super_admin', 'owner', 'admin_produk', 'analist'])) {
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

    // List outgoing transfers sent FROM current user's location
    public function indexOutgoing()
    {
        $user = Auth::user();
        if (!$user)
            return response()->json(['data' => []]);

        $query = StockOut::with(['items.product.brandRelation', 'items.distributor', 'nonHpItems.product.brandRelation', 'nonHpItems.distributor', 'user.branch', 'user.warehouse', 'user.onlineShop', 'inventoryUser.branch', 'inventoryUser.warehouse', 'inventoryUser.onlineShop', 'destinationBranch', 'destination'])
            ->where('category', 'pindah_cabang')
            ->where('status', 'pending');

        // Filter by Source (Created by user in the same location)
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner'];
        if (!$user->hasRole($unrestrictedRoles)) {
            $query->whereHas('user', function ($q) use ($user) {
                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $onlineShopIds = $user->getAccessibleOnlineShopIds();
                $distributorIds = $user->getAccessibleDistributorIds();
                
                $hasFilter = false;
                $q->where(function ($sub) use ($branchIds, $warehouseIds, $onlineShopIds, $distributorIds, &$hasFilter) {
                    if (!empty($branchIds)) {
                        $sub->orWhereIn('branch_id', $branchIds);
                        $hasFilter = true;
                    }
                    if (!empty($warehouseIds)) {
                        $sub->orWhereIn('warehouse_id', $warehouseIds);
                        $hasFilter = true;
                    }
                    if (!empty($onlineShopIds)) {
                        $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        $hasFilter = true;
                    }
                    if (!empty($distributorIds)) {
                        $sub->orWhereIn('distributor_id', $distributorIds);
                        $hasFilter = true;
                    }
                });
                
                if (!$hasFilter) {
                    $q->where('id', $user->id);
                }
            });
        }

        $transfers = $query->latest()->get();

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
                }
                $transfer->non_hp_items = $nonHpItems;
            }
        }

        return response()->json(['data' => $transfers]);
    }

    // Confirm Incoming Transfer
    public function confirm(Request $request, $id)
    {
        if ($request->inventory_user_id === 'system') {
            $request->merge(['inventory_user_id' => null]);
        }
        $request->validate([
            'items' => 'nullable|array', // List of Accepted Item IDs (HP)
            'items_rejection' => 'nullable|array', // { itemId: note }
            'non_hp_items' => 'nullable|array', // List of Accepted Quantities
            'non_hp_rejection_notes' => 'nullable|array', // { nonHpItemId: note }
            'inventory_user_id' => 'sometimes|nullable|exists:users,id',
            'transaction_pin' => 'nullable|string|size:4'
        ]);

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request, $request->inventory_user_id);
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
            } elseif ($destUser->distributor_id) {
                $destPlacementType = 'distributor';
                $destPlacementId = $destUser->distributor_id;
            }

            // 1. Process HP Items
            $acceptedItemIds = $request->items ?? [];
            $rejectionNotes = $request->items_rejection ?? [];
            foreach ($stockOut->items as $item) {
                $status = in_array($item->id, $acceptedItemIds) ? 'confirmed' : 'rejected';
                $notes = $rejectionNotes[$item->id] ?? null;

                // Update Pivot
                DB::table('stock_out_items')
                    ->where('stock_out_id', $stockOut->id)
                    ->where('product_detail_id', $item->id)
                    ->update([
                        'status' => $status,
                        'notes' => $notes
                    ]);

                if ($status === 'confirmed') {
                    // Accepted: Status Available, Placement Updated to Receiver's Location
                    $item->status = 'available';
                    $item->user_id = $confirmingUserId;
                    $item->placement_type = $destPlacementType;
                    $item->placement_id = $destPlacementId;
                    $item->created_at = now();
                    $item->save();

                    // CREATE INVENTORY LOG AS "IN" FOR THE DESTINATION
                    InventoryLog::create([
                        'product_id' => $item->product_id,
                        'user_id' => $confirmingUserId,
                        'branch_id' => ($destPlacementType == 'branch') ? $destPlacementId : null,
                        'warehouse_id' => ($destPlacementType == 'warehouse') ? $destPlacementId : null,
                        'online_shop_id' => ($destPlacementType == 'online_shop') ? $destPlacementId : null,
                        'distributor_id' => ($destPlacementType == 'distributor') ? $destPlacementId : null,
                        'type' => 'in',
                        'quantity' => 1,
                        'balance_after' => 1,
                        'description' => "Pindah Cabang Masuk (Resi: {$stockOut->receipt_id}) (" . ($item->imei ?? $item->p_code) . ")",
                        'reference_id' => (string)$item->id,
                    ]);
                    
                    \Log::info("DEBUG: PHP Log created for accepted HP #{$item->id} in Resi {$stockOut->receipt_id}");
                } else {
                    // Rejected: Set to 'returning' (not active stock yet)
                    $sender = $stockOut->user;
                    $senderLocationId = $sender->branch_id ?? $sender->warehouse_id ?? $sender->online_shop_id ?? $sender->distributor_id;
                    $senderType = $sender->branch_id ? 'branch' : ($sender->warehouse_id ? 'warehouse' : ($sender->online_shop_id ? 'online_shop' : 'distributor'));
                    
                    $item->update([
                        'status' => 'returning',
                        'placement_type' => $senderType,
                        'placement_id' => $senderLocationId,
                        'user_id' => $sender->id
                    ]);
                    
                    \Log::info("DEBUG: HP #{$item->id} marked as returning in Resi {$stockOut->receipt_id}");
                }
            }

            // 2. Process Non-HP Items
            if ($request->non_hp_items) {
                $nonHpRejectionNotes = $request->non_hp_rejection_notes ?? [];
                foreach ($request->non_hp_items as $itemId => $acceptedQty) {
                    // $itemId is the ID of stock_out_non_hp_items record
                    $record = StockOutNonHpItem::find($itemId);

                    if ($record && $record->stock_out_id == $stockOut->id) {
                        $record->update([
                            'received_quantity' => $acceptedQty,
                            'returned_quantity' => max(0, $record->quantity - $acceptedQty),
                            'notes' => $nonHpRejectionNotes[$itemId] ?? null
                        ]);

                        // Add to Inventory at Destination
                        $locationField = $destUser->branch_id ? 'branch_id' : ($destUser->warehouse_id ? 'warehouse_id' : 'online_shop_id');
                        $locationId = $destUser->branch_id ?? $destUser->warehouse_id ?? $destUser->online_shop_id;
                        $placementType = $destPlacementType;

                        if ($acceptedQty > 0) {
                            $inventory = Inventory::firstOrCreate(
                                [
                                    'product_id' => $record->product_id,
                                    'placement_type' => $placementType,
                                    'placement_id' => $locationId,
                                    'user_id' => $confirmingUserId,
                                ],
                                ['quantity' => 0]
                            );
                            $inventory->increment('quantity', $acceptedQty);

                            // Log In
                            InventoryLog::create([
                                'product_id' => $record->product_id,
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
                            $record->update(['returned_quantity' => $rejectedQty]);
                            
                            $sender = $stockOut->user;
                            $senderUserId = $sender->id;
                            $senderLocationId = $sender->branch_id ?? $sender->warehouse_id ?? $sender->online_shop_id;
                            $senderType = $sender->branch_id ? 'branch' : ($sender->warehouse_id ? 'warehouse' : 'online_shop');

                            $senderInv = Inventory::firstOrCreate(
                                [
                                    'product_id' => $record->product_id,
                                    'placement_type' => $senderType,
                                    'placement_id' => $senderLocationId,
                                    'user_id' => $senderUserId
                                ],
                                ['quantity' => 0]
                            );
                            $senderInv->increment('quantity', $rejectedQty);
                        }
                    }
                }
            }

            // Determine Final Status
            $totalAccepted = count($acceptedItemIds);
            if ($request->non_hp_items) {
                foreach ($request->non_hp_items as $qty) {
                    $totalAccepted += $qty;
                }
            }

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
    
    // Update Expedition Info for a Transfer
    public function updateExpedition(Request $request, $id)
    {
        $request->validate([
            'expedition_name' => 'required|string',
            'expedition_tracking_no' => 'required|string',
            'expedition_date' => 'required|date',
        ]);

        try {
            $stockOut = StockOut::findOrFail($id);
            
            // Only allow updates for transfers (pindah_cabang)
            if ($stockOut->category !== 'pindah_cabang') {
                return response()->json(['message' => 'Hanya transfer antar cabang yang dapat ditambahkan ekspedisi.'], 422);
            }

            $stockOut->update([
                'expedition_name' => $request->expedition_name,
                'expedition_tracking_no' => $request->expedition_tracking_no,
                'expedition_date' => $request->expedition_date,
            ]);

            return response()->json([
                'message' => 'Informasi ekspedisi berhasil diperbarui.',
                'data' => $stockOut
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // Fetch real-time tracking from external API
    public function trackExpedition(Request $request)
    {
        $request->validate([
            'courier' => 'required|string',
            'awb' => 'required|string',
        ]);

        $originalCourier = $request->courier;
        $courier = strtolower($request->courier);
        $awb = $request->awb;
        
        // --- 1. GET PROVIDER KEYS ---
        $binderKeys = env('BINDERBYTE_API_KEY');
        $biteshipKey = env('BITESHIP_API_KEY');
        
        // Manual Fallback for Cached Environment
        if (!$binderKeys) {
            try {
                $envContent = file_get_contents(base_path('.env'));
                if (preg_match('/BINDERBYTE_API_KEY=(.*)/', $envContent, $matches)) $binderKeys = trim($matches[1], "\"' \n\r\t");
                if (preg_match('/BITESHIP_API_KEY=(.*)/', $envContent, $matches)) $biteshipKey = trim($matches[1], "\"' \n\r\t");
            } catch (\Exception $e) {}
        }

        // --- 1. TRY BINDERBYTE FIRST (PRIMARY - COST FREE) ---
        $binderErrors = [];
        if ($binderKeys) {
            $courierMap = [
                'jne' => 'jne',
                'pos indonesia' => 'pos', 'pos' => 'pos',
                'j&t' => 'jnt', 'jnt' => 'jnt',
                'j&t cargo' => 'jnt_cargo', 'jnt cargo' => 'jnt_cargo',
                'sicepat' => 'sicepat',
                'tiki' => 'tiki',
                'anteraja' => 'anteraja',
                'wahana' => 'wahana',
                'ninja' => 'ninja', 'ninja xpress' => 'ninja',
                'lion' => 'lion', 'lion parcel' => 'lion',
                'shopee' => 'spx', 'shopee express' => 'spx', 'spx' => 'spx',
                'id express' => 'ide', 'ide' => 'ide',
                'indah' => 'indah_cargo', 'indah cargo' => 'indah_cargo',
                'sap' => 'sap'
            ];
            $courierSlug = $courierMap[trim(strtolower($courier))] ?? $courier;
            
            // Combine ENV keys and the new key provided
            $keys = array_unique(array_filter(explode(',', $binderKeys . ',f8000a7fa7be89bb3796d9a753d248c2d1c0ac04ac994b7cb860b31240a730d1')));
            
            foreach ($keys as $key) {
                $key = trim($key);
                if (empty($key)) continue;

                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(15)->get("https://api.binderbyte.com/v1/track", [
                        'api_key' => $key, 'courier' => $courierSlug, 'awb' => $awb
                    ]);

                    $data = $response->json();
                    if ($response->successful() && isset($data['status']) && $data['status'] == 200) {
                        return response()->json(['success' => true, 'provider' => 'binderbyte', 'data' => $data['data']]);
                    }
                    $binderErrors[] = ($data['message'] ?? 'Error');
                } catch (\Exception $e) {
                    $binderErrors[] = "Timeout/Down";
                }
            }
        }

        // --- 2. TRY BITESHIP AS FALLBACK (PAID - RELIABLE) ---
        // Jika Binderbyte gagal, terpaksa gunakan ini agar user tetap dapat data (terutama POS)
        if ($biteshipKey) {
            try {
                $biteshipMap = [
                    'jne' => 'jne', 'j&t' => 'jnt', 'jnt' => 'jnt', 'sicepat' => 'sicepat', 'tiki' => 'tiki',
                    'anteraja' => 'anteraja', 'wahana' => 'wahana', 'ninja' => 'ninja', 'shopee' => 'shopee',
                    'shopee express' => 'shopee', 'spx' => 'shopee', 'lion' => 'lion', 'id express' => 'ide',
                    'pos' => 'pos', 'pos indonesia' => 'pos', 'pcp' => 'pcp', 'jet' => 'jet', 'sap' => 'sap'
                ];
                $bsSlug = $biteshipMap[trim(strtolower($courier))] ?? $courier;

                $response = \Illuminate\Support\Facades\Http::timeout(20)
                    ->withHeaders(['Authorization' => 'Bearer ' . $biteshipKey])
                    ->get("https://api.biteship.com/v1/trackings/{$awb}/couriers/{$bsSlug}");

                $bsData = $response->json();
                if ($response->successful() && isset($bsData['success']) && $bsData['success']) {
                    // Mapper status untuk BiteShip
                    $statusIndo = [
                        'allocated' => 'Kurir Dialokasikan',
                        'picking_up' => 'Proses Penjemputan',
                        'picked_up' => 'Berhasil Dijemput',
                        'dropping_off' => 'Sedang Diantar',
                        'delivered' => 'Diterima',
                        'cancelled' => 'Dibatalkan',
                        'on_hold' => 'Tertahan/Hold',
                        'returned' => 'Dikembalikan',
                    ];

                    $history = array_map(function($h) use ($statusIndo) {
                        $hTime = $h['updated_at'] ?? $h['time'] ?? date('Y-m-d H:i:s');
                        $statusRaw = strtolower($h['status'] ?? '');
                        
                        $note = $h['note'] ?? 'Status Update';
                        // Terjemahkan note umum ke Indonesia agar UI tetap premium
                        $replacements = [
                            'Item is on the way to customer.' => 'Pesanan dalam proses antar ke tujuan.',
                            'Your shipment is on hold at the moment.' => 'Pesanan sedang dalam pengawasan/tertahan.',
                            'Courier is on the way to pick up item.' => 'Kurir sedang dalam perjalanan menjemput paket.',
                        ];
                        $note = $replacements[$note] ?? $note;

                        return [
                            'date' => date('Y-m-d H:i:s', strtotime($hTime)),
                            'desc' => $note,
                            'location' => strtoupper($statusIndo[$statusRaw] ?? $statusRaw ?? 'TRANSIT')
                        ];
                    }, $bsData['history'] ?? []);

                    // Terbaru di atas
                    $history = array_reverse($history);

                    return response()->json([
                        'success' => true,
                        'provider' => 'biteship',
                        'data' => [
                            'summary' => [
                                'awb' => $bsData['waybill_id'],
                                'courier' => strtoupper($bsData['courier']['company']),
                                'status' => strtoupper($statusIndo[strtolower($bsData['status'])] ?? $bsData['status']),
                                'date' => $bsData['updated_at'] ?? ''
                            ],
                            'detail' => [
                                'origin' => $bsData['origin']['city'] ?? 'ORIGIN',
                                'destination' => $bsData['destination']['city'] ?? 'DESTINATION',
                                'shipper' => $bsData['origin']['contact_name'] ?? 'PENGIRIM',
                                'receiver' => $bsData['destination']['contact_name'] ?? 'PENERIMA'
                            ],
                            'history' => $history
                        ]
                    ]);
                }
            } catch (\Exception $e) {}
        }

        return response()->json([
            'success' => false,
            'message' => "Seluruh server pelacakan (Binderbyte & BiteShip) tidak merespon resi ini. Mohon cek berkala di web resmi " . strtoupper($courier)
        ], 422);
    }

    // History of Transfers (Incoming and Outgoing)
    public function historyIncoming(Request $request)
    {
        $user = Auth::user();
        if (!$user)
            return response()->json(['message' => 'Unauthorized'], 401);

        $type = $request->query('type'); // 'outgoing' or 'incoming'

        $query = StockOut::with(['items.product.brandRelation', 'items.distributor', 'nonHpItems.product.brandRelation', 'nonHpItems.distributor', 'user.branch', 'user.warehouse', 'user.onlineShop', 'inventoryUser.branch', 'inventoryUser.warehouse', 'inventoryUser.onlineShop', 'destinationBranch', 'destination', 'confirmedBy', 'branch', 'onlineShop', 'warehouse'])
            ->where('category', 'pindah_cabang');

        if ($type === 'outgoing') {
            $query->where('status', 'pending');
        } elseif ($type === 'failed') {
            $query->where(function ($sub) {
                $sub->whereHas('items', function ($q) {
                    $q->whereIn('stock_out_items.status', ['rejected', 'returned']);
                })
                ->orWhereHas('nonHpItems', function ($q) {
                    $q->where('received_quantity', '<', \Illuminate\Support\Facades\DB::raw('quantity'));
                });
            });
        } else {
            $query->whereIn('status', ['received', 'rejected']);
        }

        // Filter by Destination or Source
        $query->where(function ($q) use ($user, $type) {
            if ($type === 'outgoing' || $type === 'failed') {
                $hasFilter = false;

                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $onlineShopIds = $user->getAccessibleOnlineShopIds();
                $distributorIds = $user->getAccessibleDistributorIds();

                if (!empty($branchIds)) {
                    $hasFilter = true;
                    $q->orWhereHas('user', function ($sub) use ($branchIds) {
                        $sub->whereIn('branch_id', $branchIds);
                    });
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

                $distributorIds = $user->getAccessibleDistributorIds();
                if (!empty($distributorIds)) {
                    $q->orWhereHas('user', function ($sub) use ($distributorIds) {
                        $sub->whereIn('distributor_id', $distributorIds);
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

                // Distributor
                $distributorIds = $user->getAccessibleDistributorIds();
                if (!empty($distributorIds)) {
                    $q->orWhere(function ($sub) use ($distributorIds) {
                        $sub->where('destination_type', 'distributor')
                            ->whereIn('destination_id', $distributorIds);
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

        if ($type === 'outgoing') {
            $transfers = $query->latest()->paginate(20);
        } else {
            $transfers = $query->orderByDesc('confirmed_at')->paginate(20);
        }

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

    /**
     * Cancel a sale and restore stock.
     * Restricted to transactions within the last 5 days as per requirements.
     */
    public function cancel(Request $request, $id)
    {
        if ($request->inventory_user_id === 'system') {
            $request->merge(['inventory_user_id' => null]);
        }
        $request->validate([
            'reason' => 'nullable|string|max:500',
            'inventory_user_id' => 'required|exists:users,id',
            'transaction_pin' => 'required|string|size:4'
        ]);

        // PIN Verification using Trait
        // This trait handles checking if the user has a PIN, 
        // and if it matches. It also handles generic permission checks.
        $pinError = $this->verifyPin($request, $request->inventory_user_id);
        if ($pinError) return $pinError;

        DB::beginTransaction();
        try {
            // Load items and nonHpDetails (linked to stock_out_items)
            $stockOut = StockOut::with(['items', 'nonHpDetails', 'user'])->findOrFail($id);
            $receiptId = $stockOut->receipt_id;

            // 1. Restriction: Only allow cancellations for the last 5 reporting days
            $reportingDate = \Carbon\Carbon::parse($stockOut->reporting_date);
            $fiveDaysAgo = now()->subDays(5)->startOfDay();

            if ($reportingDate->lt($fiveDaysAgo) && !Auth::user()->hasRole('super_admin')) {
                throw new \Exception('Hanya penjualan dalam 5 hari terakhir yang dapat dibatalkan.');
            }

            if ($stockOut->category === 'cancel_penjualan' || $stockOut->cancelled_at) {
                throw new \Exception('Transaksi ini sudah dibatalkan sebelumnya.');
            }

            $user = $stockOut->user; // Source user/location record

            // --- A. Handle HP Items (ProductDetail) ---
            
            // 1. Restore OUTGOING Items or Remove INCOMING Items attached to StockOut
            foreach ($stockOut->items as $item) {
                if ($stockOut->category === 'angkat_barang') {
                    // For Angkat Barang, the item in StockOut is the one we RECEIVED. Remove it.
                    $item->forceDelete();
                } else {
                    // For normal sales/exchanges, the item in StockOut is the one we SOLD. Restore it.
                    $item->update(['status' => 'available']);
                }
            }

            // 2. Cleanup INCOMING Items for Exchanges/Refunds (Those not in stock_out_items but linked via transaction models)
            $incomingHP = \App\Models\ProductDetail::where(function($q) use ($receiptId) {
                $q->where('notes', 'like', "%Masuk dari %: $receiptId%")
                  ->orWhereHas('unitExchange', function($sq) use ($receiptId) { $sq->where('receipt_id', $receiptId); })
                  ->orWhereHas('tukarTambah', function($sq) use ($receiptId) { $sq->where('receipt_id', $receiptId); })
                  ->orWhereHas('downgrade', function($sq) use ($receiptId) { $sq->where('receipt_id', $receiptId); })
                  ->orWhereHas('tradeIn', function($sq) use ($receiptId) { $sq->where('receipt_id', $receiptId); })
                  ->orWhereHas('refund', function($sq) use ($receiptId) { $sq->where('receipt_id', $receiptId); });
            })->get();

            foreach ($incomingHP as $inc) {
                // Delete the IN log to keep history clean
                \App\Models\InventoryLog::where('product_id', $inc->product_id)
                    ->where('reference_id', 'like', "%$receiptId%")
                    ->delete();
                $inc->forceDelete();
            }

            // --- B. Handle Non-HP Items ---
            foreach ($stockOut->nonHpDetails as $detail) {
                $invQuery = Inventory::where('product_id', $detail->product_id);

                if ($user->branch_id) {
                    $invQuery->where('placement_type', 'branch')->where('placement_id', $user->branch_id);
                } elseif ($user->warehouse_id) {
                    $invQuery->where('placement_type', 'warehouse')->where('placement_id', $user->warehouse_id);
                } elseif ($user->online_shop_id) {
                    $invQuery->where('placement_type', 'online_shop')->where('placement_id', $user->online_shop_id);
                }

                $inventory = $invQuery->first();
                
                if ($inventory) {
                    if ($stockOut->category === 'angkat_barang' || $stockOut->category === 'refund') {
                        // We received this, now we remove it
                        $inventory->decrement('quantity', $detail->quantity);
                        
                        // Log the removal
                        $logLabel = $stockOut->category === 'refund' ? 'Refund' : 'Angkat Barang';
                        InventoryLog::create([
                            'product_id' => $detail->product_id,
                            'type' => 'out',
                            'quantity' => $detail->quantity,
                            'balance_after' => $inventory->quantity,
                            'description' => "Pembatalan $logLabel (Ref: $receiptId)",
                            'user_id' => $request->inventory_user_id,
                            'distributor_id' => $inventory->distributor_id,
                            'branch_id' => $user->branch_id,
                            'warehouse_id' => $user->warehouse_id,
                            'online_shop_id' => $user->online_shop_id
                        ]);
                    } else {
                        // Normal sale, restore stock
                        $inventory->increment('quantity', $detail->quantity);
                        
                        InventoryLog::create([
                            'product_id' => $detail->product_id,
                            'type' => 'in',
                            'quantity' => $detail->quantity,
                            'balance_after' => $inventory->quantity,
                            'description' => "Pembatalan Penjualan (Ref: $receiptId)",
                            'user_id' => $request->inventory_user_id,
                            'distributor_id' => $inventory->distributor_id,
                            'branch_id' => $user->branch_id,
                            'warehouse_id' => $user->warehouse_id,
                            'online_shop_id' => $user->online_shop_id
                        ]);
                    }
                }
            }

            // 4. Update the StockOut record
            $stockOut->update([
                'category' => 'cancel_penjualan',
                'cancelled_at' => now(),
                'cancelled_by' => $request->inventory_user_id,
                'cancel_reason' => $request->reason ?? 'Dibatalkan oleh user',
                'status' => 'cancelled'
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Penjualan berhasil dibatalkan dan stok dikembalikan.',
                'data' => $stockOut
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
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
    // Check for duplicate tracking number
    public function checkResi(Request $request)
    {
        $resi = $request->resi;
        if (!$resi) return response()->json(['exists' => false]);
        
        $exists = StockOut::where('shopee_tracking_no', $resi)
            ->whereNull('deleted_at')
            ->exists();
            
        return response()->json(['exists' => $exists]);
    }

    /**
     * Proxy tracking to bypass Iframe restrictions
     */
    public function proxyTracking(Request $request)
    {
        $noResi = $request->query('nums');
        if (!$noResi) return response('Nomor resi tidak ditemukan', 400);

        try {
            // Using Http with timeout and no follow redirects to avoid loops
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get("https://cekresi.com/?noresi=" . $noResi);
            
            if (!$response->successful()) {
                return response('Gagal mengambil data dari CekResi', 502);
            }

            $html = $response->body();

            // Force BASE HREF for all relative assets
            $html = str_replace('<head>', '<head><base href="https://cekresi.com/">', $html);
            
            // Inject CSS to clean up the UI
            $cleanCss = '<style>
                header, footer, nav, .navbar, .ad-section, .sidebar, .breadcrumb, .footer-section { display: none !important; }
                body { padding: 0 !important; margin: 0 !important; background: transparent !important; }
                .container { width: 100% !important; max-width: 100% !important; padding: 10px !important; }
            </style>';
            
            $html = str_replace('</head>', $cleanCss . '</head>', $html);

            return response($html)->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            return response('Error: ' . $e->getMessage(), 500);
        }
    }
}
