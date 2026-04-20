<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use App\Models\StockOutNonHpItem;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\User;
use App\Models\AuditAnswer;
use App\Models\AuditProfit;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    /**
     * Get aggregated sales data for audit dashboard/reports.
     * Scoped to branches accessible by the user.
     */
    public function sales(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();
        $warehouseIds = $user->getAccessibleWarehouseIds();
        $distributorIds = $user->getAccessibleDistributorIds();

        if (empty($branchIds) && empty($onlineShopIds) && empty($warehouseIds) && empty($distributorIds)) {
            return response()->json([
                'daily_sales' => [],
                'brand_sales' => [],
                'cs_sales' => []
            ]);
        }

        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->start_date ?? $logicalNow->copy()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? $logicalNow->copy()->endOfMonth()->toDateString();

        // Role-based Date Restriction
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner'])) {
            $today = $logicalNow->toDateString();
            $sevenDaysAgo = $logicalNow->copy()->subDays(7)->toDateString();
            $startOfThisMonth = $logicalNow->copy()->startOfMonth()->toDateString();
            $startOfLastMonth = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();

            if ($startDate === $endDate) {
                // Daily view: allow up to 7 days back
                if ($startDate < $sevenDaysAgo) {
                    $startDate = $today;
                    $endDate = $today;
                }
            } else {
                // Range/Monthly view: only current and previous month
                if ($startDate < $startOfLastMonth) {
                    $startDate = $startOfThisMonth;
                    // Ensure end date also doesn't go too far back if they try to trick it
                    if ($endDate < $startOfThisMonth) {
                        $endDate = $logicalNow->copy()->endOfMonth()->toDateString();
                    }
                }
                
                // Extra safety: ensure they can't see previous years
                $currentYear = $logicalNow->format('Y');
                if (date('Y', strtotime($startDate)) < $currentYear) {
                    $startDate = $startOfThisMonth;
                }
            }
        }

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedDistributorId = $request->distributor_id;
        $requestedWarehouseId = $request->warehouse_id;

        $scopeToAccess = function ($query) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
            $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    if ($requestedBranchId) {
                        if (empty($branchIds) || in_array($requestedBranchId, $branchIds)) {
                            $sub->where('branch_id', $requestedBranchId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedOnlineShopId) {
                        if (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) {
                            $sub->where('online_shop_id', $requestedOnlineShopId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedWarehouseId) {
                        if (empty($warehouseIds) || in_array($requestedWarehouseId, $warehouseIds)) {
                            $sub->where('warehouse_id', $requestedWarehouseId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedDistributorId) {
                        if (empty($distributorIds) || in_array($requestedDistributorId, $distributorIds)) {
                            $sub->where('distributor_id', $requestedDistributorId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } else {
                        if (!empty($branchIds)) {
                            $sub->orWhereIn('branch_id', $branchIds);
                        }
                        if (!empty($onlineShopIds)) {
                            $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        }
                        if (!empty($warehouseIds)) {
                            $sub->orWhereIn('warehouse_id', $warehouseIds);
                        }
                        if (!empty($distributorIds)) {
                            $sub->orWhereIn('distributor_id', $distributorIds);
                        }
                    }
                });
            });
        };
        $requestedDistributorId = $request->distributor_id;
        $requestedCondition = $request->condition;
        $requestedCapacity = $request->capacity;

        $successCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'];
        $activityCategories = ['refund', 'angkat_barang'];
        $salesCategories = array_merge($successCategories, $activityCategories);
        // Excluded: 'pindah_cabang', 'retur', 'cancel_penjualan' per user request to clean up Sales Ranking

        // 1. Daily Sales
        $paymentMethods = \App\Models\PaymentMethod::all()->keyBy('id');

        // Load nonHpDetails relationship for Product details, but we will use JSON column for price
        $dailySalesQuery = StockOut::with(['items.product', 'nonHpDetails.product', 'user', 'inventoryUser', 'auditAnswers', 'paymentMethod'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        $scopeToAccess($dailySalesQuery);

        $paginatedSales = $dailySalesQuery->latest()->paginate(50);
        
        $dailySales = collect($paginatedSales->items())->map(function ($trx) use ($paymentMethods) {
            $details = [];
            $calculatedTotal = 0;

            $hpItems = $trx->items;
            $nonHpItems = $trx->nonHpDetails;

            $bundleHpId = null;
            $bundleNonHpId = null;

            // If it's a bundle, we group the first HP and first Non-HP into one line for the receipt
            if ($trx->is_bundle && ($hpItems->isNotEmpty() || $nonHpItems->isNotEmpty())) {
                $mainHp = $hpItems->first();
                $mainNonHp = $nonHpItems->first();

                $bundlePrice = 0;
                $bundleItemDiscount = 0;
                $bundleDistributedDiscount = 0;
                $bundleName = $trx->bundle_description ?: ($mainHp ? $mainHp->product->name . ' + BUNDLING' : 'PAKET BUNDLING');
                $bundleImei = $mainHp ? $mainHp->imei : '-';

                if ($mainHp) {
                    $pivot = $mainHp->pivot;
                    $sellingPrice = $pivot->selling_price ?? $mainHp->selling_price;
                    $itemDisc = ($pivot->item_discount ?? 0);
                    $bundlePrice += ($sellingPrice - $itemDisc);
                    $bundleHpId = $mainHp->id;
                }

                if ($mainNonHp) {
                    $itemDisc = ($mainNonHp->item_discount ?? 0);
                    $bundlePrice += ($mainNonHp->selling_price - $itemDisc);
                    $bundleNonHpId = $mainNonHp->id;
                }

                $details[] = [
                    'name' => $bundleName,
                    'qty' => 1,
                    'price' => $bundlePrice,
                    'item_discount' => 0, // Discounts are now reflected in 'price'
                    'distributed_discount' => 0,
                    'is_fixed' => true,
                    'type' => 'Bundle',
                    'imei' => $bundleImei,
                ];
                $calculatedTotal += $bundlePrice;
            }

            // 1. HP Items (process non-bundled ones)
            foreach ($hpItems as $item) {
                if ($item->id === $bundleHpId)
                    continue;

                $pivot = $item->pivot;
                $sellingPrice = $pivot->selling_price ?? $item->selling_price;
                $itemDiscount = $pivot->item_discount ?? 0;
                $netPrice = $sellingPrice - $itemDiscount;

                $details[] = [
                    'name' => $item->product->name ?? 'Unknown HP',
                    'qty' => 1,
                    'price' => $netPrice,
                    'item_discount' => 0,
                    'distributed_discount' => 0,
                    'is_fixed' => true,
                    'brand' => $item->product->brand ?? '-',
                    'type' => 'HP',
                    'imei' => $item->imei ?? '-',
                    'storage' => $item->storage ?? null,
                    'condition' => $item->condition === 'new' ? 'new' : ($item->condition === 'ex_ibox' ? 'ex_ibox' : ($item->condition ?? 'second')),
                ];
                $calculatedTotal += $netPrice;
            }

            // 2. Non-HP Items (process non-bundled ones or remaining quantity)
            foreach ($nonHpItems as $item) {
                $isMainNonHp = ($item->id === $bundleNonHpId);
                $qty = $isMainNonHp ? ($item->quantity - 1) : $item->quantity;

                if ($qty <= 0)
                    continue;

                $sellingPrice = $item->selling_price ?? 0;
                $itemDiscount = $item->item_discount ?? 0;
                $netPrice = $sellingPrice - $itemDiscount;

                $details[] = [
                    'name' => $item->product->name ?? 'Item Non-HP',
                    'qty' => $qty,
                    'price' => $netPrice,
                    'item_discount' => 0,
                    'distributed_discount' => 0,
                    'is_fixed' => true,
                    'brand' => $item->product->brand ?? '-',
                    'type' => 'Non-HP',
                    'category' => $item->product->non_imei_category ?? null,
                    'imei' => '-',
                ];
                $calculatedTotal += ($netPrice * $qty);
            }

            // 3. Final Adjustment / Gap Handling
            // [REMOVED] We no longer show "Diskon" as a row item per user request.
            // Any gaps will be handled by the "DISKON" field in the summary.

            // Calculate Global Discount explicitly for the summary
            $calculatedGlobalDiscount = 0;
            if ($trx->global_discount_type === 'percentage') {
                $calculatedGlobalDiscount = ($calculatedTotal * ($trx->global_discount_value ?? 0)) / 100;
            } else {
                $calculatedGlobalDiscount = $trx->global_discount_value ?? 0;
            }

            // Outlet Details
            $outletName = 'APEX POS';
            $outletAddress = 'Jl. Raya Example No. 123, Indonesia';

            $sourceUser = $trx->inventoryUser ?? $trx->user;

            if ($sourceUser) {
                if ($sourceUser->branch_id) {
                    $branch = \App\Models\Branch::find($sourceUser->branch_id);
                    if ($branch) {
                        $outletName = $branch->name;
                        $outletAddress = $branch->address ?? 'Alamat Cabang Belum Diatur';
                    }
                } elseif ($sourceUser->online_shop_id) {
                    $shop = \App\Models\OnlineShop::find($sourceUser->online_shop_id);
                    if ($shop) {
                        $outletName = $shop->name;
                        $addrParts = [];
                        if ($shop->platform)
                            $addrParts[] = ucfirst($shop->platform);
                        $outletAddress = !empty($addrParts) ? implode(' - ', $addrParts) : 'Toko Online';
                    }
                } elseif ($sourceUser->warehouse_id) {
                    $warehouse = \App\Models\Warehouse::find($sourceUser->warehouse_id);
                    if ($warehouse) {
                        $outletName = $warehouse->name;
                        $outletAddress = $warehouse->address ?? 'Alamat Gudang Belum Diatur';
                    }
                }
            }

            // Audit score calculation (must match getChecklist logic for edited questions)
            $currentQuestions = Question::where('category', $trx->category)->get();
            $answers = $trx->auditAnswers;
            $yesCount = $answers->where('answer', true)->count();
            $totalQuestions = $currentQuestions->count();

            // Count edited questions (answered but content changed) as additional unanswered
            foreach ($currentQuestions as $cq) {
                $existingAns = $answers->firstWhere('question_id', $cq->id);
                if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $cq->content) {
                    $totalQuestions++; // edited question = +1 unanswered
                }
            }
            // Count orphaned answers (deleted questions) still in the total
            foreach ($answers as $ans) {
                if ($ans->question_id === null || !$currentQuestions->contains('id', $ans->question_id)) {
                    $totalQuestions++;
                }
            }

            $auditScore = null;
            if ($answers->count() > 0 && $totalQuestions > 0) {
                $auditScore = round(($yesCount / $totalQuestions) * 100);
            }

            // 4. Payment Breakdown
            $processedSplitPayments = [];
            $cash = 0;
            $transfer = 0;
            $edc = 0;
            if ($trx->split_payments) {
                $splits = is_string($trx->split_payments) ? json_decode($trx->split_payments, true) : $trx->split_payments;
                if (is_array($splits)) {
                    foreach ($splits as $sp) {
                        $methodId = $sp['payment_method_id'] ?? ($sp['method_id'] ?? ($sp['id'] ?? ($sp['method'] ?? null)));
                        $amount = floatval($sp['amount'] ?? ($sp['paid'] ?? 0));

                        $methodName = 'Unknown';
                        if ($methodId && isset($paymentMethods[$methodId])) {
                            $method = $paymentMethods[$methodId];
                            $methodName = $method->name;
                            $cat = trim(strtolower($method->category ?? ''));
                            $name = trim(strtolower($method->name ?? ''));

                            if ($cat === 'tunai' || $cat === 'cash' || str_contains($name, 'cash') || str_contains($name, 'tunai')) {
                                $cash += $amount;
                            } elseif ($cat === 'edc' || $cat === 'debit' || str_contains($name, 'edc') || str_contains($name, 'debit')) {
                                $edc += $amount;
                            } else {
                                $transfer += $amount;
                            }
                        } else {
                            $transfer += $amount;
                        }

                        $processedSplitPayments[] = [
                            'method_name' => $methodName,
                            'amount' => $amount
                        ];
                    }
                }
            } else {
                // Fallback for older transactions without split_payments or simple single-payment transactions
                $methodCat = trim(strtolower($trx->paymentMethod->category ?? ''));
                $methodName = trim(strtolower($trx->paymentMethod->name ?? ''));
                
                if ($methodCat === 'tunai' || $methodCat === 'cash' || str_contains($methodName, 'cash') || str_contains($methodName, 'tunai') || ($trx->category === 'penjualan_offline' && !$methodCat)) {
                    $cash = $trx->selling_price;
                } elseif ($methodCat === 'edc' || $methodCat === 'debit' || str_contains($methodName, 'edc') || str_contains($methodName, 'debit')) {
                    $edc = $trx->selling_price;
                } else {
                    $transfer = $trx->selling_price;
                }
            }

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'order_no' => $trx->receipt_id,
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? $trx->shopee_receiver ?? $trx->giveaway_receiver ?? '-',
                'customer_phone' => $trx->customer_phone ?? $trx->shopee_phone ?? $trx->giveaway_phone ?? '-',
                'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                'brand_names' => collect()->concat($trx->items->map(fn($i) => $i->product?->brand ?? '-'))->concat($trx->nonHpDetails->map(fn($i) => $i->product?->brand ?? '-'))->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-',
                'product_names' => collect()->concat($trx->items->map(fn($i) => $i->product?->name ?? '-'))->concat($trx->nonHpDetails->map(fn($i) => $i->product?->name ?? '-'))->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: ($trx->is_bundle ? $trx->bundle_description : '-'),
                'imeis' => $trx->items->map(fn($i) => $i->imei)->filter()->implode(', ') ?: '-',
                'storages' => $trx->items->map(fn($i) => $i->ram && $i->storage ? $i->ram . '/' . $i->storage : $i->storage)->filter()->unique()->implode(', ') ?: null,
                'conditions' => $trx->items->map(fn($i) => match ($i->condition) { 'new' => 'Baru', 'ex_ibox' => 'Ex iBox', default => 'Second'})->filter()->unique()->implode(', ') ?: null,
                'qty' => $trx->items->count() + ($trx->non_hp_items ? collect($trx->non_hp_items)->sum('quantity') : ($trx->nonHpDetails ? $trx->nonHpDetails->sum('quantity') : 0)),
                'items' => $details,
                'status' => $trx->status === 'received' ? 'Lunas' : 'Pending',
                'payment_method' => $trx->paymentMethod->name ?? ($trx->category === 'penjualan_offline' ? 'Offline' : 'Online'),
                'payment_method_name' => $trx->paymentMethod->name ?? null,
                'split_payments_data' => $processedSplitPayments,
                'cash' => $cash,
                'transfer' => $transfer,
                'edc' => $edc,
                'grand_total' => $trx->selling_price, // Final Paid Amount
                'total_discount' => $calculatedGlobalDiscount,
                'global_discount_value' => $calculatedGlobalDiscount,
                'global_discount_type' => 'fixed',
                'original_price' => $trx->selling_price + $calculatedGlobalDiscount,
                'outlet_name' => $outletName,
                'outlet_address' => $outletAddress,
                'customer_wa' => $trx->customer_wa,
                'notes' => $trx->notes,
                'sales_account' => $trx->sales_account,
                'inventory_user_name' => $trx->inventoryUser->full_name ?? ($trx->inventoryUser->name ?? ($trx->user->full_name ?? ($trx->user->name ?? '-'))),
                'inventory_account_name' => $trx->inventoryUser->full_name ?? ($trx->inventoryUser->name ?? null),
                'transaction_pin' => (string)$trx->transaction_pin === '9090' ? '9090' : null,
                'audit_score' => $auditScore,
                'audit_answered' => $trx->auditAnswers->count(),
                'audit_total' => $totalQuestions,
                'audit_yes' => $yesCount,
                'proof_image' => $trx->proof_image ? asset('storage/' . $trx->proof_image) : null,
            ];
        });

        // 2. Report per Brand (Detailed for Hierarchy)
        $brandDetailedStats = [];
        
        // HP Items Detailed
        $hpQueryDetailed = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate]);

        if ($request->condition) $hpQueryDetailed->where('product_details.condition', $request->condition);
        if ($request->product_type_id) $hpQueryDetailed->where('products.id', $request->product_type_id);
        if ($request->capacity) $hpQueryDetailed->where('product_details.storage', $request->capacity);
        if ($request->distributor_id) $hpQueryDetailed->where('product_details.distributor_id', $request->distributor_id);

        $hpQueryDetailed->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId) {
                    $q->where('users.branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $q->where('users.online_shop_id', $requestedOnlineShopId);
                } else {
                    if (!empty($branchIds))
                        $q->orWhereIn('users.branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                }
            });
            
        $hpDetailedResults = $hpQueryDetailed->select(
                'products.brand', 
                'products.name', 
                'product_details.condition', 
                'product_details.storage',
                'distributors.name as distributor_name',
                DB::raw('count(*) as count')
            )
            ->leftJoin('distributors', 'product_details.distributor_id', '=', 'distributors.id')
            ->groupBy('products.brand', 'products.name', 'product_details.condition', 'product_details.storage', 'distributors.name')
            ->get();

        foreach ($hpDetailedResults as $item) {
            $brandDetailedStats[] = [
                'brand' => $item->brand ?? 'Lainnya',
                'name' => $item->name ?? 'Unknown',
                'condition' => $item->condition ?? 'unknown',
                'storage' => $item->storage ?? '-',
                'distributor' => $item->distributor_name ?? 'Tanpa Distributor',
                'qty' => $item->count,
                'is_hp' => true
            ];
        }

        // Non-HP Detailed
        $nhpQueryDetailed = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
            ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId) {
                    $q->where('users.branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $q->where('users.online_shop_id', $requestedOnlineShopId);
                } else {
                    if (!empty($branchIds))
                        $q->orWhereIn('users.branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                }
            })
            ->select('products.brand', 'products.name', DB::raw('sum(quantity) as count'))
            ->groupBy('products.brand', 'products.name')
            ->get();

        foreach ($nhpQueryDetailed as $item) {
            $brandDetailedStats[] = [
                'brand' => $item->brand ?? 'Lainnya',
                'name' => $item->name ?? 'Unknown',
                'condition' => '-',
                'storage' => '-',
                'distributor' => '-',
                'qty' => (int) $item->count,
                'is_hp' => false
            ];
        }

        $formattedBrandSales = $brandDetailedStats;

        // 3. Report per CS (Inventory Account)
        $csQuery = StockOut::whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        if ($requestedDistributorId || $requestedCondition || $requestedCapacity || $request->product_type_id) {
            $csQuery->whereExists(function ($sub) use ($requestedDistributorId, $requestedCondition, $requestedCapacity, $request) {
                $sub->select(DB::raw(1))
                    ->from('stock_out_items')
                    ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                    ->whereColumn('stock_out_items.stock_out_id', 'stock_outs.id');
                if ($requestedDistributorId) $sub->where('product_details.distributor_id', $requestedDistributorId);
                if ($requestedCondition) $sub->where('product_details.condition', $requestedCondition);
                if ($requestedCapacity) $sub->where('product_details.storage', $requestedCapacity);
                if ($request->product_type_id) $sub->where('product_details.product_id', $request->product_type_id);
            });
        }
        
        $scopeToAccess($csQuery);

        $csSales = $csQuery
            ->leftJoin('users as owners', function($join) {
                $join->on('owners.id', '=', DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id)'));
            })
            ->select(
                'owners.id as owner_id',
                'owners.name as owner_name',
                'owners.full_name as owner_full_name',
                'owners.photo as owner_photo',
                'owners.photo_inventory as owner_photo_inv',
                DB::raw("sum(case 
                    when stock_outs.category in ('" . implode("','", $successCategories) . "') then stock_outs.selling_price 
                    when stock_outs.category = 'refund' then -stock_outs.selling_price
                    else 0 
                end) as total_revenue"),
                DB::raw("sum(case when stock_outs.category = 'tukar_tambah' or stock_outs.category = 'tukar_unit' or stock_outs.category = 'angkat_barang' or stock_outs.category = 'downgrade' then 1 else 0 end) as angkat_barang_count"),
                DB::raw("sum(case when stock_outs.category = 'refund' then 1 else 0 end) as refund_count")
            )
            ->groupBy('owners.id', 'owners.name', 'owners.full_name', 'owners.photo', 'owners.photo_inventory')
            ->get()
            ->map(function ($item) use ($startDate, $endDate, $successCategories, $requestedDistributorId, $requestedCondition, $requestedCapacity, $request) {
                $ownerId = $item->owner_id;
                $catList = "'" . implode("','", $successCategories) . "'";
                
                // Build a single consistent filter for product_details
                $pdJoin = " JOIN product_details pd ON stock_out_items.product_detail_id = pd.id ";
                $pdWhere = "";
                $needsPdJoin = false;
                
                if ($requestedDistributorId) {
                    $pdWhere .= " AND pd.distributor_id = $requestedDistributorId ";
                    $needsPdJoin = true;
                }
                if ($requestedCondition) {
                    $pdWhere .= " AND pd.condition = '$requestedCondition' ";
                    $needsPdJoin = true;
                }
                if ($requestedCapacity) {
                    $pdWhere .= " AND pd.storage = '$requestedCapacity' ";
                    $needsPdJoin = true;
                }
                if ($request->product_type_id) {
                    $pdWhere .= " AND pd.product_id = " . intval($request->product_type_id) . " ";
                    $needsPdJoin = true;
                }
                
                $joinStr = $needsPdJoin ? $pdJoin : "";
                $stockSubquery = "SELECT id FROM stock_outs as s2 WHERE s2.reporting_date BETWEEN '$startDate' AND '$endDate' AND COALESCE(s2.inventory_user_id, s2.user_id) = $ownerId AND s2.category IN ($catList) AND s2.deleted_at IS NULL";
                
                $hpSql = "(SELECT count(*) FROM stock_out_items $joinStr WHERE stock_out_items.stock_out_id IN ($stockSubquery) $pdWhere) as hp_units";
                $nhpSql = "(SELECT COALESCE(sum(quantity), 0) FROM stock_out_non_hp_items WHERE stock_out_id IN ($stockSubquery)) as nhp_units";
                $iphoneSql = "(SELECT count(*) FROM stock_out_items 
                    JOIN product_details pd2 ON stock_out_items.product_detail_id = pd2.id
                    JOIN products ON pd2.product_id = products.id
                    WHERE stock_out_items.stock_out_id IN ($stockSubquery)
                    " . str_replace('pd.', 'pd2.', $pdWhere) . "
                    AND (LOWER(products.brand) LIKE '%apple%' OR LOWER(products.brand) LIKE '%iphone%')
                ) as iphone_units";
                
                $units = DB::select("SELECT $hpSql, $nhpSql, $iphoneSql")[0] ?? null;

                $totalHp = (int) ($units->hp_units ?? 0);
                $totalNonHp = (int) ($units->nhp_units ?? 0);
                $iphoneUnits = (int) ($units->iphone_units ?? 0);

                return [
                    'owner_id' => $item->owner_id,
                    'cs_name' => $item->owner_full_name ?? $item->owner_name ?? 'Unknown',
                    'photo' => $item->owner_photo_inv ?? $item->owner_photo ?? null,
                    'total_sales' => $totalHp + $totalNonHp,
                    'iphone_units' => $iphoneUnits,
                    'android_units' => $totalHp - $iphoneUnits,
                    'non_hp_units' => $totalNonHp,
                    'total_refund' => (int) $item->refund_count,
                    'total_angkat_barang' => (int) $item->angkat_barang_count,
                    'grand_total' => (float) $item->total_revenue
                ];
            });

        // 4. Report per Type
        $typeStats = [];
        $hpTypeQuery = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
            ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId) {
                    $q->where('users.branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $q->where('users.online_shop_id', $requestedOnlineShopId);
                } else {
                    if (!empty($branchIds))
                        $q->orWhereIn('users.branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                }
            })
            ->where(function ($q) use ($requestedDistributorId, $requestedCondition, $requestedCapacity) {
                if ($requestedDistributorId) $q->where('product_details.distributor_id', $requestedDistributorId);
                if ($requestedCondition) $q->where('product_details.condition', $requestedCondition);
                if ($requestedCapacity) $q->where('product_details.storage', $requestedCapacity);
            })
            ->select('products.name', 'products.brand', DB::raw('count(*) as count'))
            ->groupBy('products.name', 'products.brand')
            ->get();

        foreach ($hpTypeQuery as $item) {
            $typeStats[] = ['name' => $item->name, 'brand' => $item->brand, 'qty' => $item->count];
        }

        // 5. Report per Condition
        $conditionStats = [];
        $hpCondQuery = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
            ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId) {
                    $q->where('users.branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $q->where('users.online_shop_id', $requestedOnlineShopId);
                } else {
                    if (!empty($branchIds))
                        $q->orWhereIn('users.branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                }
            })
            ->where(function ($q) use ($requestedDistributorId, $requestedCapacity, $request) {
                if ($requestedDistributorId) $q->where('product_details.distributor_id', $requestedDistributorId);
                if ($requestedCapacity) $q->where('product_details.storage', $requestedCapacity);
                if ($request->product_type_id) $q->where('product_details.product_id', $request->product_type_id);
            })
            ->select('product_details.condition', DB::raw('count(*) as count'))
            ->groupBy('product_details.condition')
            ->get();

        foreach ($hpCondQuery as $item) {
            $conditionStats[] = ['condition' => $item->condition, 'qty' => $item->count];
        }

        // 6. Daily History (Total Omset per Day)
        $historyQuery = StockOut::whereIn('category', $successCategories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        if ($requestedDistributorId || $requestedCondition || $requestedCapacity || $request->product_type_id) {
            $historyQuery->whereExists(function ($sub) use ($requestedDistributorId, $requestedCondition, $requestedCapacity, $request) {
                $sub->select(DB::raw(1))
                    ->from('stock_out_items')
                    ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                    ->whereColumn('stock_out_items.stock_out_id', 'stock_outs.id');
                if ($requestedDistributorId) $sub->where('product_details.distributor_id', $requestedDistributorId);
                if ($requestedCondition) $sub->where('product_details.condition', $requestedCondition);
                if ($requestedCapacity) $sub->where('product_details.storage', $requestedCapacity);
                if ($request->product_type_id) $sub->where('product_details.product_id', $request->product_type_id);
            });
        }

        $scopeToAccess($historyQuery);
        $dailyHistory = $historyQuery->select(
                'reporting_date',
                DB::raw('sum(selling_price) as total_omset')
            )
            ->groupBy('reporting_date')
            ->orderByDesc('reporting_date')
            ->get()
            ->map(function ($item) use ($successCategories, $requestedDistributorId, $requestedCondition, $requestedCapacity, $request) {
                $catList = "'" . implode("','", $successCategories) . "'";
                $reportDate = $item->reporting_date;
                
                $pdJoin = " JOIN product_details pd ON stock_out_items.product_detail_id = pd.id ";
                $pdWhere = "";
                $needsPdJoin = false;
                
                if ($requestedDistributorId) {
                    $pdWhere .= " AND pd.distributor_id = $requestedDistributorId ";
                    $needsPdJoin = true;
                }
                if ($requestedCondition) {
                    $pdWhere .= " AND pd.condition = '$requestedCondition' ";
                    $needsPdJoin = true;
                }
                if ($requestedCapacity) {
                    $pdWhere .= " AND pd.storage = '$requestedCapacity' ";
                    $needsPdJoin = true;
                }
                if ($request->product_type_id) {
                    $pdWhere .= " AND pd.product_id = " . intval($request->product_type_id) . " ";
                    $needsPdJoin = true;
                }
                
                $joinStr = $needsPdJoin ? $pdJoin : "";
                $stockSubquery = "SELECT id FROM stock_outs as s2 WHERE s2.reporting_date = '$reportDate' AND s2.category IN ($catList) AND s2.deleted_at IS NULL";
                
                $hpSql = "(SELECT count(*) FROM stock_out_items $joinStr WHERE stock_out_items.stock_out_id IN ($stockSubquery) $pdWhere) as hp_units";
                $nhpSql = "(SELECT COALESCE(sum(quantity), 0) FROM stock_out_non_hp_items WHERE stock_out_id IN ($stockSubquery)) as nhp_units";
                $iphoneSql = "(SELECT count(*) FROM stock_out_items 
                    JOIN product_details pd2 ON stock_out_items.product_detail_id = pd2.id
                    JOIN products ON pd2.product_id = products.id
                    WHERE stock_out_items.stock_out_id IN ($stockSubquery)
                    " . str_replace('pd.', 'pd2.', $pdWhere) . "
                    AND (LOWER(products.brand) LIKE '%apple%' OR LOWER(products.brand) LIKE '%iphone%')
                ) as iphone_units";
                
                $units = DB::select("SELECT $hpSql, $nhpSql, $iphoneSql")[0] ?? null;

                $totalHp = (int) ($units->hp_units ?? 0);
                $totalNonHp = (int) ($units->nhp_units ?? 0);
                $iphoneUnits = (int) ($units->iphone_units ?? 0);

                return [
                    'reporting_date' => $item->reporting_date,
                    'total_omset' => (float) $item->total_omset,
                    'total_units' => $totalHp + $totalNonHp,
                    'iphone_units' => $iphoneUnits,
                    'android_units' => $totalHp - $iphoneUnits,
                    'non_hp_units' => $totalNonHp
                ];
            });

        // 7. Report per Distributor
        $distributorStatsRaw = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->leftJoin('distributors', 'product_details.distributor_id', '=', 'distributors.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
            ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId) {
                    $q->where('users.branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $q->where('users.online_shop_id', $requestedOnlineShopId);
                } else {
                    if (!empty($branchIds))
                        $q->orWhereIn('users.branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                }
            })
            ->select(
                DB::raw("COALESCE(distributors.name, 'Tanpa Distributor') as distributor"),
                'products.brand',
                'products.name as product_type',
                'product_details.condition',
                'product_details.storage',
                DB::raw('count(*) as qty')
            )
            ->groupBy('distributor', 'products.brand', 'product_type', 'product_details.condition', 'product_details.storage')
            ->get();
        
        $distributorStats = $distributorStatsRaw;

        // 8. Get sold product types for filter dropdown
        $soldProducts = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
            ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId) {
                    $q->where('users.branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $q->where('users.online_shop_id', $requestedOnlineShopId);
                } else {
                    if (!empty($branchIds))
                        $q->orWhereIn('users.branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                }
            })
            ->select('products.id', 'products.name', 'products.brand')
            ->distinct()
            ->orderBy('products.name')
            ->get();

        // 9. Get distributors used in sales for filter dropdown
        $soldDistributors = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('distributors', 'product_details.distributor_id', '=', 'distributors.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
            ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId) {
                    $q->where('users.branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $q->where('users.online_shop_id', $requestedOnlineShopId);
                } else {
                    if (!empty($branchIds))
                        $q->orWhereIn('users.branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                }
            })
            ->select('distributors.id', 'distributors.name')
            ->distinct()
            ->orderBy('distributors.name')
            ->get();

        return response()->json([
            'daily_sales' => [
                'data' => $dailySales,
                'current_page' => $paginatedSales->currentPage(),
                'last_page' => $paginatedSales->lastPage(),
                'total' => $paginatedSales->total(),
                'per_page' => $paginatedSales->perPage(),
            ],
            'brand_sales' => $formattedBrandSales,
            'type_sales' => $typeStats,
            'condition_sales' => $conditionStats,
            'distributor_sales' => $distributorStats,
            'cs_sales' => $csSales,
            'daily_history' => $dailyHistory,
            'filter_options' => [
                'products' => $soldProducts,
                'distributors' => $soldDistributors,
            ]
        ]);
    }

    public function inventory(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();
        $warehouseIds = $user->getAccessibleWarehouseIds();
        $distributorIds = $user->getAccessibleDistributorIds();

        if (empty($branchIds) && empty($onlineShopIds) && empty($warehouseIds) && empty($distributorIds)) {
            return response()->json([
                'stock' => 0,
                'stock_hp' => 0,
                'stock_non_hp' => 0,
                'in' => 0,
                'in_hp' => 0,
                'in_non_hp' => 0,
                'out' => 0,
                'out_hp' => 0,
                'out_non_hp' => 0,
            ]);
        }

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;
        $requestedDistributorId = $request->distributor_id;

        // Filter assignments based on request
        if ($requestedBranchId) {
            $branchIds = (empty($branchIds) || in_array($requestedBranchId, $branchIds)) ? [$requestedBranchId] : [];
            $onlineShopIds = []; $warehouseIds = []; $distributorIds = [];
        } elseif ($requestedOnlineShopId) {
            $onlineShopIds = (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) ? [$requestedOnlineShopId] : [];
            $branchIds = []; $warehouseIds = []; $distributorIds = [];
        } elseif ($requestedWarehouseId) {
            $warehouseIds = (empty($warehouseIds) || in_array($requestedWarehouseId, $warehouseIds)) ? [$requestedWarehouseId] : [];
            $branchIds = []; $onlineShopIds = []; $distributorIds = [];
        } elseif ($requestedDistributorId) {
            $distributorIds = (empty($distributorIds) || in_array($requestedDistributorId, $distributorIds)) ? [$requestedDistributorId] : [];
            $branchIds = []; $onlineShopIds = []; $warehouseIds = [];
        }

        if (empty($branchIds) && empty($onlineShopIds) && empty($warehouseIds) && empty($distributorIds)) {
            return response()->json([
                'stock' => 0,
                'stock_hp' => 0,
                'stock_non_hp' => 0,
                'in' => 0,
                'in_hp' => 0,
                'in_non_hp' => 0,
                'out' => 0,
                'out_hp' => 0,
                'out_non_hp' => 0,
            ]);
        }

        // 1. Stock (Available Items)
        // HP
        $hpStock = ProductDetail::where('status', 'available')
            ->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                if (!empty($branchIds)) {
                    $q->orWhere(function ($sub) use ($branchIds) {
                        $sub->where('placement_type', 'branch')->whereIn('placement_id', $branchIds);
                    });
                }
                if (!empty($onlineShopIds)) {
                    $q->orWhere(function ($sub) use ($onlineShopIds) {
                        $sub->where('placement_type', 'online_shop')->whereIn('placement_id', $onlineShopIds);
                    });
                }
                if (!empty($warehouseIds)) {
                    $q->orWhere(function ($sub) use ($warehouseIds) {
                        $sub->where('placement_type', 'warehouse')->whereIn('placement_id', $warehouseIds);
                    });
                }
                if (!empty($distributorIds)) {
                    $q->orWhere(function ($sub) use ($distributorIds) {
                        $sub->where('placement_type', 'distributor')->whereIn('placement_id', $distributorIds);
                    });
                }
            })
            ->count();

        // Non-HP
        $nonHpStockQuery = \App\Models\Inventory::query();
        $nonHpStockQuery->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
            if (!empty($branchIds)) {
                $q->orWhere(function ($sub) use ($branchIds) {
                    $sub->where('placement_type', 'branch')->whereIn('placement_id', $branchIds);
                });
            }
            if (!empty($onlineShopIds)) {
                $q->orWhere(function ($sub) use ($onlineShopIds) {
                    $sub->where('placement_type', 'online_shop')->whereIn('placement_id', $onlineShopIds);
                });
            }
            if (!empty($warehouseIds)) {
                $q->orWhere(function ($sub) use ($warehouseIds) {
                    $sub->where('placement_type', 'warehouse')->whereIn('placement_id', $warehouseIds);
                });
            }
            if (!empty($distributorIds)) {
                $q->orWhere(function ($sub) use ($distributorIds) {
                    $sub->where('placement_type', 'distributor')->whereIn('placement_id', $distributorIds);
                });
            }
        });
        $nonHpStock = (int) $nonHpStockQuery->sum('quantity');

        $totalStock = $hpStock + $nonHpStock;

        // 2. Stock In (Incoming Transfers that are Received)
        // Helper to scope StockOut (Transfers) by Destination
        $scopeIn = function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
            $q->where('stock_outs.status', 'received')
                ->whereMonth('stock_outs.reporting_date', now()->month)
                ->whereYear('stock_outs.reporting_date', now()->year)
                ->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                    if (!empty($branchIds)) {
                        $sub->orWhere(function ($deep) use ($branchIds) {
                            $deep->where('stock_outs.destination_type', 'branch')->whereIn('stock_outs.destination_id', $branchIds);
                        });
                    }
                    if (!empty($onlineShopIds)) {
                        $sub->orWhere(function ($deep) use ($onlineShopIds) {
                            $deep->where('stock_outs.destination_type', 'online_shop')->whereIn('stock_outs.destination_id', $onlineShopIds);
                        });
                    }
                    if (!empty($warehouseIds)) {
                        $sub->orWhere(function ($deep) use ($warehouseIds) {
                            $deep->where('stock_outs.destination_type', 'warehouse')->whereIn('stock_outs.destination_id', $warehouseIds);
                        });
                    }
                    if (!empty($distributorIds)) {
                        $sub->orWhere(function ($deep) use ($distributorIds) {
                            $deep->where('stock_outs.destination_type', 'distributor')->whereIn('stock_outs.destination_id', $distributorIds);
                        });
                    }
                });
        };

        // HP In
        $inHp = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->where(function ($q) use ($scopeIn) {
                $scopeIn($q);
            })
            ->count();

        // Non-HP In
        $inNonHp = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->where(function ($q) use ($scopeIn) {
                $scopeIn($q);
            })
            ->sum('stock_out_non_hp_items.quantity');

        $totalIn = $inHp + $inNonHp;


        // 3. Stock Out (Sales + Transfers Out)
        // Helper to scope StockOut by Source
        $scopeOut = function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
            $q->whereMonth('stock_outs.reporting_date', now()->month)
                ->whereYear('stock_outs.reporting_date', now()->year)
                ->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                    if (!empty($branchIds)) {
                        $sub->orWhereIn('users.branch_id', $branchIds);
                    }
                    if (!empty($onlineShopIds)) {
                        $sub->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                    if (!empty($warehouseIds)) {
                        $sub->orWhereIn('users.warehouse_id', $warehouseIds);
                    }
                    if (!empty($distributorIds)) {
                        $sub->orWhereIn('users.distributor_id', $distributorIds);
                    }
                });
        };

        // HP Out
        $outHp = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereMonth('stock_outs.reporting_date', now()->month)
            ->whereYear('stock_outs.reporting_date', now()->year)
            ->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                if (!empty($branchIds))
                    $q->orWhereIn('users.branch_id', $branchIds);
                if (!empty($onlineShopIds))
                    $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                if (!empty($warehouseIds))
                    $q->orWhereIn('users.warehouse_id', $warehouseIds);
                if (!empty($distributorIds))
                    $q->orWhereIn('users.distributor_id', $distributorIds);
            })
            ->count();

        // Non-HP Out
        $outNonHp = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereMonth('stock_outs.reporting_date', now()->month)
            ->whereYear('stock_outs.reporting_date', now()->year)
            ->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                if (!empty($branchIds))
                    $q->orWhereIn('users.branch_id', $branchIds);
                if (!empty($onlineShopIds))
                    $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                if (!empty($warehouseIds))
                    $q->orWhereIn('users.warehouse_id', $warehouseIds);
                if (!empty($distributorIds))
                    $q->orWhereIn('users.distributor_id', $distributorIds);
            })
            ->sum('stock_out_non_hp_items.quantity');

        $totalOut = $outHp + $outNonHp;

        return response()->json([
            'stock' => $totalStock,
            'stock_hp' => $hpStock,
            'stock_non_hp' => $nonHpStock,
            'in' => $totalIn,
            'in_hp' => $inHp,
            'in_non_hp' => (int) $inNonHp,
            'out' => $totalOut,
            'out_hp' => $outHp,
            'out_non_hp' => (int) $outNonHp
        ]);
    }

    /**
     * Track Item by IMEI/SKU
     */
    public function track(Request $request)
    {
        $search = $request->query('q');
        if (!$search)
            return response()->json([]);

        // Search ProductDetails (HP)
        $items = ProductDetail::with(['product'])
            ->where('imei', 'like', "%$search%")
            ->take(20)
            ->get()
            ->map(function ($item) {
                $loc = '-';
                if ($item->placement_type === 'branch') {
                    $loc = \App\Models\Branch::find($item->placement_id)?->name ?? 'Branch ' . $item->placement_id;
                } elseif ($item->placement_type === 'warehouse') {
                    $loc = \App\Models\Warehouse::find($item->placement_id)?->name ?? 'Gudang ' . $item->placement_id;
                } elseif ($item->placement_type === 'online_shop') {
                    $loc = \App\Models\OnlineShop::find($item->placement_id)?->name ?? 'Online Shop ' . $item->placement_id;
                }

                return [
                    'id' => $item->id,
                    'name' => $item->product->name ?? '-',
                    'imei' => $item->imei,
                    'status' => $item->status,
                    'location' => $loc,
                    'type' => 'HP'
                ];
            });

        return response()->json($items);
    }

    public function analysis(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();
        $warehouseIds = $user->getAccessibleWarehouseIds();
        $distributorIds = $user->getAccessibleDistributorIds();

        if (empty($branchIds) && empty($onlineShopIds) && empty($warehouseIds) && empty($distributorIds)) {
            return response()->json([
                'profit_trend' => [],
                'sales_breakdown' => [],
                'summary' => [
                    'total_profit' => 0,
                    'total_revenue' => 0,
                    'total_items' => 0
                ]
            ]);
        }

        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $year = $request->year ?? $logicalNow->format('Y');
        $month = $request->month; // Optional

        // Role-based Date Restriction
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner'])) {
            $currentYear = $logicalNow->format('Y');
            $currentMonth = (int)$logicalNow->format('n');
            $prevMonth = $currentMonth === 1 ? 12 : $currentMonth - 1;
            // If restricted and restricted to current year, prevMonthYear must also be restricted to current year
            // But if it's January, then last month was last year.
            // Following 'only current year' literally:
            $prevMonthYear = $currentMonth === 1 ? $currentYear : $currentYear; 

            // Enforce current year
            $year = $currentYear;

            // Enforce this month or last month
            if ($month) {
                $month = (int)$month;
                if ($month !== $currentMonth && ($month !== $prevMonth || $year !== $prevMonthYear)) {
                    $month = $currentMonth;
                }
            }
        }

        // Base Query Categories
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        // 1. Profit Trend (Daily)
        $dailyStats = [];

        // Query StockOut (Transactions)
        $query = StockOut::with(['items', 'nonHpItems', 'user', 'inventoryUser'])
            ->whereIn('category', $salesCategories)
            ->whereYear('reporting_date', $year);

        if ($request->date) {
            $query->where('reporting_date', $request->date);
        } elseif ($month) {
            $query->whereMonth('reporting_date', $month);
        }

        // Scope to user access & location filter
        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;
        $requestedDistributorId = $request->distributor_id;

        $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
            $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                if ($requestedBranchId) {
                    if (empty($branchIds) || in_array($requestedBranchId, $branchIds)) {
                        $sub->where('branch_id', $requestedBranchId);
                    } else {
                        $sub->whereRaw('1=0');
                    }
                } elseif ($requestedOnlineShopId) {
                    if (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) {
                        $sub->where('online_shop_id', $requestedOnlineShopId);
                    } else {
                        $sub->whereRaw('1=0');
                    }
                } elseif ($requestedWarehouseId) {
                    if (empty($warehouseIds) || in_array($requestedWarehouseId, $warehouseIds)) {
                        $sub->where('warehouse_id', $requestedWarehouseId);
                    } else {
                        $sub->whereRaw('1=0');
                    }
                } elseif ($requestedDistributorId) {
                    if (empty($distributorIds) || in_array($requestedDistributorId, $distributorIds)) {
                        $sub->where('distributor_id', $requestedDistributorId);
                    } else {
                        $sub->whereRaw('1=0');
                    }
                } else {
                    if (!empty($branchIds)) $sub->orWhereIn('branch_id', $branchIds);
                    if (!empty($onlineShopIds)) $sub->orWhereIn('online_shop_id', $onlineShopIds);
                    if (!empty($warehouseIds)) $sub->orWhereIn('warehouse_id', $warehouseIds);
                    if (!empty($distributorIds)) $sub->orWhereIn('distributor_id', $distributorIds);
                }
            });
        });

        $transactions = $query->oldest()->get();

        $totalRevenue = 0;
        $totalCost = 0;
        $totalItems = 0;

        // Breakdown Stats
        $breakdown = [];

        foreach ($transactions as $trx) {
            $date = $trx->reporting_date;

            // Calculate Cost
            $trxCost = 0;
            $trxItems = 0;

            // HP Items Cost
            foreach ($trx->items as $item) {
                $trxCost += $item->cost_price;
                $trxItems++;
            }

            // Non-HP Items Cost (Limitation: Assuming 0 or need product reference)
            foreach ($trx->nonHpItems as $nhp) {
                $trxItems += $nhp->quantity;
            }

            $profit = $trx->selling_price - $trxCost;

            // Update Total Stats
            $totalRevenue += $trx->selling_price;
            $totalCost += $trxCost;
            $totalItems += $trxItems;

            // Daily Stats for Trend Chart
            if (!isset($dailyStats[$date])) {
                $dailyStats[$date] = [
                    'date' => $date,
                    'profit' => 0,
                    'revenue' => 0,
                    'items' => 0
                ];
            }
            $dailyStats[$date]['profit'] += $profit;
            $dailyStats[$date]['revenue'] += $trx->selling_price;
            $dailyStats[$date]['items'] += $trxItems;

            // Breakdown by CS (Akun Inventory)
            $sourceName = 'Unknown CS';

            if ($trx->inventoryUser) {
                $sourceName = $trx->inventoryUser->full_name;
            } elseif ($trx->user) {
                $sourceName = $trx->user->full_name;
            }

            if (!isset($breakdown[$sourceName])) {
                $breakdown[$sourceName] = [
                    'name' => $sourceName,
                    'profit' => 0,
                    'revenue' => 0,
                    'items' => 0
                ];
            }
            $breakdown[$sourceName]['profit'] += $profit;
            $breakdown[$sourceName]['revenue'] += $trx->selling_price;
            $breakdown[$sourceName]['items'] += $trxItems;
        }

        // Daily Comparison Logic (if date provided)
        $comparison = null;
        if ($request->date) {
            $targetDate = $request->date;
            $prevDate = date('Y-m-d', strtotime($targetDate . ' -1 day'));

            $targetStats = $dailyStats[$targetDate] ?? ['profit' => 0, 'revenue' => 0, 'items' => 0];
            $prevStats = $dailyStats[$prevDate] ?? ['profit' => 0, 'revenue' => 0, 'items' => 0];

            $comparison = [
                'date' => $targetDate,
                'profit' => $targetStats['profit'],
                'revenue' => $targetStats['revenue'],
                'items' => $targetStats['items'],
                'prev_date' => $prevDate,
                'prev_profit' => $prevStats['profit'],
                'prev_revenue' => $prevStats['revenue'],
                'profit_diff' => $targetStats['profit'] - $prevStats['profit'],
                'revenue_diff' => $targetStats['revenue'] - $prevStats['revenue'],
                'percentage' => $prevStats['profit'] != 0 ? round((($targetStats['profit'] - $prevStats['profit']) / abs($prevStats['profit'])) * 100, 1) : 0
            ];
        }

        return response()->json([
            'summary' => [
                'total_profit' => $totalRevenue - $totalCost,
                'total_revenue' => $totalRevenue,
                'total_items' => $totalItems
            ],
            'profit_trend' => array_values($dailyStats),
            'sales_breakdown' => array_values($breakdown),
            'comparison' => $comparison
        ]);
    }

    /**
     * Get audit checklist questions + existing answers for a stock_out.
     * Merges answered questions (with snapshotted content) + current unanswered questions.
     */
    public function getChecklist($stockOutId)
    {
        $stockOut = StockOut::findOrFail($stockOutId);
        $category = $stockOut->category;

        // Contextual Mapping: If we are auditing a received transfer, use the "In" category questions
        if ($category === 'pindah_cabang' && $stockOut->status === 'received') {
            $category = 'pindah_cabang_masuk';
        } elseif ($category === 'Barang Masuk Inventory') {
            $category = 'barang_masuk';
        }

        // Get current questions for this category
        $currentQuestions = Question::where('category', $category)->orderBy('id')->get();
        $currentQuestionIds = $currentQuestions->pluck('id')->toArray();

        // Get existing answers for this transaction
        $existingAnswers = AuditAnswer::where('stock_out_id', $stockOutId)->get();

        $checklist = collect();
        $answeredQuestionIds = [];

        // 1. Add previously answered questions (use snapshotted content if available)
        foreach ($existingAnswers as $ans) {
            $answeredQuestionIds[] = $ans->question_id;
            $checklist->push([
                'question_id' => $ans->question_id,
                'content' => $ans->question_content ?? optional(Question::find($ans->question_id))->content ?? 'Pertanyaan dihapus',
                'answer' => (bool) $ans->answer,
                'notes' => $ans->notes,
                'answered_at' => $ans->updated_at?->toDateTimeString(),
                'is_deleted' => $ans->question_id === null || !in_array($ans->question_id, $currentQuestionIds),
            ]);
        }

        // 2. Add current questions that haven't been answered yet,
        //    OR that were answered but the content has since been edited (new version = new question)
        foreach ($currentQuestions as $q) {
            if (!in_array($q->id, $answeredQuestionIds)) {
                // Never answered — add as new
                $checklist->push([
                    'question_id' => $q->id,
                    'content' => $q->content,
                    'answer' => null,
                    'notes' => null,
                    'answered_at' => null,
                    'is_deleted' => false,
                ]);
            } else {
                // Was answered — check if the question text was edited since the answer
                $existingAns = $existingAnswers->firstWhere('question_id', $q->id);
                if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $q->content) {
                    // Question was edited: mark old answer as 'is_deleted' (old version) and add new version
                    $checklist->push([
                        'question_id' => $q->id,
                        'content' => $q->content,
                        'answer' => null,
                        'notes' => null,
                        'answered_at' => null,
                        'is_deleted' => false,
                    ]);
                    // Mark the old answered version as edited
                    $checklist->transform(function ($item) use ($q, $existingAns) {
                        if ($item['question_id'] === $q->id && $item['content'] === $existingAns->question_content && $item['answer'] !== null) {
                            $item['is_deleted'] = true;
                        }
                        return $item;
                    });
                }
            }
        }

        $answeredCount = $existingAnswers->count();
        $yesCount = $existingAnswers->where('answer', true)->count();
        $totalQuestions = $checklist->count();
        $latestAnswer = $existingAnswers->max('updated_at');

        return response()->json([
            'stock_out_id' => (int) $stockOutId,
            'category' => $category,
            'questions' => $checklist->values(),
            'total' => $totalQuestions,
            'answered' => $answeredCount,
            'yes_count' => $yesCount,
            'score' => $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : 0,
            'audited_at' => $latestAnswer ? \Carbon\Carbon::parse($latestAnswer)->toDateTimeString() : null,
        ]);
    }

    /**
     * Save/update audit checklist answers with notes and question content snapshot.
     */
    public function saveChecklist(Request $request, $stockOutId)
    {
        $stockOut = StockOut::findOrFail($stockOutId);
        $user = $request->user();

        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.answer' => 'required|boolean',
            'answers.*.notes' => 'nullable|string|max:1000',
            'answers.*.content' => 'nullable|string',
        ]);

        foreach ($request->answers as $item) {
            $question = Question::find($item['question_id']);
            $content = $item['content'] ?? ($question ? $question->content : null);

            AuditAnswer::updateOrCreate(
                [
                    'stock_out_id' => $stockOutId,
                    'question_id' => $item['question_id'],
                ],
                [
                    'answer' => $item['answer'],
                    'auditor_id' => $user->id,
                    'question_content' => $content,
                    'notes' => $item['notes'] ?? null,
                ]
            );
        }

        // Return updated score
        $allAnswers = AuditAnswer::where('stock_out_id', $stockOutId)->get();
        $totalQuestions = $allAnswers->count();
        $yesCount = $allAnswers->where('answer', true)->count();
        $score = $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : 0;

        return response()->json([
            'message' => 'Checklist berhasil disimpan',
            'score' => $score,
            'answered' => $totalQuestions,
            'yes_count' => $yesCount,
            'total' => $totalQuestions,
            'audited_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get sales data with profit information for audit.
     */
    public function profit(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();
        $warehouseIds = $user->getAccessibleWarehouseIds();
        $distributorIds = $user->getAccessibleDistributorIds();

        if (empty($branchIds) && empty($onlineShopIds) && empty($warehouseIds) && empty($distributorIds)) {
            return response()->json([
                'daily_sales' => [],
                'brand_sales' => [],
                'cs_sales' => []
            ]);
        }

        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->start_date ?? $logicalNow->copy()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? $logicalNow->copy()->endOfMonth()->toDateString();

        // Role-based Date Restriction
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner'])) {
            $today = $logicalNow->toDateString();
            $yesterday = $logicalNow->copy()->subDay()->toDateString();
            $startOfThisMonth = $logicalNow->copy()->startOfMonth()->toDateString();
            $startOfLastMonth = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();

            if ($startDate === $endDate) {
                if ($startDate < $yesterday) {
                    $startDate = $today;
                    $endDate = $today;
                }
            } else {
                if ($startDate < $startOfLastMonth) {
                    $startDate = $startOfThisMonth;
                    if ($endDate < $startOfThisMonth) {
                        $endDate = $logicalNow->copy()->endOfMonth()->toDateString();
                    }
                }
                if (date('Y', strtotime($startDate)) < $logicalNow->format('Y')) {
                    $startDate = $startOfThisMonth;
                }
            }
        }

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;
        $requestedDistributorId = $request->distributor_id;

        $scopeToAccess = function ($query) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
            $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    if ($requestedBranchId) {
                        if (empty($branchIds) || in_array($requestedBranchId, $branchIds)) {
                            $sub->where('branch_id', $requestedBranchId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedOnlineShopId) {
                        if (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) {
                            $sub->where('online_shop_id', $requestedOnlineShopId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedWarehouseId) {
                        if (empty($warehouseIds) || in_array($requestedWarehouseId, $warehouseIds)) {
                            $sub->where('warehouse_id', $requestedWarehouseId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedDistributorId) {
                        if (empty($distributorIds) || in_array($requestedDistributorId, $distributorIds)) {
                            $sub->where('distributor_id', $requestedDistributorId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } else {
                        if (!empty($branchIds)) $sub->orWhereIn('branch_id', $branchIds);
                        if (!empty($onlineShopIds)) $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds)) $sub->orWhereIn('warehouse_id', $warehouseIds);
                        if (!empty($distributorIds)) $sub->orWhereIn('distributor_id', $distributorIds);
                    }
                });
            });
        };

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'];

        $dailySalesQuery = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'auditProfit'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        $scopeToAccess($dailySalesQuery);

        $paginatedProfit = $dailySalesQuery->latest()->paginate(50);
        
        $dailySales = collect($paginatedProfit->items())->map(function ($trx) {
            $details = [];
            $calculatedTotal = 0;

            // HP Items
            foreach ($trx->items as $item) {
                $price = ($item->selling_price > 0) ? $item->selling_price : ($item->product->price ?? 0);
                $details[] = [
                    'id' => 'hp_' . $item->id,
                    'name' => $item->product->name ?? 'Unknown HP',
                    'qty' => 1,
                    'price' => $price,
                    'is_fixed' => true,
                    'brand' => $item->product->brand ?? '-',
                    'type' => 'HP',
                    'imei' => $item->imei ?? '-',
                    'storage' => $item->ram && $item->storage ? $item->ram . ' / ' . $item->storage : ($item->storage ?? null),
                    'condition' => $item->condition === 'new' ? 'new' : ($item->condition === 'ex_ibox' ? 'ex_ibox' : ($item->condition ?? 'second')),
                    'raw_cost_price' => (float) ($item->cost_price ?? 0),
                ];
                $calculatedTotal += $price;
            }

            // Non-HP Items
            $jsonItems = $trx->non_hp_items;
            $hasJsonData = is_array($jsonItems) && count($jsonItems) > 0;

            if ($hasJsonData) {
                $productMap = $trx->nonHpItems->pluck('product', 'product_id');
                foreach ($jsonItems as $idx => $itemData) {
                    $pid = $itemData['product_id'] ?? null;
                    $product = $productMap[$pid] ?? null;
                    $name = $product ? $product->name : ($itemData['product_name'] ?? 'Item Non-HP');
                    $price = $itemData['selling_price'] ?? 0;
                    $qty = $itemData['quantity'] ?? 1;
                    $details[] = [
                        'id' => 'nonhp_json_' . $idx,
                        'name' => $name,
                        'qty' => $qty,
                        'price' => $price,
                        'is_fixed' => true,
                        'brand' => $product ? ($product->brand ?? '-') : '-',
                        'type' => 'Non-HP',
                        'raw_cost_price' => (float) ($product ? ($product->cost_price ?? 0) : 0)
                    ];
                    $calculatedTotal += ($price * $qty);
                }
            } else {
                foreach ($trx->nonHpItems as $nhp) {
                    $basePrice = $nhp->product->price ?? 0;
                    $details[] = [
                        'id' => 'nonhp_' . $nhp->id,
                        'name' => $nhp->product->name ?? 'Unknown Item',
                        'qty' => $nhp->quantity,
                        'price' => $basePrice,
                        'is_fixed' => true,
                        'brand' => $nhp->product->brand ?? '-',
                        'type' => 'Non-HP',
                        'raw_cost_price' => (float) ($nhp->product->cost_price ?? 0)
                    ];
                    $calculatedTotal += ($basePrice * $nhp->quantity);
                }
            }

            // Gap handling
            $remainingBalance = $trx->selling_price - $calculatedTotal;
            if (abs($remainingBalance) > 1) {
                $details[] = [
                    'id' => 'gap_1',
                    'name' => $remainingBalance > 0 ? 'Biaya Admin / Tambahan' : 'Diskon',
                    'qty' => 1,
                    'price' => $remainingBalance,
                    'brand' => '-',
                    'type' => 'Lainnya',
                    'raw_cost_price' => 0
                ];
            }

            // Outlet Details
            $outletName = 'APEX POS';
            $sourceUser = $trx->inventoryUser ?? $trx->user;
            if ($sourceUser) {
                if ($sourceUser->branch_id) {
                    $branch = \App\Models\Branch::find($sourceUser->branch_id);
                    if ($branch)
                        $outletName = $branch->name;
                } elseif ($sourceUser->online_shop_id) {
                    $shop = \App\Models\OnlineShop::find($sourceUser->online_shop_id);
                    if ($shop)
                        $outletName = $shop->name;
                }
            }

            // Profit calculation per item
            $savedProfit = $trx->auditProfit;
            $itemsModalData = $savedProfit ? ($savedProfit->items_modal ?? []) : [];

            $totalHargaModal = 0;
            $totalHargaJual = 0;

            foreach ($details as &$detail) {
                $itemJualTotal = $detail['price'] * $detail['qty']; // Aggregated sell price for this item row
                // Use actual cost price from item if available, otherwise fallback to 95%
                $defaultItemModal = (isset($detail['raw_cost_price']) && $detail['raw_cost_price'] > 0)
                    ? $detail['raw_cost_price']
                    : ($itemJualTotal > 0 ? round($itemJualTotal * 0.95) : 0);

                // If auditor saved a specific modal for this item row, use it
                $savedItemModal = null;
                if (is_array($itemsModalData) && isset($itemsModalData[$detail['id']])) {
                    $savedItemModal = (float) $itemsModalData[$detail['id']];
                }

                $effectiveItemModal = $savedItemModal ?? $defaultItemModal;
                $itemProfit = $itemJualTotal - $effectiveItemModal;

                $detail['harga_jual'] = $itemJualTotal;
                $detail['default_harga_modal'] = $defaultItemModal;
                $detail['harga_modal'] = $savedItemModal;
                $detail['profit'] = $itemProfit;
                $detail['has_saved_modal'] = $savedItemModal !== null;

                $totalHargaModal += $effectiveItemModal;
                $totalHargaJual += $itemJualTotal;
            }
            unset($detail);

            // Total Profit calculation (sum of items)
            $hargaJual = (float) ($trx->selling_price ?? 0);
            $hargaModal = $savedProfit ? (float) $savedProfit->harga_modal : null;
            $defaultHargaModal = $hargaJual > 0 ? round($hargaJual * 0.95) : 0;

            // Effective sum of all item modals
            $effectiveHargaModal = $totalHargaModal;
            $profit = $hargaJual - $effectiveHargaModal;

            // Audit score using 'profit' category (must match getProfitChecklist logic)
            $currentProfitQuestions = Question::where('category', 'profit')->get();
            $profitQuestionIds = $currentProfitQuestions->pluck('id')->toArray();
            $profitAnswers = $trx->auditAnswers->filter(function ($a) use ($profitQuestionIds) {
                return in_array($a->question_id, $profitQuestionIds) || $a->question_id === null;
            });
            $yesCount = $profitAnswers->where('answer', true)->count();
            $totalQuestions = $currentProfitQuestions->count();

            // Count edited questions as additional unanswered
            foreach ($currentProfitQuestions as $cq) {
                $existingAns = $profitAnswers->firstWhere('question_id', $cq->id);
                if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $cq->content) {
                    $totalQuestions++;
                }
            }
            // Count orphaned answers (deleted questions)
            foreach ($profitAnswers as $ans) {
                if ($ans->question_id === null || !$currentProfitQuestions->contains('id', $ans->question_id)) {
                    $totalQuestions++;
                }
            }

            $auditScore = null;
            if ($profitAnswers->count() > 0 && $totalQuestions > 0) {
                $auditScore = round(($yesCount / $totalQuestions) * 100);
            }

            // Process split payments for the receipt modal
            $processedSplitPayments = [];
            if ($trx->split_payments) {
                $splits = is_string($trx->split_payments) ? json_decode($trx->split_payments, true) : $trx->split_payments;
                if (is_array($splits)) {
                    foreach ($splits as $sp) {
                        $methodId = $sp['payment_method_id'] ?? ($sp['method_id'] ?? ($sp['id'] ?? ($sp['method'] ?? null)));
                        $amount = floatval($sp['amount'] ?? ($sp['paid'] ?? 0));
                        $methodName = 'Unknown';
                        $paymentMethods = \App\Models\PaymentMethod::all()->keyBy('id'); // Local fetch for safety or use global if available
                        if ($methodId && isset($paymentMethods[$methodId])) {
                            $methodName = $paymentMethods[$methodId]->name;
                        }
                        $processedSplitPayments[] = [
                            'method_name' => $methodName,
                            'amount' => $amount
                        ];
                    }
                }
            }

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'order_no' => $trx->receipt_id,
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? $trx->shopee_receiver ?? $trx->giveaway_receiver ?? '-',
                'customer_phone' => $trx->customer_phone ?? $trx->shopee_phone ?? $trx->giveaway_phone ?? '-',
                'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                'brand_names' => collect()->concat($trx->items->map(fn($i) => $i->product->brand ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->brand ?? '-'))->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-',
                'product_names' => collect()->concat($trx->items->map(fn($i) => $i->product->name ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->name ?? '-'))->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: '-',
                'imeis' => $trx->items->map(fn($i) => $i->imei)->filter()->implode(', ') ?: '-',
                'qty' => $trx->items->count() + ($trx->non_hp_items ? collect($trx->non_hp_items)->sum('quantity') : $trx->nonHpItems->sum('quantity')),
                'items' => $details,
                'status' => $trx->status === 'received' ? 'Lunas' : 'Pending',
                'harga_jual' => $hargaJual,
                'harga_modal' => $hargaModal,
                'default_harga_modal' => $defaultHargaModal,
                'profit' => $profit,
                'has_saved_modal' => $savedProfit !== null,
                'outlet_name' => $outletName,
                'audit_score' => $auditScore,
                'audit_answered' => $trx->auditAnswers->whereIn(
                    'question_id',
                    Question::where('category', 'profit')->pluck('id')
                )->count(),
                'audit_total' => $totalQuestions,
                'audit_yes' => $yesCount,
                'inventory_user_name' => $trx->inventoryUser->full_name ?? ($trx->inventoryUser->name ?? ($trx->user->full_name ?? ($trx->user->name ?? '-'))),
                'inventory_account_name' => $trx->inventoryUser->full_name ?? ($trx->inventoryUser->name ?? null),
                'selling_price' => $hargaJual,
                'total_discount' => $trx->total_discount ?? 0,
                'payment_method_name' => $trx->paymentMethod->name ?? null,
                'split_payments_data' => $processedSplitPayments,
                'cash' => $trx->cash ?? 0,
                'transfer' => $trx->transfer ?? 0,
                'edc' => $trx->edc ?? 0,
            ];
        });

        return response()->json([
            'daily_sales' => [
                'data' => $dailySales,
                'current_page' => $paginatedProfit->currentPage(),
                'last_page' => $paginatedProfit->lastPage(),
                'total' => $paginatedProfit->total(),
                'per_page' => $paginatedProfit->perPage(),
            ],
        ]);
    }

    /**
     * Save/update auditor's harga modal for a stock_out.
     */
    public function saveProfitData(Request $request, $stockOutId)
    {
        $stockOut = StockOut::findOrFail($stockOutId);
        $user = $request->user();

        $request->validate([
            'items_modal' => 'required|array',
        ]);

        $itemsModal = $request->items_modal;
        $totalModal = array_sum(array_map('floatval', $itemsModal));

        $auditProfit = AuditProfit::updateOrCreate(
            ['stock_out_id' => $stockOutId],
            [
                'harga_modal' => $totalModal,
                'items_modal' => $itemsModal,
                'auditor_id' => $user->id,
            ]
        );

        $profit = $stockOut->selling_price - $totalModal;

        return response()->json([
            'message' => 'Harga modal berhasil disimpan',
            'harga_modal' => $totalModal,
            'items_modal' => $itemsModal,
            'profit' => $profit,
        ]);
    }

    /**
     * Get audit checklist questions for profit category.
     * Merges answered (snapshotted) + current unanswered questions.
     */
    public function getProfitChecklist($stockOutId)
    {
        $stockOut = StockOut::findOrFail($stockOutId);

        $currentQuestions = Question::where('category', 'profit')->orderBy('id')->get();
        $currentQuestionIds = $currentQuestions->pluck('id')->toArray();

        // Load all answers for this transaction
        $existingAnswers = AuditAnswer::where('stock_out_id', $stockOutId)->get();

        $checklist = collect();
        $answeredQuestionIds = [];

        foreach ($existingAnswers as $ans) {
            $answeredQuestionIds[] = $ans->question_id;
            $checklist->push([
                'question_id' => $ans->question_id,
                'content' => $ans->question_content ?? optional(Question::find($ans->question_id))->content ?? 'Pertanyaan dihapus',
                'answer' => (bool) $ans->answer,
                'notes' => $ans->notes,
                'answered_at' => $ans->updated_at?->toDateTimeString(),
                'is_deleted' => $ans->question_id === null || !in_array($ans->question_id, $currentQuestionIds),
            ]);
        }

        // 2. Add current questions that haven't been answered yet,
        //    OR that were answered but the content has since been edited
        foreach ($currentQuestions as $q) {
            if (!in_array($q->id, $answeredQuestionIds)) {
                $checklist->push([
                    'question_id' => $q->id,
                    'content' => $q->content,
                    'answer' => null,
                    'notes' => null,
                    'answered_at' => null,
                    'is_deleted' => false,
                ]);
            } else {
                $existingAns = $existingAnswers->firstWhere('question_id', $q->id);
                if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $q->content) {
                    $checklist->push([
                        'question_id' => $q->id,
                        'content' => $q->content,
                        'answer' => null,
                        'notes' => null,
                        'answered_at' => null,
                        'is_deleted' => false,
                    ]);
                    $checklist->transform(function ($item) use ($q, $existingAns) {
                        if ($item['question_id'] === $q->id && $item['content'] === $existingAns->question_content && $item['answer'] !== null) {
                            $item['is_deleted'] = true;
                        }
                        return $item;
                    });
                }
            }
        }

        $answeredCount = $existingAnswers->count();
        $yesCount = $existingAnswers->where('answer', true)->count();
        $totalQuestions = $checklist->count();
        $latestAnswer = $existingAnswers->max('updated_at');

        return response()->json([
            'stock_out_id' => (int) $stockOutId,
            'category' => 'profit',
            'questions' => $checklist->values(),
            'total' => $totalQuestions,
            'answered' => $answeredCount,
            'yes_count' => $yesCount,
            'score' => $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : 0,
            'audited_at' => $latestAnswer ? \Carbon\Carbon::parse($latestAnswer)->toDateTimeString() : null,
        ]);
    }

    /**
     * Save profit checklist answers with notes and question content snapshot.
     */
    public function saveProfitChecklist(Request $request, $stockOutId)
    {
        $stockOut = StockOut::findOrFail($stockOutId);
        $user = $request->user();

        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.answer' => 'required|boolean',
            'answers.*.notes' => 'nullable|string|max:1000',
            'answers.*.content' => 'nullable|string',
        ]);

        foreach ($request->answers as $item) {
            $question = Question::find($item['question_id']);
            $content = $item['content'] ?? ($question ? $question->content : null);

            AuditAnswer::updateOrCreate(
                [
                    'stock_out_id' => $stockOutId,
                    'question_id' => $item['question_id'],
                ],
                [
                    'answer' => $item['answer'],
                    'auditor_id' => $user->id,
                    'question_content' => $content,
                    'notes' => $item['notes'] ?? null,
                ]
            );
        }

        $allAnswers = AuditAnswer::where('stock_out_id', $stockOutId)->get();
        $totalQuestions = $allAnswers->count();
        $yesCount = $allAnswers->where('answer', true)->count();
        $score = $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : 0;

        return response()->json([
            'message' => 'Checklist profit berhasil disimpan',
            'score' => $score,
            'answered' => $totalQuestions,
            'yes_count' => $yesCount,
            'total' => $totalQuestions,
            'audited_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Export Audit Sales data as CSV.
     */
    public function exportSales(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();
        $warehouseIds = $user->getAccessibleWarehouseIds();
        $distributorIds = $user->getAccessibleDistributorIds();

        if (empty($branchIds) && empty($onlineShopIds) && empty($warehouseIds) && empty($distributorIds)) {
            return response()->json(['error' => 'No access'], 403);
        }

        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->start_date ?? $logicalNow->copy()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? $logicalNow->copy()->endOfMonth()->toDateString();

        // Role-based Date Restriction
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner'])) {
            $today = $logicalNow->toDateString();
            $yesterday = $logicalNow->copy()->subDay()->toDateString();
            $startOfThisMonth = $logicalNow->copy()->startOfMonth()->toDateString();
            $startOfLastMonth = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();

            if ($startDate === $endDate) {
                if ($startDate < $yesterday) {
                    $startDate = $today;
                    $endDate = $today;
                }
            } else {
                if ($startDate < $startOfLastMonth) {
                    $startDate = $startOfThisMonth;
                    if ($endDate < $startOfThisMonth) {
                        $endDate = $logicalNow->copy()->endOfMonth()->toDateString();
                    }
                }
                if (date('Y', strtotime($startDate)) < $logicalNow->format('Y')) {
                    $startDate = $startOfThisMonth;
                }
            }
        }

        // Filter by specific location
        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;
        $requestedDistributorId = $request->distributor_id;

        $scopeToAccess = function ($query) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
            $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    if ($requestedBranchId) {
                        if (empty($branchIds) || in_array($requestedBranchId, $branchIds)) {
                            $sub->where('branch_id', $requestedBranchId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedOnlineShopId) {
                        if (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) {
                            $sub->where('online_shop_id', $requestedOnlineShopId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedWarehouseId) {
                        if (empty($warehouseIds) || in_array($requestedWarehouseId, $warehouseIds)) {
                            $sub->where('warehouse_id', $requestedWarehouseId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedDistributorId) {
                        if (empty($distributorIds) || in_array($requestedDistributorId, $distributorIds)) {
                            $sub->where('distributor_id', $requestedDistributorId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } else {
                        if (!empty($branchIds)) $sub->orWhereIn('branch_id', $branchIds);
                        if (!empty($onlineShopIds)) $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds)) $sub->orWhereIn('warehouse_id', $warehouseIds);
                        if (!empty($distributorIds)) $sub->orWhereIn('distributor_id', $distributorIds);
                    }
                });
            });
        };

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        $dailySalesQuery = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        $scopeToAccess($dailySalesQuery);

        $items = $dailySalesQuery->latest()->get();

        $callback = function () use ($items) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            fputcsv($file, [
                'Waktu',
                'No Pesanan',
                'Nama Pelanggan',
                'Kategori',
                'Tipe',
                'Brand',
                'Nama Type',
                'Jumlah Barang',
                'Status',
                'Audit Score (%)'
            ]);

            foreach ($items as $trx) {
                // Calculate Audit Score (Logic from sales() method)
                $categoryQuestions = Question::where('category', $trx->category)->get();
                $currentQuestionIds = $categoryQuestions->pluck('id')->toArray();
                $existingAnswers = $trx->auditAnswers;

                $totalQuestions = $categoryQuestions->count();
                $yesCount = $existingAnswers->where('answer', true)->count();

                // Snapshot handling logic similar to getChecklist
                foreach ($categoryQuestions as $cq) {
                    $existingAns = $existingAnswers->firstWhere('question_id', $cq->id);
                    if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $cq->content) {
                        $totalQuestions++;
                    }
                }
                foreach ($existingAnswers as $ans) {
                    if ($ans->question_id === null || !in_array($ans->question_id, $currentQuestionIds)) {
                        $totalQuestions++;
                    }
                }

                $auditScore = $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : null;

                fputcsv($file, [
                    $trx->created_at->format('Y-m-d H:i'),
                    $trx->receipt_id,
                    $trx->customer_name ?? $trx->receiver_name ?? $trx->shopee_receiver ?? $trx->giveaway_receiver ?? '-',
                    $trx->category,
                    $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                    $trx->items->map(fn($i) => $i->product->brand ?? '-')->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: ($trx->nonHpItems->map(fn($i) => $i->product->brand ?? '-')->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-'),
                    $trx->items->map(fn($i) => $i->product->name ?? '-')->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: ($trx->nonHpItems->map(fn($i) => $i->product->name ?? '-')->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: '-'),
                    $trx->items->count() + ($trx->non_hp_items ? collect($trx->non_hp_items)->sum('quantity') : $trx->nonHpItems->sum('quantity')),
                    $trx->status === 'received' ? 'Lunas' : 'Pending',
                    $auditScore !== null ? $auditScore . '%' : '-'
                ]);
            }

            fclose($file);
        };

        $filename = 'audit-sales-export-' . now()->format('Y-m-d') . '.csv';

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**

     * Get stock-in data for audit.
     */
    public function stockIn(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();
        $warehouseIds = $user->getAccessibleWarehouseIds();
        $distributorIds = $user->getAccessibleDistributorIds();

        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->start_date ?? $logicalNow->copy()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? $logicalNow->copy()->endOfMonth()->toDateString();

        // Role-based Date Restriction
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner'])) {
            $today = $logicalNow->toDateString();
            $yesterday = $logicalNow->copy()->subDay()->toDateString();
            $startOfThisMonth = $logicalNow->copy()->startOfMonth()->toDateString();
            $startOfLastMonth = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();

            if ($startDate === $endDate) {
                if ($startDate < $yesterday) {
                    $startDate = $today;
                    $endDate = $today;
                }
            } else {
                if ($startDate < $startOfLastMonth) {
                    $startDate = $startOfThisMonth;
                    if ($endDate < $startOfThisMonth) {
                        $endDate = $logicalNow->copy()->endOfMonth()->toDateString();
                    }
                }
                if (date('Y', strtotime($startDate)) < $logicalNow->format('Y')) {
                    $startDate = $startOfThisMonth;
                }
            }
        }

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;
        $requestedDistributorId = $request->distributor_id;

        $categories = ['barang_masuk', 'pindah_cabang_masuk', 'pindah_cabang'];

        $query = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'destination'])
            ->whereIn('category', $categories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        // Only received transfers are relevant for "In"
        $query->where(function ($q) {
            $q->where('category', '!=', 'pindah_cabang')
                ->orWhere('status', 'received');
        });

        // Filter by location
        $query->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
            // For barang_masuk_inventory (manual), filter by inventoryUser's location
            $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                $sub->whereIn('category', ['barang_masuk', 'Barang Masuk Inventory']);
                $sub->whereHas('inventoryUser', function ($sq) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    if ($requestedBranchId) {
                        $sq->where('branch_id', $requestedBranchId);
                    } elseif ($requestedOnlineShopId) {
                        $sq->where('online_shop_id', $requestedOnlineShopId);
                    } elseif ($requestedWarehouseId) {
                        $sq->where('warehouse_id', $requestedWarehouseId);
                    } elseif ($requestedDistributorId) {
                        $sq->where('distributor_id', $requestedDistributorId);
                    } else {
                        if (!empty($branchIds)) $sq->orWhereIn('branch_id', $branchIds);
                        if (!empty($onlineShopIds)) $sq->orWhereIn('online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds)) $sq->orWhereIn('warehouse_id', $warehouseIds);
                        if (!empty($distributorIds)) $sq->orWhereIn('distributor_id', $distributorIds);
                    }
                });
            });

            // For pindah_cabang (transfers), filter by destination
            $q->orWhere(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                $sub->where('category', 'pindah_cabang');
                if ($requestedBranchId) {
                    $sub->where('destination_type', 'branch')->where('destination_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $sub->where('destination_type', 'online_shop')->where('destination_id', $requestedOnlineShopId);
                } elseif ($requestedWarehouseId) {
                    $sub->where('destination_type', 'warehouse')->where('destination_id', $requestedWarehouseId);
                } elseif ($requestedDistributorId) {
                    $sub->where('destination_type', 'distributor')->where('destination_id', $requestedDistributorId);
                } else {
                    $sub->where(function ($inner) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                        if (!empty($branchIds)) {
                            $inner->orWhere(function ($ss) use ($branchIds) {
                                $ss->where('destination_type', 'branch')->whereIn('destination_id', $branchIds);
                            });
                        }
                        if (!empty($onlineShopIds)) {
                            $inner->orWhere(function ($ss) use ($onlineShopIds) {
                                $ss->where('destination_type', 'online_shop')->whereIn('destination_id', $onlineShopIds);
                            });
                        }
                        if (!empty($warehouseIds)) {
                            $inner->orWhere(function ($ss) use ($warehouseIds) {
                                $ss->where('destination_type', 'warehouse')->whereIn('destination_id', $warehouseIds);
                            });
                        }
                        if (!empty($distributorIds)) {
                            $inner->orWhere(function ($ss) use ($distributorIds) {
                                $ss->where('destination_type', 'distributor')->whereIn('destination_id', $distributorIds);
                            });
                        }
                    });
                }
            });
        });

        $paginatedIn = $query->latest()->paginate(50);
        
        $records = collect($paginatedIn->items())->map(function ($trx) {
            $hpItemsCount = $trx->items->count();
            // Non-HP count from audit payload or relation
            $nonHpItemsCount = 0;
            if ($trx->items->isEmpty()) {
                if (is_array($trx->non_hp_items)) {
                    $nonHpItemsCount = collect($trx->non_hp_items)->sum('quantity');
                } elseif ($trx->nonHpItems->isNotEmpty()) {
                    $nonHpItemsCount = $trx->nonHpItems->sum('quantity');
                }
            }

            // Audit score
            $auditAnsCount = $trx->auditAnswers->count();
            $yesCount = $trx->auditAnswers->where('answer', true)->count();
            $currentQuestions = Question::where('category', $trx->category)->count();

            // Only calculate score if there is at least 1 answer
            $score = null;
            if ($auditAnsCount > 0 && $currentQuestions > 0) {
                $score = round(($yesCount / $currentQuestions) * 100);
            }

            $sourceLabel = 'Unknown';
            if ($trx->category === 'pindah_cabang') {
                $sourceUser = $trx->user;
                $sourceLabel = $sourceUser->branch?->name ?? $sourceUser->warehouse?->name ?? 'External';
            } else {
                $sourceLabel = 'Manual Entry';
            }

            $outletName = 'APEX POS';
            $invUser = $trx->inventoryUser ?? $trx->user;
            if ($invUser) {
                if ($invUser->branch_id) {
                    $branch = \App\Models\Branch::find($invUser->branch_id);
                    if ($branch) $outletName = $branch->name;
                } elseif ($invUser->online_shop_id) {
                    $shop = \App\Models\OnlineShop::find($invUser->online_shop_id);
                    if ($shop) $outletName = $shop->name;
                } elseif ($invUser->warehouse_id) {
                    $warehouse = \App\Models\Warehouse::find($invUser->warehouse_id);
                    if ($warehouse) $outletName = $warehouse->name;
                }
            }

            $actualCategory = $trx->category;
            // Map for frontend display in the context of Stock In audit
            $displayCategory = $actualCategory;
            if ($actualCategory === 'pindah_cabang') {
                $displayCategory = 'pindah_cabang_masuk';
            } elseif ($actualCategory === 'Barang Masuk Inventory') {
                $displayCategory = 'barang_masuk';
            }

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'receipt_id' => $trx->receipt_id,
                'category' => $displayCategory,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                'brand_names' => collect()->concat($trx->items->map(fn($i) => $i->product->brand ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->brand ?? '-'))->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-',
                'product_names' => collect()->concat($trx->items->map(fn($i) => $i->product->name ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->name ?? '-'))->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: '-',
                'imeis' => $trx->items->map(fn($i) => $i->imei)->filter()->implode(', ') ?: '-',
                'storages' => $trx->items->map(fn($i) => $i->ram && $i->storage ? $i->ram . '/' . $i->storage : $i->storage)->filter()->unique()->implode(', ') ?: null,
                'conditions' => $trx->items->map(fn($i) => match ($i->condition) { 'new' => 'Baru', 'ex_ibox' => 'Ex iBox', default => 'Second'})->filter()->unique()->implode(', ') ?: null,
                'qty' => $hpItemsCount + $nonHpItemsCount,
                'source' => $sourceLabel,
                'outlet_name' => $outletName,
                'audit_score' => $score,
                'audit_answered' => $auditAnsCount,
                'audit_total' => $currentQuestions,
            ];
        });

        return response()->json([
            'data' => $records,
            'current_page' => $paginatedIn->currentPage(),
            'last_page' => $paginatedIn->lastPage(),
            'total' => $paginatedIn->total(),
            'per_page' => $paginatedIn->perPage(),
        ]);
    }

    /**
     * Get stock-out data for audit.
     */
    public function stockOut(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();
        $warehouseIds = $user->getAccessibleWarehouseIds();
        $distributorIds = $user->getAccessibleDistributorIds();

        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->start_date ?? $logicalNow->copy()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? $logicalNow->copy()->endOfMonth()->toDateString();

        // Role-based Date Restriction
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner'])) {
            $today = $logicalNow->toDateString();
            $yesterday = $logicalNow->copy()->subDay()->toDateString();
            $startOfThisMonth = $logicalNow->copy()->startOfMonth()->toDateString();
            $startOfLastMonth = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();

            if ($startDate === $endDate) {
                if ($startDate < $yesterday) {
                    $startDate = $today;
                    $endDate = $today;
                }
            } else {
                if ($startDate < $startOfLastMonth) {
                    $startDate = $startOfThisMonth;
                    if ($endDate < $startOfThisMonth) {
                        $endDate = $logicalNow->copy()->endOfMonth()->toDateString();
                    }
                }
                if (date('Y', strtotime($startDate)) < $logicalNow->format('Y')) {
                    $startDate = $startOfThisMonth;
                }
            }
        }

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;
        $requestedDistributorId = $request->distributor_id;

        $categories = [
            'penjualan_offline',
            'orderan_online',
            'pindah_cabang',
            'retur',
            'kesalahan_input',
            'giveaway_customer',
            'shopee',
            'penjualan_store',
            'bundling',
            'tukar_unit',
            'tukar_tambah',
            'downgrade',
            'hadiah',
            'brand_ambassador',
            'promo',
            'inventaris',
            'event_sponsorship',
            'hilang',
        ];

        $query = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'destination'])
            ->whereIn('category', $categories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        // Filter by location
        $query->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
            $q->whereHas('inventoryUser', function ($sq) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                if ($requestedBranchId) {
                    $sq->where('branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $sq->where('online_shop_id', $requestedOnlineShopId);
                } elseif ($requestedWarehouseId) {
                    $sq->where('warehouse_id', $requestedWarehouseId);
                } elseif ($requestedDistributorId) {
                    $sq->where('distributor_id', $requestedDistributorId);
                } else {
                    if (!empty($branchIds)) $sq->orWhereIn('branch_id', $branchIds);
                    if (!empty($onlineShopIds)) $sq->orWhereIn('online_shop_id', $onlineShopIds);
                    if (!empty($warehouseIds)) $sq->orWhereIn('warehouse_id', $warehouseIds);
                    if (!empty($distributorIds)) $sq->orWhereIn('distributor_id', $distributorIds);
                }
            })->orWhereHas('user', function ($sq) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                if ($requestedBranchId) {
                    $sq->where('branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $sq->where('online_shop_id', $requestedOnlineShopId);
                } elseif ($requestedWarehouseId) {
                    $sq->where('warehouse_id', $requestedWarehouseId);
                } elseif ($requestedDistributorId) {
                    $sq->where('distributor_id', $requestedDistributorId);
                } else {
                    if (!empty($branchIds)) $sq->orWhereIn('branch_id', $branchIds);
                    if (!empty($onlineShopIds)) $sq->orWhereIn('online_shop_id', $onlineShopIds);
                    if (!empty($warehouseIds)) $sq->orWhereIn('warehouse_id', $warehouseIds);
                    if (!empty($distributorIds)) $sq->orWhereIn('distributor_id', $distributorIds);
                }
            });
        });

        $paginatedOut = $query->latest()->paginate(50);
        
        $records = collect($paginatedOut->items())->map(function ($trx) {
            $hpItemsCount = $trx->items->count();
            // Non-HP count from audit payload or relation
            $nonHpItemsCount = 0;
            if ($trx->items->isEmpty()) {
                if (is_array($trx->non_hp_items)) {
                    $nonHpItemsCount = collect($trx->non_hp_items)->sum('quantity');
                } elseif ($trx->nonHpItems->isNotEmpty()) {
                    $nonHpItemsCount = $trx->nonHpItems->sum('quantity');
                }
            }

            // Audit score
            $auditAnsCount = $trx->auditAnswers->count();
            $yesCount = $trx->auditAnswers->where('answer', true)->count();
            $currentQuestions = Question::where('category', $trx->category)->count();

            // Only calculate score if there is at least 1 answer
            $score = null;
            if ($auditAnsCount > 0 && $currentQuestions > 0) {
                $score = round(($yesCount / $currentQuestions) * 100);
            }

            $sourceLabel = 'Internal';
            if ($trx->category === 'pindah_cabang' && $trx->destination) {
                $sourceLabel = $trx->destination->name;
            } elseif (in_array($trx->category, ['penjualan_offline', 'orderan_online'])) {
                $sourceLabel = 'Customer';
            } else {
                $sourceLabel = 'Manual Entry';
            }

            $outletName = 'APEX POS';
            $invUser = $trx->inventoryUser ?? $trx->user;
            if ($invUser) {
                if ($invUser->branch_id) {
                    $branch = \App\Models\Branch::find($invUser->branch_id);
                    if ($branch) $outletName = $branch->name;
                } elseif ($invUser->online_shop_id) {
                    $shop = \App\Models\OnlineShop::find($invUser->online_shop_id);
                    if ($shop) $outletName = $shop->name;
                } elseif ($invUser->warehouse_id) {
                    $warehouse = \App\Models\Warehouse::find($invUser->warehouse_id);
                    if ($warehouse) $outletName = $warehouse->name;
                }
            }

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'receipt_id' => $trx->receipt_id,
                'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                'brand_names' => collect()->concat($trx->items->map(fn($i) => $i->product->brand ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->brand ?? '-'))->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-',
                'product_names' => collect()->concat($trx->items->map(fn($i) => $i->product->name ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->name ?? '-'))->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: '-',
                'imeis' => $trx->items->map(fn($i) => $i->imei)->filter()->implode(', ') ?: '-',
                'storages' => $trx->items->map(fn($i) => $i->ram && $i->storage ? $i->ram . '/' . $i->storage : $i->storage)->filter()->unique()->implode(', ') ?: null,
                'conditions' => $trx->items->map(fn($i) => match ($i->condition) { 'new' => 'Baru', 'ex_ibox' => 'Ex iBox', default => 'Second'})->filter()->unique()->implode(', ') ?: null,
                'qty' => $hpItemsCount + $nonHpItemsCount,
                'source' => $sourceLabel,
                'outlet_name' => $outletName,
                'audit_score' => $score,
                'audit_answered' => $auditAnsCount,
                'audit_total' => $currentQuestions,
            ];
        });

        return response()->json([
            'data' => $records,
            'current_page' => $paginatedOut->currentPage(),
            'last_page' => $paginatedOut->lastPage(),
            'total' => $paginatedOut->total(),
            'per_page' => $paginatedOut->perPage(),
        ]);
    }

    /**
     * Get checklist for stock-in audit.
     */
    public function getStockInChecklist($stockOutId)
    {
        return $this->getChecklist($stockOutId);
    }

    /**
     * Save checklist for stock-in audit.
     */
    public function saveStockInChecklist(Request $request, $stockOutId)
    {
        return $this->saveChecklist($request, $stockOutId);
    }

    /**
     * Get checklist for stock-out audit.
     */
    public function getStockOutChecklist($stockOutId)
    {
        return $this->getChecklist($stockOutId);
    }

    /**
     * Save checklist for stock-out audit.
     */
    public function saveStockOutChecklist(Request $request, $stockOutId)
    {
        return $this->saveChecklist($request, $stockOutId);
    }
}
