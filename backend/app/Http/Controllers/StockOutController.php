<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use App\Models\ProductDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\StockOutNonHpItem;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\Distributor;
use App\Traits\VerifiesPin;

class StockOutController extends Controller
{
    use VerifiesPin;
    // List all stock outs
    public function index(Request $request)
    {
        /** @var \App\Models\User|null $user */
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
            $query->where(function ($q) {
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

                $q->where(function ($sq) use ($bIds, $wIds, $osIds) {
                    if (!empty($bIds)) $sq->orWhereIn('branch_id', $bIds);
                    if (!empty($wIds)) $sq->orWhereIn('warehouse_id', $wIds);
                    if (!empty($osIds)) $sq->orWhereIn('online_shop_id', $osIds);
                });
            });
        }

        // AUDIT BRANCH FILTER
        if ($request->branch_id && $user->hasAnyRole(array_merge($unrestrictedRoles, ['audit', 'leader']))) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        // AUDIT ONLINE SHOP FILTER
        if ($request->online_shop_id && $user->hasAnyRole(array_merge($unrestrictedRoles, ['audit', 'leader']))) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('online_shop_id', $request->online_shop_id);
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
                $start = \Carbon\Carbon::create($y, $m, 1)->startOfMonth()->startOfDay()->toDateTimeString();
                $end = \Carbon\Carbon::create($y, $m, 1)->endOfMonth()->endOfDay()->toDateTimeString();
                $query->whereBetween('reporting_date', [$start, $end]);
            } elseif ($request->start_date && $request->end_date) {
                $query->whereBetween('reporting_date', [$request->start_date, $request->end_date]);
            }
        }


        // Filter by Type (HP vs Non-HP)
        if ($request->type === 'hp') {
            $query->whereHas('items');
        } elseif ($request->type === 'non-hp') {
            $query->where(function ($q) {
                $q->whereHas('nonHpDetails')
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('non_hp_items')
                            ->where('non_hp_items', 'not like', '[]')
                            ->where('non_hp_items', 'not like', '{}')
                            ->where('non_hp_items', 'not like', '""');
                    });
            });
        }

        $results = $query->with(['items.product', 'items.distributor', 'nonHpDetails.product', 'nonHpDetails.distributor', 'user.branch', 'user.onlineShop', 'inventoryUser.branch', 'inventoryUser.onlineShop', 'branch', 'onlineShop', 'destination', 'destinationBranch'])
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
            $stockOut->recipient_label = $stockOut->customer_name ?: ($stockOut->receiver_name ?: ($stockOut->ba_name ?: ($stockOut->event_receiver ?: ($stockOut->shopee_receiver ?: ($stockOut->giveaway_receiver ?: ($stockOut->notes ?: ($stockOut->sub_category ?: '-')))))));

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
                    'keluar',
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
            $rules['payment_proof_image'] = 'nullable|image|max:20480';

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

        // Validasi khusus: Metode Pembayaran wajib dipilih untuk kategori penjualan
        if (in_array($request->category, ['penjualan_store', 'penjualan_offline', 'bundling'])) {
            $hasPayment = false;
            if ($request->payment_method_id) {
                $hasPayment = true;
            }

            // Cek juga apabila menggunakan sistem split payment
            if ($request->split_payments) {
                $splits = is_string($request->split_payments) ? json_decode($request->split_payments, true) : $request->split_payments;
                if (is_array($splits)) {
                    foreach ($splits as $sp) {
                        if (isset($sp['payment_method_id']) && $sp['payment_method_id']) {
                            $hasPayment = true;
                        }
                    }
                }
            }

            if (!$hasPayment) {
                return response()->json([
                    'message' => 'Metode Pembayaran wajib dipilih.',
                    'errors' => ['payment_method_id' => ['Pilih Metode Pembayaran sebelum menyimpan transaksi.']]
                ], 422);
            }
        }

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError) return $pinError;

        DB::beginTransaction();

        try {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            if (!$user) {
                throw new \Exception('User tidak terautentikasi.');
            }

            Log::info("DEBUG STOCK-OUT: Starting for user " . $user->id, [
                'category' => $request->category,
                'has_product_detail_ids' => !empty($request->product_detail_ids),
                'has_non_hp_items' => !empty($request->non_hp_items)
            ]);

            // Resolve Location for Reporting Date
            $userLocation = null;
            try {
                $userLocation = $user->branch ?: ($user->onlineShop ?: null);
            } catch (\Throwable $e) {
                Log::error("DEBUG STOCK-OUT: Failed to resolve user location: " . $e->getMessage());
            }

            $reportingDate = StockOut::calculateReportingDate(
                $request->category,
                $userLocation
            );

            Log::info("DEBUG STOCK-OUT: Reporting date resolved: " . $reportingDate);

            // Verify HP items availability
            $productDetails = collect();
            if ($request->product_detail_ids) {
                $uniqueIds = array_unique($request->product_detail_ids);
                $productDetails = ProductDetail::whereIn('id', $uniqueIds)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->get();

                if ($productDetails->count() !== count($uniqueIds)) {
                    throw new \Exception('Beberapa barang HP sudah tidak tersedia atau sudah keluar stok.');
                }
            }

            // Verify Non-HP items availability and Deduct
            /** @var \App\Models\User|null $user */
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

                        $sourceBranch = $request->origin_branch_id ?? $user->branch_id;
                        $sourceWarehouse = $request->origin_warehouse_id ?? $user->warehouse_id;
                        $sourceOnlineShop = $request->origin_online_shop_id ?? $user->online_shop_id;

                        if ($sourceBranch) {
                            $hpQuery->where('placement_type', 'branch')->where('placement_id', $sourceBranch);
                        } elseif ($sourceWarehouse) {
                            $hpQuery->where('placement_type', 'warehouse')->where('placement_id', $sourceWarehouse);
                        } elseif ($sourceOnlineShop) {
                            $hpQuery->where('placement_type', 'online_shop')->where('placement_id', $sourceOnlineShop);
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

                    $reqBranch = $request->origin_branch_id;
                    $reqWarehouse = $request->origin_warehouse_id;
                    $reqOnlineShop = $request->origin_online_shop_id;

                    if ($reqBranch) {
                        $invQuery->where('placement_type', 'branch')->where('placement_id', $reqBranch);
                    } elseif ($reqWarehouse) {
                        $invQuery->where('placement_type', 'warehouse')->where('placement_id', $reqWarehouse);
                    } elseif ($reqOnlineShop) {
                        $invQuery->where('placement_type', 'online_shop')->where('placement_id', $reqOnlineShop);
                    } elseif ($user->branch_id) {
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
                            'branch_id' => $request->origin_branch_id ?? $user->branch_id ?? null,
                            'warehouse_id' => $request->origin_warehouse_id ?? $user->warehouse_id ?? null,
                            'online_shop_id' => $request->origin_online_shop_id ?? $user->online_shop_id ?? null,
                            'reporting_date' => $reportingDate,
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

            $paymentProofImagePath = null;
            if ($request->hasFile('payment_proof_image')) {
                $paymentProofImagePath = $request->file('payment_proof_image')->store('stock-outs/payment-proofs', 'public');
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
                                $itemPrice = (isset($item['selling_price']) && floatval($item['selling_price']) > 0)
                                    ? floatval($item['selling_price'])
                                    : floatval($prod->price);
                                $totalSellingPrice += ($itemPrice * intval($item['quantity']));
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
                'warehouse_id' => $request->origin_warehouse_id,
                'branch_id' => $request->origin_branch_id,
                'online_shop_id' => $request->origin_online_shop_id,
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
                'payment_proof_image' => $paymentProofImagePath,
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
                        Log::warning("Pre-generation failed for StockOut ID {$stockOut->id}: " . $e->getMessage());
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
                            'selling_price' => (isset($item['selling_price']) && floatval($item['selling_price']) > 0) ? floatval($item['selling_price']) : (isset($prod) ? floatval($prod->price) : 0),
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

                // If pindah_cabang, set status to in_transit
                // The item's location will NOT move to the destination yet.
                // It will be moved when the destination branch confirms the transfer (Transfer Masuk).
                if ($request->category === 'pindah_cabang') {
                    $updateData['status'] = 'in_transit'; // OTW
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
                    'reporting_date' => $reportingDate,
                ]);
            }

            // Handle Tukar Unit incoming item
            if ($request->category === 'tukar_unit' && ($request->filled('incoming_product_type_id') || $request->filled('incoming_product_name')) && $request->filled('incoming_cost_price')) {
                $incomingImei = $request->incoming_imei;
                $incomingStorage = $request->incoming_storage;
                $incomingCondition = $request->incoming_condition ?? 'second';
                $incomingCostPrice = (float) $request->incoming_cost_price;
                $incomingDistributorId = $request->incoming_distributor_id;

                // Resolve product from product_type_id or product_name
                $incomingProductName = $request->incoming_product_name;
                if ($request->filled('incoming_product_type_id')) {
                    $productType = \App\Models\ProductType::with('brand')->find($request->incoming_product_type_id);
                    if ($productType) {
                        $incomingProductName = $productType->name;
                        $brandName = $productType->brand?->name ?? 'Unknown';
                    }
                }
                $brandName = $brandName ?? 'Unknown';

                // Find or create product
                $incomingProduct = \App\Models\Product::firstOrCreate(
                    ['name' => $incomingProductName, 'brand' => $brandName],
                    ['type' => $incomingImei ? 'hp' : 'non-hp', 'has_imei' => !!$incomingImei, 'is_active' => true, 'sku' => 'TU-' . strtoupper(\Illuminate\Support\Str::random(8))]
                );

                $placementType = $user->online_shop_id ? 'online_shop' : ($user->warehouse_id ? 'warehouse' : 'branch');
                $placementId = $user->online_shop_id ?? $user->warehouse_id ?? $user->branch_id;
                $ownerUserId = $request->inventory_user_id ?? $user->id;

                if ($incomingImei) {
                    // HP item - create ProductDetail
                    $existingPd = ProductDetail::where('imei', $incomingImei)->first();
                    if ($existingPd) {
                        $existingPd->update([
                            'product_id' => $incomingProduct->id,
                            'status' => 'available',
                            'placement_type' => $placementType,
                            'placement_id' => $placementId,
                            'storage' => $incomingStorage,
                            'condition' => $incomingCondition,
                            'cost_price' => $incomingCostPrice,
                            'supplier_name' => 'Tukar Unit: ' . ($request->customer_name ?? 'Customer'),
                            'distributor_id' => $incomingDistributorId,
                            'user_id' => $ownerUserId,
                        ]);
                    } else {
                        ProductDetail::create([
                            'product_id' => $incomingProduct->id,
                            'imei' => $incomingImei,
                            'status' => 'available',
                            'placement_type' => $placementType,
                            'placement_id' => $placementId,
                            'storage' => $incomingStorage,
                            'condition' => $incomingCondition,
                            'cost_price' => $incomingCostPrice,
                            'selling_price' => 0,
                            'supplier_name' => 'Tukar Unit: ' . ($request->customer_name ?? 'Customer'),
                            'distributor_id' => $incomingDistributorId,
                            'user_id' => $ownerUserId,
                        ]);
                    }
                }

                // Log incoming
                InventoryLog::create([
                    'product_id' => $incomingProduct->id,
                    'branch_id' => $placementType === 'branch' ? $placementId : null,
                    'warehouse_id' => $placementType === 'warehouse' ? $placementId : null,
                    'online_shop_id' => $placementType === 'online_shop' ? $placementId : null,
                    'user_id' => $ownerUserId,
                    'type' => 'in',
                    'quantity' => 1,
                    'reference_id' => $stockOut->receipt_id,
                    'description' => 'Tukar Unit IN: ' . $incomingProductName . ($incomingImei ? " ($incomingImei)" : ''),
                    'supplier_name' => 'Tukar Unit Customer',
                ]);
            }

            DB::commit();

            // Bust Inventory Cache
            \Illuminate\Support\Facades\Cache::increment('inv_version');

            Log::info("DEBUG STOCK-OUT: Success! Receipt ID: " . $stockOut->receipt_id);

            return response()->json([
                'message' => 'Stock out successful',
                'id' => $stockOut->id,
                'receipt_id' => $stockOut->receipt_id,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("DEBUG STOCK-OUT CRASH: " . $e->getMessage(), [
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

    public function show($id)
    {
        $stockOut = StockOut::with(['items.product', 'nonHpDetails.product', 'user.branch', 'user.onlineShop', 'inventoryUser.branch', 'inventoryUser.onlineShop', 'branch', 'onlineShop', 'destinationBranch', 'destination', 'paymentMethod'])
            ->where('id', $id)
            ->orWhere('receipt_id', $id)
            ->firstOrFail();

        return response()->json($stockOut);
    }

    // Get Shopee History
    public function shopeeHistory(Request $request)
    {
        /** @var \App\Models\User|null $user */
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

                $q->where(function ($sq) use ($bIds, $wIds, $osIds) {
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

            // 1. Search STOCK IN (Registration Events) — Use InventoryLog for accurate history
            // InventoryLog type=in records are permanent and never recreated, unlike ProductDetail

            // First get ALL ProductDetails for this IMEI to cover re-registrations and deleted items
            $currentDetails = ProductDetail::withTrashed()
                ->with(['product', 'distributor', 'user', 'stockOuts'])
                ->where('imei', $query)
                ->orderBy('created_at', 'asc')
                ->get();
                
            $currentDetail = $currentDetails->last(); // Keep the latest for reference if needed

            // Search InventoryLog by IMEI in description OR by reference_id matching ProductDetail
            $stockInLogQuery = InventoryLog::with(['product', 'user', 'distributor'])
                ->where('type', 'in')
                ->where(function ($q) use ($query, $currentDetails) {
                    $q->where('description', 'like', "%({$query})%");
                    if ($currentDetails->isNotEmpty()) {
                        $q->orWhereIn('reference_id', $currentDetails->pluck('id')->map(fn($id) => (string)$id)->toArray());
                    }
                })
                ->where('description', 'not like', 'Pindah Cabang Masuk%')
                ->where('description', 'not like', 'Transfer Masuk%')
                ->orderBy('created_at');

            $stockInLogs = $stockInLogQuery->get();

            // Do not deduplicate stock_in logs so the full history of when an item entered is visible.
            // (Removed previous logic that only showed the most recent stock_in)

            foreach ($stockInLogs as $log) {
                $locationName = match (true) {
                    !empty($log->branch_id) => \App\Models\Branch::find($log->branch_id)?->name ?? 'Unknown Branch',
                    !empty($log->warehouse_id) => \App\Models\Warehouse::find($log->warehouse_id)?->name ?? 'Unknown Warehouse',
                    !empty($log->online_shop_id) => \App\Models\OnlineShop::find($log->online_shop_id)?->name ?? 'Unknown Shop',
                    default => '-'
                };

                $allEvents[] = [
                    'type' => 'stock_in',
                    'sub_type' => 'registration',
                    'id' => 'IN-' . ($log->reference_id ?? $log->id),
                    'imei' => $query,
                    'product_name' => $log->product?->name ?? ($currentDetail?->product?->name ?? '-'),
                    'status' => $currentDetail?->status ?? 'available',
                    'placement_type' => !empty($log->branch_id) ? 'branch' : (!empty($log->warehouse_id) ? 'warehouse' : 'online_shop'),
                    'placement_id' => $log->branch_id ?? $log->warehouse_id ?? $log->online_shop_id,
                    'placement_name' => $locationName,
                    'created_at' => $log->created_at->toDateTimeString(),
                    'timestamp' => $log->created_at->timestamp,
                    'distributor' => $log->distributor?->name ?? ($currentDetail?->distributor?->name ?? null),
                    'supplier_name' => $log->supplier_name ?? ($currentDetail?->supplier_name ?? null),
                    'input_by' => $log->user?->name,
                    'ram' => $currentDetail?->ram,
                    'storage' => $currentDetail?->storage,
                    'selling_price' => $currentDetail?->selling_price,
                    'condition' => $currentDetail?->condition,
                ];
            }

            // Fallback: If no InventoryLog found but ProductDetail exists (legacy data without logs)
            // Apply fallback for ANY ProductDetail that doesn't have a corresponding InventoryLog
            foreach ($currentDetails as $detail) {
                // Check if this detail has an InventoryLog (either by reference_id or within a few seconds)
                $hasLog = $stockInLogs->contains(function ($log) use ($detail, $query) {
                    return $log->reference_id === (string)$detail->id || 
                           (str_contains($log->description, "({$query})") && abs($log->created_at->timestamp - $detail->created_at->timestamp) <= 60);
                });

                if (!$hasLog) {
                    // Show the original registration event from ProductDetail
                    // Only skip if the ONLY stock-in event is the barang_masuk itself
                    // (i.e., ProductDetail was created at the same time as barang_masuk)
                    $shouldShow = true;
                    $barangMasukOut = $detail->stockOuts->first(fn($so) => $so->category === 'barang_masuk');
                    if ($barangMasukOut) {
                        $pdTs = $detail->created_at->timestamp;
                        $bmTs = $barangMasukOut->created_at->timestamp;
                        // If ProductDetail was created by barang_masuk (same timestamp), skip it
                        if (abs($pdTs - $bmTs) <= 5) {
                            $shouldShow = false;
                        }
                    }

                    if ($shouldShow) {
                        $locationName = match ($detail->placement_type) {
                            'branch' => \App\Models\Branch::find($detail->placement_id)?->name ?? 'Unknown Branch',
                            'warehouse' => \App\Models\Warehouse::find($detail->placement_id)?->name ?? 'Unknown Warehouse',
                            'online_shop' => \App\Models\OnlineShop::find($detail->placement_id)?->name ?? 'Unknown Shop',
                            default => $detail->placement_type . ' #' . $detail->placement_id
                        };

                        $allEvents[] = [
                            'type' => 'stock_in',
                            'sub_type' => 'registration',
                            'id' => 'IN-' . $detail->id,
                            'imei' => $detail->imei,
                            'product_name' => $detail->product?->name ?? 'Unknown',
                            'status' => $detail->status,
                            'placement_type' => $detail->placement_type,
                            'placement_id' => $detail->placement_id,
                            'placement_name' => $locationName,
                            'created_at' => $detail->created_at->toDateTimeString(),
                            'timestamp' => $detail->created_at->timestamp,
                            'distributor' => $detail->distributor?->name,
                            'supplier_name' => $detail->supplier_name,
                            'input_by' => $detail->user?->name,
                            'ram' => $detail->ram,
                            'storage' => $detail->storage,
                            'selling_price' => $detail->selling_price,
                            'condition' => $detail->condition,
                        ];
                    }
                }
            }

            // 2. Search STOCK OUT (Execution & Arrival Events)
            $stockOuts = StockOut::withTrashed()->with([
                'items' => function ($q) {
                    $q->withTrashed();
                },
                'items.product',
                'items.distributor',
                'nonHpDetails.product',
                'user.branch',
                'inventoryUser.branch',
                'destinationBranch',
                'destination',
                'confirmedBy',
                'branch',
                'onlineShop',
                'warehouse',
                'paymentMethod',
                'cancelledByUser'
            ])
                ->where('receipt_id', $query)
                ->orWhere('shopee_tracking_no', $query)
                ->orWhereHas('items', function ($q) use ($query) {
                    $q->withTrashed()->where('imei', $query);
                })
                ->orWhere('shopee_items_data', 'like', "%\"{$query}\"%")
                ->get()
                ->unique('id');

            // Auto-detect and include MASUK event for non-HP Auto-Transfers
            $additionalStockOuts = collect();
            foreach ($stockOuts as $so) {
                if (in_array($so->category, ['pindah_cabang', 'keluar'])) {
                    $nonHpProductIds = $so->nonHpDetails->pluck('product_id')->toArray();
                    $nhData = $so->non_hp_items;
                    $nonHpItems = is_string($nhData) ? json_decode($nhData, true) : (is_array($nhData) ? $nhData : []);
                    foreach ($nonHpItems as $nhp) {
                        if (isset($nhp['product_id']) && !in_array($nhp['product_id'], $nonHpProductIds)) {
                            $nonHpProductIds[] = $nhp['product_id'];
                        }
                    }

                    if (count($nonHpProductIds) > 0) {
                        $fiveMinsBefore = $so->created_at->subMinutes(5);
                        $masukOuts = StockOut::withTrashed()->with([
                            'items.product',
                            'items.distributor',
                            'nonHpDetails.product',
                            'user.branch',
                            'inventoryUser.branch',
                            'destinationBranch',
                            'destination',
                            'confirmedBy',
                            'branch',
                            'onlineShop',
                            'warehouse',
                            'paymentMethod'
                        ])
                            ->where('category', 'barang_masuk')
                            ->where('user_id', $so->user_id)
                            ->whereBetween('created_at', [$fiveMinsBefore, $so->created_at->addMinute()])
                            ->get();

                        foreach ($masukOuts as $mo) {
                            $moProdIds = $mo->nonHpDetails->pluck('product_id')->toArray();
                            $moNhData = $mo->non_hp_items;
                            $moNonHpItems = is_string($moNhData) ? json_decode($moNhData, true) : (is_array($moNhData) ? $moNhData : []);
                            foreach ($moNonHpItems as $nhp) {
                                if (isset($nhp['product_id']) && !in_array($nhp['product_id'], $moProdIds)) {
                                    $moProdIds[] = $nhp['product_id'];
                                }
                            }

                            if (count(array_intersect($nonHpProductIds, $moProdIds)) > 0) {
                                $additionalStockOuts->push($mo);
                            }
                        }
                    }
                }
            }

            $stockOuts = $stockOuts->merge($additionalStockOuts)->unique('id');

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
                    'distributor_name' => $i->distributor?->name,
                    'supplier_name' => $i->supplier_name,
                    'notes' => $i->pivot?->notes,
                    'brand' => $i->product?->brandRelation?->name ?? ($i->product?->brand ?? '-'),
                    'storage' => $i->storage,
                    'condition' => $i->condition,
                ])->toArray();

                // Non-HP enrich from relation
                foreach ($out->nonHpDetails as $detail) {
                    $mergedItems[] = [
                        'type' => 'non-hp',
                        'product_name' => $detail->product?->name ?? 'Unknown Product',
                        'quantity' => $detail->quantity,
                        'tracking_no' => null,
                        'notes' => $detail->notes,
                        'brand' => $detail->product?->brandRelation?->name ?? ($detail->product?->brand ?? '-'),
                        'selling_price' => $detail->selling_price,
                        'distributor_name' => $detail->distributor?->name,
                    ];
                }

                // Non-HP enrich from JSON (fallback)
                $nhData = $out->non_hp_items;
                $nonHpItems = is_string($nhData) ? json_decode($nhData, true) : (is_array($nhData) ? $nhData : []);
                if ($out->nonHpDetails->isEmpty() && !empty($nonHpItems)) {
                    $productIds = array_column($nonHpItems, 'product_id');
                    $products = \App\Models\Product::with('brandRelation')->whereIn('id', $productIds)->get()->keyBy('id');
                    foreach ($nonHpItems as $nhp) {
                        $prod = $products->get($nhp['product_id']);

                        // Coba cari distributor name dari ID di JSON jika ada
                        $distName = null;
                        if (!empty($nhp['distributor_id'])) {
                            $dist = \App\Models\Distributor::find($nhp['distributor_id']);
                            $distName = $dist ? $dist->name : null;
                        }

                        $mergedItems[] = [
                            'type' => 'non-hp',
                            'product_name' => $prod?->name ?? 'Unknown Product',
                            'quantity' => $nhp['quantity'] ?? 1,
                            'tracking_no' => $nhp['tracking_no'] ?? null,
                            'notes' => $nhp['notes'] ?? null,
                            'brand' => $prod?->brandRelation?->name ?? ($prod?->brand ?? '-'),
                            'selling_price' => $nhp['selling_price'] ?? 0,
                            'distributor_name' => $distName,
                        ];
                    }
                }

                // Eagerly fetch exchange details if applicable
                $exchangeInfo = null;
                $catLower = strtolower($out->category);
                if ($catLower === 'tukar_tambah') {
                    $exchangeInfo = \App\Models\TukarTambah::with(['incomingProductType.brand', 'distributor'])
                        ->where('receipt_id', $out->receipt_id)
                        ->first();
                } elseif ($catLower === 'downgrade') {
                    $exchangeInfo = \App\Models\Downgrade::with(['incomingProductType.brand', 'distributor'])
                        ->where('receipt_id', $out->receipt_id)
                        ->first();
                } elseif ($catLower === 'tukar_unit') {
                    $exchangeInfo = \App\Models\UnitExchange::with(['incomingProductType.brand', 'distributor'])
                        ->where('receipt_id', $out->receipt_id)
                        ->first();
                } elseif ($catLower === 'refund') {
                    $exchangeInfo = \App\Models\Refund::where('receipt_id', $out->receipt_id)->first();
                }

                // Compile proof images list
                $proofImages = collect([
                    $out->proof_image,
                    $exchangeInfo->photo_unit ?? null,
                    $exchangeInfo->photo_customer ?? null
                ])->filter()->unique()->map(fn($path) => asset('storage/' . $path))->values()->toArray();

                // Build detailed raw items for ReceiptModal
                $rawItems = [];
                if ($exchangeInfo && in_array($catLower, ['tukar_tambah', 'downgrade', 'tukar_unit'])) {
                    $inProd = ($exchangeInfo->incomingProductType->name ?? 'Unit Konsumen');
                    $inImei = $exchangeInfo->incoming_imei ?? '-';
                    $rawItems[] = [
                        'type' => 'IN',
                        'is_hp' => true,
                        'imei' => $inImei,
                        'name' => "IN: " . $inProd,
                        'product_name' => "IN: " . $inProd,
                        'qty' => 1,
                        'quantity' => 1,
                        'price' => -(float) ($exchangeInfo->incoming_cost_price ?? 0),
                        'selling_price' => -(float) ($exchangeInfo->incoming_cost_price ?? 0),
                        'discount' => 0,
                        'item_discount' => 0,
                        'brand' => $exchangeInfo->incomingProductType->brand->name ?? '-',
                        'condition' => $exchangeInfo->incoming_condition ?? 'second',
                        'storage' => $exchangeInfo->incoming_storage ?? '-',
                        'is_incoming' => true,
                        'notes' => null
                    ];
                }

                // Add outgoing HP items to rawItems
                foreach ($out->items as $i) {
                    $isRefundOrAngkat = in_array($catLower, ['refund', 'angkat_barang']);
                    $pName = ($isRefundOrAngkat ? "IN: " : "") . ($i->product?->name ?? 'Unknown HP');
                    if ($exchangeInfo && in_array($catLower, ['tukar_tambah', 'downgrade', 'tukar_unit'])) {
                        $pName = "OUT: " . ($i->product?->name ?? 'Unknown HP');
                    }

                    $rawItems[] = [
                        'type' => 'hp',
                        'is_hp' => true,
                        'imei' => $i->imei,
                        'name' => $pName,
                        'product_name' => $pName,
                        'qty' => 1,
                        'quantity' => 1,
                        'price' => (float)($i->pivot?->selling_price ?? 0),
                        'selling_price' => (float)($i->pivot?->selling_price ?? 0),
                        'discount' => (float)($i->pivot?->item_discount ?? 0),
                        'item_discount' => (float)($i->pivot?->item_discount ?? 0),
                        'brand' => $i->product?->brandRelation?->name ?? ($i->product?->brand ?? '-'),
                        'condition' => $i->condition,
                        'storage' => $i->storage,
                        'distributor_name' => $i->distributor?->name,
                        'supplier_name' => $i->supplier_name,
                        'notes' => $i->pivot?->notes
                    ];
                }

                // Add Non-HP items to rawItems from mergedItems
                foreach ($mergedItems as $mItem) {
                    if ($mItem['type'] === 'non-hp') {
                        $rawItems[] = [
                            'type' => 'non-hp',
                            'is_hp' => false,
                            'name' => $mItem['product_name'],
                            'product_name' => $mItem['product_name'],
                            'qty' => (int)($mItem['quantity'] ?? 1),
                            'quantity' => (int)($mItem['quantity'] ?? 1),
                            'price' => (float)($mItem['selling_price'] ?? 0),
                            'selling_price' => (float)($mItem['selling_price'] ?? 0),
                            'discount' => 0,
                            'item_discount' => 0,
                            'brand' => $mItem['brand'],
                            'condition' => '-',
                            'storage' => '-',
                            'tracking_no' => $mItem['tracking_no'] ?? null,
                            'notes' => $mItem['notes'] ?? null,
                            'distributor_name' => $mItem['distributor_name'] ?? null
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

                // Event 1: Skip barang_masuk if we already have a stock_in event from InventoryLog around the same time
                if ($out->category === 'barang_masuk') {
                    $bmTs = $out->created_at->timestamp;
                    $alreadyHasStockIn = collect($allEvents)->contains(function ($evt) use ($bmTs) {
                        return $evt['type'] === 'stock_in' && abs($evt['timestamp'] - $bmTs) <= 60;
                    });
                    if ($alreadyHasStockIn) {
                        continue;
                    }
                    // No matching log — render barang_masuk as stock_in
                    $allEvents[] = [
                        'type' => 'stock_in',
                        'sub_type' => 'registration',
                        'id' => $out->receipt_id,
                        'imei' => $query,
                        'product_name' => $out->items->first()?->product?->name ?? ($out->nonHpDetails->first()?->product?->name ?? 'Mixed Items'),
                        'status' => 'available',
                        'placement_type' => $out->branch_id ? 'branch' : ($out->warehouse_id ? 'warehouse' : 'online_shop'),
                        'placement_id' => $out->branch_id ?? $out->warehouse_id ?? $out->online_shop_id,
                        'placement_name' => $out->branch?->name ?: ($out->warehouse?->name ?: ($out->onlineShop?->name ?: '-')),
                        'created_at' => $out->created_at->toDateTimeString(),
                        'timestamp' => $out->created_at->timestamp,
                        'distributor' => $out->items->first()?->distributor?->name ?? ($out->nonHpDetails->first()?->distributor?->name ?? null),
                        'supplier_name' => $out->items->first()?->supplier_name,
                        'input_by' => $out->inventoryUser ? ($out->inventoryUser->full_name ?? $out->inventoryUser->name) : ($out->user?->name ?? $out->user?->username),
                        'condition' => $out->items->first()?->condition ?? '-',
                        'selling_price' => $out->items->first()?->selling_price ?? ($out->nonHpDetails->first()?->selling_price ?? 0),
                        'storage' => $out->items->first()?->storage ?? '-',
                        'notes' => $out->notes ?? ($out->nonHpDetails->first()?->notes ?? null),
                    ];
                    continue;
                }

                $allEvents[] = [
                    'type' => 'stock_out',
                    'sub_type' => 'departure',
                    'id' => $out->receipt_id,
                    'category' => $out->category,
                    'sub_category' => $out->sub_category,
                    'ba_name' => $out->ba_name,
                    'ba_phone' => $out->ba_phone,
                    'ba_social_media' => $out->ba_social_media,
                    'ba_notes' => $out->ba_notes,
                    'event_name' => $out->event_name,
                    'event_receiver' => $out->event_receiver,
                    'event_phone' => $out->event_phone,
                    'event_doc' => $out->event_doc,
                    'giveaway_receiver' => $out->giveaway_receiver,
                    'giveaway_phone' => $out->giveaway_phone,
                    'giveaway_address' => $out->giveaway_address,
                    'giveaway_notes' => $out->giveaway_notes,
                    'person_in_charge' => $out->person_in_charge,
                    'loss_chronology' => $out->loss_chronology,
                    'items' => $mergedItems,
                    'shopee_receiver' => implode(', ', array_unique($shopeeReceivers)) ?: null,
                    'shopee_tracking_no' => implode(', ', array_unique($shopeeTrackingNos)) ?: null,
                    'destination' => $out->destination ? ['name' => $out->destination->name, 'type' => $out->destination_type] : null,
                    'receiver_name' => $out->receiver_name,
                    'customer_name' => $out->customer_name,
                    'customer_wa' => $out->customer_wa,
                    'notes' => $out->notes,
                    'cancel_reason' => $out->cancel_reason,
                    'deletion_reason' => $out->deletion_reason,
                    'cancelled_by_name' => $out->category === 'kesalahan_input' ? ($out->user?->name ?? '') : ($out->cancelledByUser?->name ?? ''),
                    'processed_by' => $out->inventoryUser ? ($out->inventoryUser->full_name ?? $out->inventoryUser->name) : ($out->user?->name ?? $out->user?->username),
                    'status' => ($out->category === 'pindah_cabang' && $out->status === 'rejected') ? 'pending' : $out->status,
                    'created_at' => ($out->category === 'cancel_penjualan' && $out->cancelled_at) ? $out->cancelled_at->toDateTimeString() : $out->created_at->toDateTimeString(),
                    'timestamp' => ($out->category === 'cancel_penjualan' && $out->cancelled_at) ? $out->cancelled_at->timestamp : $out->created_at->timestamp,

                    // Extra properties for multiple proof images and receipt display
                    'proof_images' => $proofImages,
                    'proof_image' => $out->proof_image ? asset('storage/' . $out->proof_image) : ($exchangeInfo && $exchangeInfo->photo_unit ? asset('storage/' . $exchangeInfo->photo_unit) : null),
                    'order_no' => $out->receipt_id,
                    'branch' => $out->branch,
                    'online_shop' => $out->onlineShop,
                    'warehouse' => $out->warehouse,
                    'source_name' => $out->branch?->name ?: ($out->warehouse?->name ?: ($out->onlineShop?->name ?: '-')),
                    'original_price' => (float)$out->selling_price,
                    'selling_price' => (float)$out->selling_price,
                    'total_discount' => (float)$out->total_discount,
                    'grand_total' => (float)($out->selling_price - $out->total_discount),
                    'payment_method_name' => $out->paymentMethod?->name ?? '-',
                    'split_payments_data' => $out->split_payments_data ?? [],
                    'inventory_user_name' => $out->inventoryUser ? ($out->inventoryUser->full_name ?? $out->inventoryUser->name) : ($out->user?->name ?? $out->user?->username),
                    'sales_account' => $out->sales_account,
                    'raw_items' => $rawItems,
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
                        'processed_by' => $out->confirmedBy?->name ?? 'Unknown',
                        'status' => 'rejected', // Red "Ditolak" badge
                        'created_at' => $out->confirmed_at->toDateTimeString(),
                        'timestamp' => $out->confirmed_at->timestamp,
                        'is_rejection' => true,
                        'branch' => $out->branch,
                        'online_shop' => $out->onlineShop,
                        'warehouse' => $out->warehouse,
                        'source_name' => $out->branch?->name ?: ($out->warehouse?->name ?: ($out->onlineShop?->name ?: '-')),
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

                // Event 3b: RETUR REJECTED (returned to original sender/location)
                if ($out->category === 'retur' && $out->status === 'rejected' && $out->confirmed_at) {
                    $returItem = $out->items->first(function ($i) use ($query, $out) {
                        if ($query === $out->receipt_id) return true;
                        return stripos($i->imei, $query) !== false;
                    }) ?: $out->items->first();

                    if ($returItem) {
                        $rejectUser = $out->confirmedBy;
                        if ((!$rejectUser || !$rejectUser->hasRole('inventory')) && $out->return_destination_id) {
                            $rejectUser = \App\Models\User::role('inventory')
                                ->where('warehouse_id', $out->return_destination_id)
                                ->where('is_active', true)
                                ->first() ?: $rejectUser;
                        }

                        $placementName = match ($returItem->placement_type) {
                            'warehouse' => \App\Models\Warehouse::find($returItem->placement_id)?->name ?? 'Gudang',
                            'branch' => \App\Models\Branch::find($returItem->placement_id)?->name ?? 'Cabang',
                            'online_shop' => \App\Models\OnlineShop::find($returItem->placement_id)?->name ?? 'Toko Online',
                            'distributor' => \App\Models\Distributor::find($returItem->placement_id)?->name ?? 'Distributor',
                            default => 'Unknown'
                        };

                        $allEvents[] = [
                            'type' => 'stock_in',
                            'sub_type' => 'retur_rejected_return',
                            'id' => $out->receipt_id,
                            'imei' => $returItem->imei,
                            'product_name' => $returItem->product?->name ?? 'Unknown',
                            'status' => 'available',
                            'placement_type' => $returItem->placement_type,
                            'placement_id' => $returItem->placement_id,
                            'placement_name' => $placementName,
                            'created_at' => $out->confirmed_at->toDateTimeString(),
                            'timestamp' => $out->confirmed_at->timestamp,
                            'input_by' => $rejectUser?->name ?? 'Unknown',
                            'condition' => $returItem->condition ?? '-',
                            'selling_price' => $returItem->selling_price ?? 0,
                            'distributor' => $returItem->distributor?->name ?? '-',
                            'storage' => $returItem->storage ?? '-',
                            'rejected_by' => $rejectUser?->name ?? 'Unknown',
                            'is_arrival' => true,
                            'is_retur_rejection' => true,
                        ];
                    }
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

            // Deduplicate events with same id + type + sub_type + date
            $seen = [];
            $allEvents = array_filter($allEvents, function ($evt) use (&$seen) {
                $date = substr($evt['created_at'], 0, 10);
                $key = $evt['id'] . '|' . $evt['type'] . '|' . ($evt['sub_type'] ?? '') . '|' . $date;
                if (isset($seen[$key])) return false;
                $seen[$key] = true;
                return true;
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
    public function indexIncoming(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user)
            return response()->json(['data' => []]);

        $query = StockOut::with(['items.product.brandRelation', 'items.distributor', 'nonHpItems.product.brandRelation', 'nonHpItems.distributor', 'user.branch', 'user.warehouse', 'user.onlineShop', 'inventoryUser.branch', 'inventoryUser.warehouse', 'inventoryUser.onlineShop', 'destinationBranch', 'destination'])
            ->where('category', 'pindah_cabang')
            ->where('status', 'pending');

        // Filter by Destination
        $query->where(function ($q) use ($user, $request) {
            $hasFilter = false;
            $isUnrestricted = $user->hasRole(['super_admin', 'owner', 'admin_produk', 'analist']);

            // Branch
            $branchIds = $user->getAccessibleBranchIds();
            $warehouseIds = $user->getAccessibleWarehouseIds();
            $onlineShopIds = $user->getAccessibleOnlineShopIds();
            $distributorIds = $user->getAccessibleDistributorIds();

            if ($request->branch_id) {
                $branchIds = $isUnrestricted || in_array($request->branch_id, $branchIds) ? [$request->branch_id] : [];
                $warehouseIds = [];
                $onlineShopIds = [];
                $distributorIds = [];
                $isUnrestricted = false;
            } elseif ($request->online_shop_id) {
                $onlineShopIds = $isUnrestricted || in_array($request->online_shop_id, $onlineShopIds) ? [$request->online_shop_id] : [];
                $branchIds = [];
                $warehouseIds = [];
                $distributorIds = [];
                $isUnrestricted = false;
            } elseif ($request->warehouse_id) {
                $warehouseIds = $isUnrestricted || in_array($request->warehouse_id, $warehouseIds) ? [$request->warehouse_id] : [];
                $branchIds = [];
                $onlineShopIds = [];
                $distributorIds = [];
                $isUnrestricted = false;
            } elseif ($request->distributor_id) {
                $distributorIds = $isUnrestricted || in_array($request->distributor_id, $distributorIds) ? [$request->distributor_id] : [];
                $branchIds = [];
                $onlineShopIds = [];
                $warehouseIds = [];
                $isUnrestricted = false;
            }

            if (!empty($branchIds)) {
                $q->orWhere(function ($sub) use ($branchIds) {
                    $sub->where('destination_type', 'branch')
                        ->whereIn('destination_id', $branchIds);
                });
                $hasFilter = true;
            }

            if (!empty($warehouseIds)) {
                $q->orWhere(function ($sub) use ($warehouseIds) {
                    $sub->where('destination_type', 'warehouse')
                        ->whereIn('destination_id', $warehouseIds);
                });
                $hasFilter = true;
            }

            if (!empty($onlineShopIds)) {
                $q->orWhere(function ($sub) use ($onlineShopIds) {
                    $sub->where('destination_type', 'online_shop')
                        ->whereIn('destination_id', $onlineShopIds);
                });
                $hasFilter = true;
            }

            if (!empty($distributorIds)) {
                $q->orWhere(function ($sub) use ($distributorIds) {
                    $sub->where('destination_type', 'distributor')
                        ->whereIn('destination_id', $distributorIds);
                });
                $hasFilter = true;
            }

            if (!$hasFilter) {
                if ($isUnrestricted) {
                    $q->orWhereRaw('1 = 1');
                } else {
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
    public function indexOutgoing(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user)
            return response()->json(['data' => []]);

        $query = StockOut::with(['items.product.brandRelation', 'items.distributor', 'nonHpItems.product.brandRelation', 'nonHpItems.distributor', 'user.branch', 'user.warehouse', 'user.onlineShop', 'inventoryUser.branch', 'inventoryUser.warehouse', 'inventoryUser.onlineShop', 'destinationBranch', 'destination'])
            ->where('category', 'pindah_cabang')
            ->where('status', 'pending');

        // Filter by Source (Created by user in the same location)
        $query->where(function ($q) use ($user, $request) {
            $isUnrestricted = $user->hasRole(['super_admin', 'admin_produk', 'owner']);

            $branchIds = $user->getAccessibleBranchIds();
            $warehouseIds = $user->getAccessibleWarehouseIds();
            $onlineShopIds = $user->getAccessibleOnlineShopIds();
            $distributorIds = $user->getAccessibleDistributorIds();

            if ($request->branch_id) {
                $branchIds = $isUnrestricted || in_array($request->branch_id, $branchIds) ? [$request->branch_id] : [];
                $warehouseIds = [];
                $onlineShopIds = [];
                $distributorIds = [];
                $isUnrestricted = false;
            } elseif ($request->online_shop_id) {
                $onlineShopIds = $isUnrestricted || in_array($request->online_shop_id, $onlineShopIds) ? [$request->online_shop_id] : [];
                $branchIds = [];
                $warehouseIds = [];
                $distributorIds = [];
                $isUnrestricted = false;
            } elseif ($request->warehouse_id) {
                $warehouseIds = $isUnrestricted || in_array($request->warehouse_id, $warehouseIds) ? [$request->warehouse_id] : [];
                $branchIds = [];
                $onlineShopIds = [];
                $distributorIds = [];
                $isUnrestricted = false;
            } elseif ($request->distributor_id) {
                $distributorIds = $isUnrestricted || in_array($request->distributor_id, $distributorIds) ? [$request->distributor_id] : [];
                $branchIds = [];
                $onlineShopIds = [];
                $warehouseIds = [];
                $isUnrestricted = false;
            }

            if ($isUnrestricted) {
                $q->orWhereRaw('1 = 1');
                return;
            }

            $hasFilter = false;
            if (!empty($branchIds)) {
                $q->orWhereIn('branch_id', $branchIds);
                $hasFilter = true;
            }
            if (!empty($warehouseIds)) {
                $q->orWhereIn('warehouse_id', $warehouseIds);
                $hasFilter = true;
            }
            if (!empty($onlineShopIds)) {
                $q->orWhereIn('online_shop_id', $onlineShopIds);
                $hasFilter = true;
            }
            if (!empty($distributorIds)) {
                $q->orWhereIn('distributor_id', $distributorIds);
                $hasFilter = true;
            }

            if (!$hasFilter) {
                $q->where('user_id', $user->id);
            }
        });

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

    // Get asset values for in-transit (incoming) and outgoing transfers
    public function getAssetValues()
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['in_value' => 0, 'out_value' => 0]);
        }

        // --- 1. Compute IN (Incoming Transfers Value) ---
        $inQuery = StockOut::where('category', 'pindah_cabang')
            ->where('status', 'pending');

        // Filter by Destination
        $inQuery->where(function ($q) use ($user) {
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
                    $q->orWhereRaw('1 = 1');
                } else {
                    $q->whereRaw('0 = 1');
                }
            }
        });

        $inTransfers = $inQuery->with(['items', 'nonHpItems.product'])->get();
        $inValue = 0;
        foreach ($inTransfers as $t) {
            foreach ($t->items as $item) {
                $inValue += (float) ($item->selling_price ?? 0);
            }
            foreach ($t->nonHpItems as $nh) {
                $inValue += (int) $nh->quantity * (float) ($nh->product->price ?? 0);
            }
        }

        // --- 2. Compute OUT (Outgoing Transfers Value) ---
        $outQuery = StockOut::where('category', 'pindah_cabang')
            ->where('status', 'pending');

        // Filter by Source (Created by user in the same location)
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner'];
        if (!$user->hasRole($unrestrictedRoles)) {
            $outQuery->where(function ($q) use ($user) {
                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $onlineShopIds = $user->getAccessibleOnlineShopIds();
                $distributorIds = $user->getAccessibleDistributorIds();

                $hasFilter = false;
                if (!empty($branchIds)) {
                    $q->orWhereIn('branch_id', $branchIds);
                    $hasFilter = true;
                }
                if (!empty($warehouseIds)) {
                    $q->orWhereIn('warehouse_id', $warehouseIds);
                    $hasFilter = true;
                }
                if (!empty($onlineShopIds)) {
                    $q->orWhereIn('online_shop_id', $onlineShopIds);
                    $hasFilter = true;
                }
                if (!empty($distributorIds)) {
                    $q->orWhereIn('distributor_id', $distributorIds);
                    $hasFilter = true;
                }

                if (!$hasFilter) {
                    $q->where('user_id', $user->id);
                }
            });
        }

        $outTransfers = $outQuery->with(['items', 'nonHpItems.product'])->get();
        $outValue = 0;
        foreach ($outTransfers as $t) {
            foreach ($t->items as $item) {
                $outValue += (float) ($item->selling_price ?? 0);
            }
            foreach ($t->nonHpItems as $nh) {
                $outValue += (int) $nh->quantity * (float) ($nh->product->price ?? 0);
            }
        }

        return response()->json([
            'in_value' => $inValue,
            'out_value' => $outValue
        ]);
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

            // Calculate Destination Location
            $destPlacementType = $stockOut->destination_type;
            $destPlacementId = $stockOut->destination_id;
            $destUser = \App\Models\User::find($confirmingUserId);

            // Fallback if destination is not in stockOut for some reason
            if (!$destPlacementType || !$destPlacementId) {
                if ($destUser) {
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
                }
            }

            if (!$destPlacementType || !$destPlacementId) {
                throw new \Exception("Gagal menentukan lokasi tujuan transfer.");
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

                    Log::info("DEBUG: PHP Log created for accepted HP #{$item->id} in Resi {$stockOut->receipt_id}");
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

                    Log::info("DEBUG: HP #{$item->id} marked as returning in Resi {$stockOut->receipt_id}");
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
                        $locationField = $destPlacementType . '_id';
                        $locationId = $destPlacementId;
                        $placementType = $destPlacementType;

                        if ($acceptedQty > 0) {
                            $inventory = Inventory::firstOrCreate(
                                [
                                    'product_id' => $record->product_id,
                                    'placement_type' => $placementType,
                                    'placement_id' => $locationId,
                                ],
                                [
                                    'quantity' => 0,
                                    'user_id' => $confirmingUserId,
                                    'selling_price' => $record->selling_price
                                ]
                            );

                            // Always ensure selling_price is updated to the transferred price if it was 0 or newly created
                            if ($inventory->selling_price <= 0 && $record->selling_price > 0) {
                                $inventory->selling_price = $record->selling_price;
                            }
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
            } catch (\Exception $e) {
            }
        }

        // --- 1. TRY BINDERBYTE FIRST (PRIMARY - COST FREE) ---
        $binderErrors = [];
        if ($binderKeys) {
            $courierMap = [
                'jne' => 'jne',
                'pos indonesia' => 'pos',
                'pos' => 'pos',
                'j&t' => 'jnt',
                'jnt' => 'jnt',
                'j&t cargo' => 'jnt_cargo',
                'jnt cargo' => 'jnt_cargo',
                'sicepat' => 'sicepat',
                'tiki' => 'tiki',
                'anteraja' => 'anteraja',
                'wahana' => 'wahana',
                'ninja' => 'ninja',
                'ninja xpress' => 'ninja',
                'lion' => 'lion',
                'lion parcel' => 'lion',
                'shopee' => 'spx',
                'shopee express' => 'spx',
                'spx' => 'spx',
                'id express' => 'ide',
                'ide' => 'ide',
                'indah' => 'indah_cargo',
                'indah cargo' => 'indah_cargo',
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
                        'api_key' => $key,
                        'courier' => $courierSlug,
                        'awb' => $awb
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
                    'jne' => 'jne',
                    'j&t' => 'jnt',
                    'jnt' => 'jnt',
                    'sicepat' => 'sicepat',
                    'tiki' => 'tiki',
                    'anteraja' => 'anteraja',
                    'wahana' => 'wahana',
                    'ninja' => 'ninja',
                    'shopee' => 'shopee',
                    'shopee express' => 'shopee',
                    'spx' => 'shopee',
                    'lion' => 'lion',
                    'id express' => 'ide',
                    'pos' => 'pos',
                    'pos indonesia' => 'pos',
                    'pcp' => 'pcp',
                    'jet' => 'jet',
                    'sap' => 'sap'
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

                    $history = array_map(function ($h) use ($statusIndo) {
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
            } catch (\Exception $e) {
            }
        }

        return response()->json([
            'success' => false,
            'message' => "Seluruh server pelacakan (Binderbyte & BiteShip) tidak merespon resi ini. Mohon cek berkala di web resmi " . strtoupper($courier)
        ], 422);
    }

    // History of Transfers (Incoming and Outgoing)
    public function historyIncoming(Request $request)
    {
        /** @var \App\Models\User|null $user */
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
        $query->where(function ($q) use ($user, $type, $request) {
            $isUnrestricted = $user->hasRole(['super_admin', 'owner', 'admin_produk', 'analist']);

            $branchIds = $user->getAccessibleBranchIds();
            $warehouseIds = $user->getAccessibleWarehouseIds();
            $onlineShopIds = $user->getAccessibleOnlineShopIds();
            $distributorIds = $user->getAccessibleDistributorIds();

            if ($request->branch_id) {
                $branchIds = $isUnrestricted || in_array($request->branch_id, $branchIds) ? [$request->branch_id] : [];
                $warehouseIds = [];
                $onlineShopIds = [];
                $distributorIds = [];
                $isUnrestricted = false;
            } elseif ($request->online_shop_id) {
                $onlineShopIds = $isUnrestricted || in_array($request->online_shop_id, $onlineShopIds) ? [$request->online_shop_id] : [];
                $branchIds = [];
                $warehouseIds = [];
                $distributorIds = [];
                $isUnrestricted = false;
            } elseif ($request->warehouse_id) {
                $warehouseIds = $isUnrestricted || in_array($request->warehouse_id, $warehouseIds) ? [$request->warehouse_id] : [];
                $branchIds = [];
                $onlineShopIds = [];
                $distributorIds = [];
                $isUnrestricted = false;
            } elseif ($request->distributor_id) {
                $distributorIds = $isUnrestricted || in_array($request->distributor_id, $distributorIds) ? [$request->distributor_id] : [];
                $branchIds = [];
                $onlineShopIds = [];
                $warehouseIds = [];
                $isUnrestricted = false;
            }

            if ($type === 'outgoing' || $type === 'failed') {
                $hasFilter = false;

                if (!empty($branchIds)) {
                    $hasFilter = true;
                    $q->orWhereIn('branch_id', $branchIds);
                }

                if (!empty($warehouseIds)) {
                    $hasFilter = true;
                    $q->orWhereIn('warehouse_id', $warehouseIds);
                }

                if (!empty($onlineShopIds)) {
                    $hasFilter = true;
                    $q->orWhereIn('online_shop_id', $onlineShopIds);
                }

                if (!empty($distributorIds)) {
                    $hasFilter = true;
                    $q->orWhereIn('distributor_id', $distributorIds);
                }

                if ($isUnrestricted) {
                    $q->orWhereRaw('1 = 1'); // Show all for super admin
                } elseif (!$hasFilter) {
                    $q->whereRaw('0 = 1'); // Restrict if no locations assigned
                }
            } else {
                $hasFilter = false;

                if (!empty($branchIds)) {
                    $q->orWhere(function ($sub) use ($branchIds) {
                        $sub->where('destination_type', 'branch')
                            ->whereIn('destination_id', $branchIds);
                    });
                    $hasFilter = true;
                }

                // Warehouse
                if (!empty($warehouseIds)) {
                    $q->orWhere(function ($sub) use ($warehouseIds) {
                        $sub->where('destination_type', 'warehouse')
                            ->whereIn('destination_id', $warehouseIds);
                    });
                    $hasFilter = true;
                }

                // Online Shop
                if (!empty($onlineShopIds)) {
                    $q->orWhere(function ($sub) use ($onlineShopIds) {
                        $sub->where('destination_type', 'online_shop')
                            ->whereIn('destination_id', $onlineShopIds);
                    });
                    $hasFilter = true;
                }

                // Distributor
                if (!empty($distributorIds)) {
                    $q->orWhere(function ($sub) use ($distributorIds) {
                        $sub->where('destination_type', 'distributor')
                            ->whereIn('destination_id', $distributorIds);
                    });
                    $hasFilter = true;
                }

                if (!$hasFilter) {
                    if ($isUnrestricted) {
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
            'transaction_pin' => 'nullable|string'
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

            /** @var \App\Models\User|null $authorizer */
            $authorizer = Auth::user();
            if ($reportingDate->lt($fiveDaysAgo) && !($authorizer?->hasRole('super_admin') ?? false)) {
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
            $outgoingIds = [];
            if ($stockOut->category !== 'angkat_barang') {
                $outgoingIds = collect($stockOut->items)->pluck('id')->toArray();
            }

            // Retrieve outgoing product detail IDs from transaction tables directly to be 100% safe
            $ttOut = \App\Models\TukarTambah::where('receipt_id', $receiptId)->value('outgoing_product_detail_id');
            if ($ttOut) $outgoingIds[] = $ttOut;

            $ueOut = \App\Models\UnitExchange::where('receipt_id', $receiptId)->value('outgoing_product_detail_id');
            if ($ueOut) $outgoingIds[] = $ueOut;

            $dgOut = \App\Models\Downgrade::where('receipt_id', $receiptId)->value('outgoing_product_detail_id');
            if ($dgOut) $outgoingIds[] = $dgOut;

            $outgoingIds = array_unique(array_filter($outgoingIds));

            $incomingHPQuery = \App\Models\ProductDetail::where(function ($q) use ($receiptId) {
                $q->where('notes', 'like', "%Masuk dari %: $receiptId%")
                    ->orWhereHas('unitExchange', function ($sq) use ($receiptId) {
                        $sq->where('receipt_id', $receiptId);
                    })
                    ->orWhereHas('tukarTambah', function ($sq) use ($receiptId) {
                        $sq->where('receipt_id', $receiptId);
                    })
                    ->orWhereHas('downgrade', function ($sq) use ($receiptId) {
                        $sq->where('receipt_id', $receiptId);
                    })
                    ->orWhereHas('tradeIn', function ($sq) use ($receiptId) {
                        $sq->where('receipt_id', $receiptId);
                    })
                    ->orWhereHas('refund', function ($sq) use ($receiptId) {
                        $sq->where('receipt_id', $receiptId);
                    });
            });

            if (!empty($outgoingIds)) {
                $incomingHPQuery->whereNotIn('id', $outgoingIds);
            }

            $incomingHP = $incomingHPQuery->get();

            foreach ($incomingHP as $inc) {
                // Delete the IN log to keep history clean
                \App\Models\InventoryLog::where('product_id', $inc->product_id)
                    ->where('reference_id', 'like', "%$receiptId%")
                    ->delete();

                // Check if this incoming product detail is referenced as an outgoing unit in any other transactions
                $isReferenced = \App\Models\TukarTambah::where('outgoing_product_detail_id', $inc->id)
                    ->where('receipt_id', '!=', $receiptId)
                    ->exists()
                    || \App\Models\UnitExchange::where('outgoing_product_detail_id', $inc->id)
                    ->where('receipt_id', '!=', $receiptId)
                    ->exists()
                    || \App\Models\Downgrade::where('outgoing_product_detail_id', $inc->id)
                    ->where('receipt_id', '!=', $receiptId)
                    ->exists()
                    || DB::table('stock_out_items')
                    ->where('product_detail_id', $inc->id)
                    ->where('stock_out_id', '!=', $stockOut->id)
                    ->exists();

                if ($isReferenced) {
                    // It was an existing sold item that was traded back. Do NOT delete it.
                    // Just clear the current transaction link and revert status to sold.
                    $inc->update([
                        'status' => 'sold',
                        'tukar_tambah_id' => null,
                        'unit_exchange_id' => null,
                        'downgrade_id' => null,
                        'trade_in_id' => null,
                        'refund_id' => null,
                        'notes' => ($inc->notes ? $inc->notes . "\n" : "") . "Batal Tukar Tambah: " . $receiptId
                    ]);
                } else {
                    // It was newly created for this transaction. Safe to delete.
                    $inc->forceDelete();
                }
            }

            // 3. Soft delete associated transaction records to keep dashboards and active transaction lists clean
            \App\Models\TukarTambah::where('receipt_id', $receiptId)->delete();
            \App\Models\UnitExchange::where('receipt_id', $receiptId)->delete();
            \App\Models\Downgrade::where('receipt_id', $receiptId)->delete();
            \App\Models\TradeIn::where('receipt_id', $receiptId)->delete();
            \App\Models\Refund::where('receipt_id', $receiptId)->delete();

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
