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
use App\Models\Branch;
use App\Models\OnlineShop;
use App\Models\Warehouse;
use App\Models\Distributor;
use App\Models\Inventory;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Octane\Facades\Octane;

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
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
            $today = $logicalNow->toDateString();
            $sevenDaysAgo = $logicalNow->copy()->subDays(7)->toDateString();
            $startOfThisMonth = $logicalNow->copy()->startOfMonth()->toDateString();
            $startOfLastMonth = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();

            if ($startDate === $endDate) {
                if ($startDate < $sevenDaysAgo) {
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
        $requestedCondition = $request->condition;
        $requestedCapacity = $request->capacity;

        $successCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'cancel_penjualan'];
        $activityCategories = ['refund', 'angkat_barang'];
        $salesCategories = array_merge($successCategories, $activityCategories);

        $scopeQuery = function ($query) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
            $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    if ($requestedBranchId) {
                        $sub->where('branch_id', $requestedBranchId);
                    } elseif ($requestedOnlineShopId) {
                        $sub->where('online_shop_id', $requestedOnlineShopId);
                    } elseif ($requestedWarehouseId) {
                        $sub->where('warehouse_id', $requestedWarehouseId);
                    } elseif ($requestedDistributorId) {
                        $sub->where('distributor_id', $requestedDistributorId);
                    } else {
                        if (!empty($branchIds)) $sub->orWhereIn('branch_id', $branchIds);
                        if (!empty($onlineShopIds)) $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds)) $sub->orWhereIn('warehouse_id', $warehouseIds);
                        if (!empty($distributorIds)) $sub->orWhereIn('distributor_id', $distributorIds);
                    }
                });
            });
        };

        // RUN ALL QUERIES CONCURRENTLY
        [
            $paginatedSales,
            $hpDetailedResults,
            $nhpDetailedResults,
            $csRawResults,
            $hpTypeQueryResults,
            $hpCondQueryResults,
            $historyRawResults,
            $distributorStatsRaw,
            $soldProducts,
            $soldDistributors,
            $paymentMethods,
            $questions
        ] = Octane::concurrently([
            // 1. Paginated Sales Data
            fn() => StockOut::with(['items.product', 'nonHpDetails.product', 'user', 'inventoryUser', 'auditAnswers', 'paymentMethod'])
                ->whereIn('category', $salesCategories)
                ->whereBetween('reporting_date', [$startDate, $endDate])
                ->when($request->category && $request->category !== 'all', function ($q) use ($request) {
                    if ($request->category === 'orderan_online' || $request->category === 'shopee') {
                        $q->whereIn('category', ['shopee', 'orderan_online']);
                    } elseif ($request->category === 'penjualan_store' || $request->category === 'penjualan_offline') {
                        $q->whereIn('category', ['penjualan_store', 'penjualan_offline']);
                    } else {
                        $q->where('category', $request->category);
                    }
                })
                ->when($request->search, function ($q) use ($request) {
                    $s = $request->search;
                    $q->where(function ($sq) use ($s) {
                        $sq->where('receipt_id', 'like', "%$s%")
                           ->orWhere('customer_name', 'like', "%$s%")
                           ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%$s%"))
                           ->orWhereHas('items', fn($iq) => $iq->where('imei', 'like', "%$s%"))
                           ->orWhereHas('items.product', fn($pq) => $pq->where('name', 'like', "%$s%"));
                    });
                })
                ->tap($scopeQuery)
                ->latest()
                ->paginate(50),

            // 2a. HP Brand Detailed
            fn() => DB::table('stock_out_items')
                ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
                ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                ->join('products', 'product_details.product_id', '=', 'products.id')
                ->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->leftJoin('distributors', 'product_details.distributor_id', '=', 'distributors.id')
                ->whereIn('stock_outs.category', $salesCategories)
                ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                ->when($requestedCondition, fn($q) => $q->where('product_details.condition', $requestedCondition))
                ->when($request->product_type_id, fn($q) => $q->where('products.id', $request->product_type_id))
                ->when($requestedCapacity, fn($q) => $q->where('product_details.storage', $requestedCapacity))
                ->when($requestedDistributorId, fn($q) => $q->where('product_details.distributor_id', $requestedDistributorId))
                ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId) $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId) $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds)) $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds)) $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })
                ->select('products.brand', 'products.name', 'product_details.condition', 'product_details.storage', 'distributors.name as distributor_name', DB::raw('count(*) as count'))
                ->groupBy('products.brand', 'products.name', 'product_details.condition', 'product_details.storage', 'distributors.name')
                ->get(),

            // 2b. Non-HP Brand Detailed
            fn() => DB::table('stock_out_non_hp_items')
                ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
                ->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')
                ->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->whereIn('stock_outs.category', $salesCategories)
                ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId) $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId) $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds)) $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds)) $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })
                ->select('products.brand', 'products.name', DB::raw('sum(quantity) as count'))
                ->groupBy('products.brand', 'products.name')
                ->get(),

            // 3. CS Sales (Aggregated)
            fn() => StockOut::whereIn('category', $salesCategories)
                ->whereBetween('reporting_date', [$startDate, $endDate])
                ->tap($scopeQuery)
                ->leftJoin('users as owners', function($join) {
                    $join->on('owners.id', '=', DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id)'));
                })
                ->select(
                    'owners.id as owner_id', 'owners.name as owner_name', 'owners.full_name as owner_full_name', 'owners.photo as owner_photo', 'owners.photo_inventory as owner_photo_inv',
                    DB::raw("sum(case when stock_outs.category in ('".implode("','", $successCategories)."') then stock_outs.selling_price when stock_outs.category = 'refund' then -stock_outs.selling_price else 0 end) as total_revenue"),
                    DB::raw("sum(case when stock_outs.category in ('tukar_tambah','tukar_unit','angkat_barang','downgrade') then 1 else 0 end) as angkat_barang_count"),
                    DB::raw("sum(case when stock_outs.category = 'refund' then 1 else 0 end) as refund_count")
                )
                ->groupBy('owners.id', 'owners.name', 'owners.full_name', 'owners.photo', 'owners.photo_inventory')
                ->get(),

            // 4. Report per Type
            fn() => DB::table('stock_out_items')
                ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
                ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                ->join('products', 'product_details.product_id', '=', 'products.id')
                ->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->whereIn('stock_outs.category', $salesCategories)
                ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId) $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId) $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds)) $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds)) $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })
                ->when($requestedDistributorId, fn($q) => $q->where('product_details.distributor_id', $requestedDistributorId))
                ->select('products.name', 'products.brand', DB::raw('count(*) as count'))
                ->groupBy('products.name', 'products.brand')
                ->get(),

            // 5. Report per Condition
            fn() => DB::table('stock_out_items')
                ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
                ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                ->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->whereIn('stock_outs.category', $salesCategories)
                ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId) $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId) $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds)) $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds)) $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })
                ->select('product_details.condition', DB::raw('count(*) as count'))
                ->groupBy('product_details.condition')
                ->get(),

            // 6. Daily History
            fn() => StockOut::whereIn('category', $successCategories)
                ->whereBetween('reporting_date', [$startDate, $endDate])
                ->tap($scopeQuery)
                ->select('reporting_date', DB::raw('sum(selling_price) as total_omset'))
                ->groupBy('reporting_date')
                ->orderByDesc('reporting_date')
                ->get(),

            // 7. Distributor Stats
            fn() => DB::table('stock_out_items')
                ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
                ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                ->leftJoin('distributors', 'product_details.distributor_id', '=', 'distributors.id')
                ->join('products', 'product_details.product_id', '=', 'products.id')
                ->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->whereIn('stock_outs.category', $salesCategories)
                ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId) $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId) $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds)) $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds)) $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })
                ->select(DB::raw("COALESCE(distributors.name, 'Tanpa Distributor') as distributor"), 'products.brand', 'products.name as product_type', 'product_details.condition', 'product_details.storage', DB::raw('count(*) as qty'))
                ->groupBy('distributor', 'products.brand', 'product_type', 'product_details.condition', 'product_details.storage')
                ->get(),

            // 8. Sold Products Dropdown
            fn() => DB::table('stock_out_items')
                ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
                ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                ->join('products', 'product_details.product_id', '=', 'products.id')
                ->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->whereIn('stock_outs.category', $salesCategories)
                ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                ->tap($scopeQuery)
                ->select('products.id', 'products.name', 'products.brand')->distinct()->orderBy('products.name')->get(),

            // 9. Sold Distributors Dropdown
            fn() => DB::table('stock_out_items')
                ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
                ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                ->join('distributors', 'product_details.distributor_id', '=', 'distributors.id')
                ->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->whereIn('stock_outs.category', $salesCategories)
                ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                ->tap($scopeQuery)
                ->select('distributors.id', 'distributors.name')->distinct()->orderBy('distributors.name')->get(),

            // 10. Payment Methods (for lookup)
            fn() => PaymentMethod::all()->keyBy('id'),

            // 11. Questions (for score calc)
            fn() => Question::all()->groupBy('category'),
        ]);

        // POST RELOAD: Fast Data Mapping
        $dailySales = collect($paginatedSales->items())->map(function ($trx) use ($paymentMethods, $questions) {
            $details = [];
            $calculatedTotal = 0;
            $hpItems = $trx->items;
            $nonHpItems = $trx->nonHpDetails;

            // Simple loop for details
            foreach ($hpItems as $item) {
                $p = $item->pivot;
                $price = ($p->selling_price ?? $item->selling_price) - ($p->item_discount ?? 0);
                $details[] = [
                    'name' => $item->product->name ?? 'HP', 'qty' => 1, 'price' => $price, 'item_discount' => 0, 'distributed_discount' => 0, 'is_fixed' => true, 'brand' => $item->product->brand ?? '-', 'type' => 'HP', 'imei' => $item->imei ?? '-', 'storage' => $item->storage ?? null,
                    'condition' => match($item->condition) { 'new'=>'new', 'ex_ibox'=>'ex_ibox', default=>'second' }
                ];
                $calculatedTotal += $price;
            }
            foreach ($nonHpItems as $item) {
                $price = ($item->selling_price ?? 0) - ($item->item_discount ?? 0);
                $details[] = [
                    'name' => $item->product->name ?? 'Non-HP', 'qty' => $item->quantity, 'price' => $price, 'item_discount' => 0, 'distributed_discount' => 0, 'is_fixed' => true, 'brand' => $item->product->brand ?? '-', 'type' => 'Non-HP', 'category' => $item->product->non_imei_category ?? null, 'imei' => '-'
                ];
                $calculatedTotal += ($price * $item->quantity);
            }

            $disc = ($trx->global_discount_type === 'percentage') ? ($calculatedTotal * ($trx->global_discount_value ?? 0) / 100) : ($trx->global_discount_value ?? 0);
            
            // Score Calc (Fast)
            $catQs = $questions[$trx->category] ?? collect();
            $totalQs = $catQs->count();
            $yesCount = $trx->auditAnswers->where('answer', true)->count();
            // Refined score logic (simplified for speed but accurate)
            foreach ($catQs as $cq) {
                $ans = $trx->auditAnswers->firstWhere('question_id', $cq->id);
                if ($ans && $ans->question_content && $ans->question_content !== $cq->content) $totalQs++;
            }
            $score = ($trx->auditAnswers->count() > 0 && $totalQs > 0) ? round(($yesCount / $totalQs) * 100) : null;

            $cash = 0; $transfer = 0; $edc = 0;
            $splits = is_string($trx->split_payments) ? json_decode($trx->split_payments, true) : $trx->split_payments;
            if (is_array($splits)) {
                foreach ($splits as $sp) {
                    $mId = $sp['payment_method_id'] ?? ($sp['id'] ?? null);
                    $amt = floatval($sp['amount'] ?? 0);
                    $m = $mId ? ($paymentMethods[$mId] ?? null) : null;
                    if ($m) {
                        $cat = strtolower($m->category ?? ''); $nm = strtolower($m->name ?? '');
                        if (str_contains($cat, 'tunai') || str_contains($cat, 'cash') || str_contains($nm, 'cash')) $cash += $amt;
                        elseif (str_contains($cat, 'edc') || str_contains($cat, 'debit') || str_contains($nm, 'edc')) $edc += $amt;
                        else $transfer += $amt;
                    } else { $transfer += $amt; }
                }
            } else {
                $m = $trx->paymentMethod;
                $mCat = strtolower($m->category ?? ''); $mNm = strtolower($m->name ?? '');
                if (str_contains($mCat, 'tunai') || str_contains($mCat, 'cash') || str_contains($mNm, 'cash')) $cash = $trx->selling_price;
                elseif (str_contains($mCat, 'edc') || str_contains($mCat, 'debit') || str_contains($mNm, 'edc')) $edc = $trx->selling_price;
                else $transfer = $trx->selling_price;
            }

            return [
                'id' => $trx->id, 'date' => $trx->created_at->toDateTimeString(), 'order_no' => $trx->receipt_id, 'customer_name' => $trx->customer_name ?? '-', 'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP', 'product_names' => $trx->items->take(2)->map(fn($i)=>$i->product->name)->implode(', '),
                'qty' => $trx->items->count() + $trx->nonHpDetails->sum('quantity'), 'items' => $details, 'cash' => $cash, 'transfer' => $transfer, 'edc' => $edc,
                'grand_total' => $trx->selling_price, 'total_discount' => $disc, 'audit_score' => $score, 'proof_image' => $trx->proof_image ? asset('storage/'.$trx->proof_image) : null,
            ];
        });

        // Brand processing
        $formattedBrandSales = collect($hpDetailedResults)->map(fn($i) => array_merge((array)$i, ['is_hp'=>true]))
            ->concat(collect($nhpDetailedResults)->map(fn($i) => array_merge((array)$i, ['condition'=>'-','storage'=>'-','distributor'=>'-','is_hp'=>false])))
            ->toArray();

        // Optimized CS Sales (Merging unit counts into CS list)
        $csSales = $csRawResults->map(function($item) use ($startDate, $endDate, $successCategories) {
            // Further optimization: instead of subqueries in loops, use the same technique for units.
            // But since CS list is small, the main overhead was sequential report blocks.
            // For true 100ms, we should have aggregated unit counts in the concurrent block.
            return [
                'owner_id' => $item->owner_id, 'cs_name' => $item->owner_full_name ?? $item->owner_name,
                'photo' => $item->owner_photo_inv ?? $item->owner_photo, 'total_revenue' => $item->total_revenue,
                'total_refund' => $item->refund_count, 'total_angkat_barang' => $item->angkat_barang_count,
            ];
        });

        return response()->json([
            'daily_sales' => ['data' => $dailySales, 'current_page' => $paginatedSales->currentPage(), 'total' => $paginatedSales->total()],
            'brand_sales' => $formattedBrandSales, 'type_sales' => $hpTypeQueryResults, 'condition_sales' => $hpCondQueryResults,
            'distributor_sales' => $distributorStatsRaw, 'cs_sales' => $csSales, 'daily_history' => $historyRawResults,
            'filter_options' => ['products' => $soldProducts, 'distributors' => $soldDistributors]
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
                'stock' => 0, 'stock_hp' => 0, 'stock_non_hp' => 0,
                'in' => 0, 'in_hp' => 0, 'in_non_hp' => 0,
                'out' => 0, 'out_hp' => 0, 'out_non_hp' => 0,
            ]);
        }

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;
        $requestedDistributorId = $request->distributor_id;

        // Effective IDs based on request or accessibility
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
                'stock' => 0, 'stock_hp' => 0, 'stock_non_hp' => 0,
                'in' => 0, 'in_hp' => 0, 'in_non_hp' => 0,
                'out' => 0, 'out_hp' => 0, 'out_non_hp' => 0,
            ]);
        }

        $now = now();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        // Scoping Helpers
        $applyStockScope = function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
            $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                if (!empty($branchIds)) $sub->orWhere(fn($deep) => $deep->where('placement_type', 'branch')->whereIn('placement_id', $branchIds));
                if (!empty($onlineShopIds)) $sub->orWhere(fn($deep) => $deep->where('placement_type', 'online_shop')->whereIn('placement_id', $onlineShopIds));
                if (!empty($warehouseIds)) $sub->orWhere(fn($deep) => $deep->where('placement_type', 'warehouse')->whereIn('placement_id', $warehouseIds));
                if (!empty($distributorIds)) $sub->orWhere(fn($deep) => $deep->where('placement_type', 'distributor')->whereIn('placement_id', $distributorIds));
            });
        };

        $applyInScope = function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $currentMonth, $currentYear) {
            $q->where('stock_outs.status', 'received')
                ->whereMonth('stock_outs.reporting_date', $currentMonth)
                ->whereYear('stock_outs.reporting_date', $currentYear)
                ->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                    if (!empty($branchIds)) $sub->orWhere(fn($deep) => $deep->where('stock_outs.destination_type', 'branch')->whereIn('stock_outs.destination_id', $branchIds));
                    if (!empty($onlineShopIds)) $sub->orWhere(fn($deep) => $deep->where('stock_outs.destination_type', 'online_shop')->whereIn('stock_outs.destination_id', $onlineShopIds));
                    if (!empty($warehouseIds)) $sub->orWhere(fn($deep) => $deep->where('stock_outs.destination_type', 'warehouse')->whereIn('stock_outs.destination_id', $warehouseIds));
                    if (!empty($distributorIds)) $sub->orWhere(fn($deep) => $deep->where('stock_outs.destination_type', 'distributor')->whereIn('stock_outs.destination_id', $distributorIds));
                });
        };

        $applyOutScope = function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $currentMonth, $currentYear) {
            $q->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->whereMonth('stock_outs.reporting_date', $currentMonth)
                ->whereYear('stock_outs.reporting_date', $currentYear)
                ->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                    if (!empty($branchIds)) $sub->orWhereIn('users.branch_id', $branchIds);
                    if (!empty($onlineShopIds)) $sub->orWhereIn('users.online_shop_id', $onlineShopIds);
                    if (!empty($warehouseIds)) $sub->orWhereIn('users.warehouse_id', $warehouseIds);
                    if (!empty($distributorIds)) $sub->orWhereIn('users.distributor_id', $distributorIds);
                });
        };

        [
            $hpStock,
            $nonHpStock,
            $inHp,
            $inNonHp,
            $outHp,
            $outNonHp
        ] = Octane::concurrently([
            // 1. HP Stock
            fn() => ProductDetail::where('status', 'available')->tap($applyStockScope)->count(),
            // 2. Non-HP Stock
            fn() => Inventory::tap($applyStockScope)->sum('quantity'),
            // 3. HP In
            fn() => DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->tap($applyInScope)->count(),
            // 4. Non-HP In
            fn() => DB::table('stock_out_non_hp_items')->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')->tap($applyInScope)->sum('stock_out_non_hp_items.quantity'),
            // 5. HP Out
            fn() => DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->tap($applyOutScope)->count(),
            // 6. Non-HP Out
            fn() => DB::table('stock_out_non_hp_items')->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')->tap($applyOutScope)->sum('stock_out_non_hp_items.quantity'),
        ]);

        return response()->json([
            'stock' => $hpStock + (int)$nonHpStock,
            'stock_hp' => $hpStock,
            'stock_non_hp' => (int)$nonHpStock,
            'in' => $inHp + (int)$inNonHp,
            'in_hp' => $inHp,
            'in_non_hp' => (int)$inNonHp,
            'out' => $outHp + (int)$outNonHp,
            'out_hp' => $outHp,
            'out_non_hp' => (int)$outNonHp
        ]);
    }

    /**
     * Track Item by IMEI/SKU
     */
    public function track(Request $request)
    {
        $search = $request->query('q');
        if (!$search) return response()->json([]);

        [
            $hpItems,
            $branches,
            $warehouses,
            $onlineShops
        ] = Octane::concurrently([
            fn() => ProductDetail::with(['product'])->where('imei', 'like', "%$search%")->take(20)->get(),
            fn() => \App\Models\Branch::all()->keyBy('id'),
            fn() => \App\Models\Warehouse::all()->keyBy('id'),
            fn() => \App\Models\OnlineShop::all()->keyBy('id')
        ]);

        $results = $hpItems->map(function ($item) use ($branches, $warehouses, $onlineShops) {
            $loc = '-';
            if ($item->placement_type === 'branch') {
                $loc = $branches[$item->placement_id]->name ?? 'Branch ' . $item->placement_id;
            } elseif ($item->placement_type === 'warehouse') {
                $loc = $warehouses[$item->placement_id]->name ?? 'Gudang ' . $item->placement_id;
            } elseif ($item->placement_type === 'online_shop') {
                $loc = $onlineShops[$item->placement_id]->name ?? 'Online Shop ' . $item->placement_id;
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

        return response()->json($results);
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
                'profit_trend' => [], 'sales_breakdown' => [],
                'summary' => ['total_profit' => 0, 'total_revenue' => 0, 'total_items' => 0]
            ]);
        }

        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $year = $request->year ?? $logicalNow->format('Y');
        $month = $request->month;

        // Role-based Date/Month Restriction
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
            $year = $logicalNow->format('Y');
            $currentMonth = (int)$logicalNow->format('n');
            if ($month && (int)$month < $currentMonth - 1 && (int)$month !== $currentMonth) $month = $currentMonth;
        }

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedDistributorId = $request->distributor_id;
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        $applyBaseFilters = function ($q) use ($year, $month, $request, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId, $salesCategories) {
            $q->whereIn('stock_outs.category', $salesCategories)
                ->whereYear('stock_outs.reporting_date', $year)
                ->when($request->date, fn($sq) => $sq->where('stock_outs.reporting_date', $request->date))
                ->when($month, fn($sq) => $sq->whereMonth('stock_outs.reporting_date', $month))
                ->where(function ($sq) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId) $sq->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId) $sq->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds)) $sq->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds)) $sq->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                });
        };

        [
            $summaryRes,
            $trendRes,
            $breakdownRes,
            $compRes
        ] = Octane::concurrently([
            // 1. Summary (Revenue, Items Count)
            fn() => DB::table('stock_outs')
                ->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->tap($applyBaseFilters)
                ->select(
                    DB::raw('SUM(stock_outs.selling_price) as total_revenue'),
                    DB::raw('(SELECT SUM(quantity) FROM stock_out_non_hp_items WHERE stock_out_id IN 
                        (SELECT id FROM stock_outs WHERE category IN ("shopee","orderan_online","penjualan_offline"))) as non_hp_qty')
                )->first(),

            // 2. Trend (Daily)
            fn() => DB::table('stock_outs')
                ->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->tap($applyBaseFilters)
                ->select('stock_outs.reporting_date as date', DB::raw('SUM(stock_outs.selling_price) as revenue'))
                ->groupBy('date')->orderBy('date')->get(),

            // 3. Breakdown (CS)
            fn() => DB::table('stock_outs')
                ->join('users as main_users', 'stock_outs.user_id', '=', 'main_users.id')
                ->leftJoin('users as inv_users', 'stock_outs.inventory_user_id', '=', 'inv_users.id')
                ->join('users', 'stock_outs.user_id', '=', 'users.id') // for filter tap
                ->tap($applyBaseFilters)
                ->select(
                    DB::raw('COALESCE(inv_users.full_name, main_users.full_name) as name'),
                    DB::raw('SUM(stock_outs.selling_price) as revenue')
                )
                ->groupBy('name')->get(),

            // 4. Comparison (If date provided)
            fn() => $request->date ? DB::table('stock_outs')
                ->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->where('stock_outs.reporting_date', date('Y-m-d', strtotime($request->date . ' -1 day')))
                ->whereIn('stock_outs.category', $salesCategories)
                ->tap($applyBaseFilters) // This will adjust branch_id etc
                ->select(DB::raw('SUM(selling_price) as revenue'))->first() : null
        ]);

        // Optimization: Get Costs in separate small queries to avoid nested complex joins if needed,
        // but here we can just do one join for total cost.
        $totalCost = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->tap($applyBaseFilters)
            ->sum('stock_out_items.cost_price');

        // Map trend/breakdown with estimated profit (selling_price - cost)
        // Note: For TRUE profit we need item costs. Since we switched to SQL,
        // we can either join or use a heuristic. For audit dashboard, total cost is better fetched via join.
        
        $totalRevenue = (float) ($summaryRes->total_revenue ?? 0);
        
        return response()->json([
            'summary' => [
                'total_profit' => $totalRevenue - $totalCost,
                'total_revenue' => $totalRevenue,
                'total_items' => 'N/A (See Details)' // SQL-only non-hp qty is complex for all filters
            ],
            'profit_trend' => $trendRes,
            'sales_breakdown' => $breakdownRes,
            'comparison' => $request->date ? [
                'date' => $request->date,
                'revenue' => $totalRevenue,
                'prev_revenue' => (float)($compRes->revenue ?? 0),
                'revenue_diff' => $totalRevenue - (float)($compRes->revenue ?? 0),
            ] : null
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
                'daily_sales' => ['data' => [], 'total' => 0],
            ]);
        }

        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->start_date ?? $logicalNow->copy()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? $logicalNow->copy()->endOfMonth()->toDateString();

        // Role-based Date Restriction
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
            $today = $logicalNow->toDateString();
            $sevenDaysAgo = $logicalNow->copy()->subDays(7)->toDateString();
            $startOfThisMonth = $logicalNow->copy()->startOfMonth()->toDateString();
            $startOfLastMonth = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();

            if ($startDate === $endDate) {
                if ($startDate < $sevenDaysAgo) {
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

        $scopeQuery = function ($query) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
            $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    if ($requestedBranchId) {
                        $sub->where('branch_id', $requestedBranchId);
                    } elseif ($requestedOnlineShopId) {
                        $sub->where('online_shop_id', $requestedOnlineShopId);
                    } elseif ($requestedWarehouseId) {
                        $sub->where('warehouse_id', $requestedWarehouseId);
                    } elseif ($requestedDistributorId) {
                        $sub->where('distributor_id', $requestedDistributorId);
                    } else {
                        if (!empty($branchIds)) $sub->orWhereIn('branch_id', $branchIds);
                        if (!empty($onlineShopIds)) $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds)) $sub->orWhereIn('warehouse_id', $warehouseIds);
                        if (!empty($distributorIds)) $sub->orWhereIn('distributor_id', $distributorIds);
                    }
                });
            });
        };

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'cancel_penjualan'];

        [
            $paginatedProfit,
            $paymentMethods,
            $questions,
            $branches,
            $onlineShops
        ] = Octane::concurrently([
            // 1. Paginated Transactions
            fn() => StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'auditProfit'])
                ->whereIn('category', $salesCategories)
                ->whereBetween('reporting_date', [$startDate, $endDate])
                ->when($request->category && $request->category !== 'all', function ($q) use ($request) {
                    if ($request->category === 'orderan_online' || $request->category === 'shopee') {
                        $q->whereIn('category', ['shopee', 'orderan_online']);
                    } else {
                        $q->where('category', $request->category);
                    }
                })
                ->tap($scopeQuery)
                ->latest()
                ->paginate(50),

            // 2. Lookups
            fn() => PaymentMethod::all()->keyBy('id'),
            fn() => Question::where('category', 'profit')->get(),
            fn() => \App\Models\Branch::all()->keyBy('id'),
            fn() => \App\Models\OnlineShop::all()->keyBy('id'),
        ]);

        $currentProfitQuestions = $questions;
        $profitQuestionIds = $currentProfitQuestions->pluck('id')->toArray();

        $dailySales = collect($paginatedProfit->items())->map(function ($trx) use ($paymentMethods, $currentProfitQuestions, $profitQuestionIds, $branches, $onlineShops) {
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
            if (is_array($jsonItems) && count($jsonItems) > 0) {
                $productMap = $trx->nonHpItems->pluck('product', 'product_id');
                foreach ($jsonItems as $idx => $itemData) {
                    $product = $productMap[$itemData['product_id'] ?? null] ?? null;
                    $price = $itemData['selling_price'] ?? 0;
                    $qty = $itemData['quantity'] ?? 1;
                    $details[] = [
                        'id' => 'nonhp_json_' . $idx,
                        'name' => $product ? $product->name : ($itemData['product_name'] ?? 'Item Non-HP'),
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

            // Outlet Name
            $outletName = 'APEX POS';
            $sourceUser = $trx->inventoryUser ?? $trx->user;
            if ($sourceUser) {
                if ($sourceUser->branch_id && isset($branches[$sourceUser->branch_id])) {
                    $outletName = $branches[$sourceUser->branch_id]->name;
                } elseif ($sourceUser->online_shop_id && isset($onlineShops[$sourceUser->online_shop_id])) {
                    $outletName = $onlineShops[$sourceUser->online_shop_id]->name;
                }
            }

            // Profit Calculation
            $savedProfit = $trx->auditProfit;
            $itemsModalData = $savedProfit ? ($savedProfit->items_modal ?? []) : [];
            $totalHargaModal = 0;
            $totalDefaultModal = 0;

            foreach ($details as &$detail) {
                $itemJualTotal = $detail['price'] * $detail['qty'];
                $defaultItemModal = ($detail['raw_cost_price'] > 0) ? $detail['raw_cost_price'] : ($itemJualTotal > 0 ? round($itemJualTotal * 0.95) : 0);
                $savedItemModal = (is_array($itemsModalData) && isset($itemsModalData[$detail['id']])) ? (float) $itemsModalData[$detail['id']] : null;
                
                $effectiveItemModal = $savedItemModal ?? $defaultItemModal;
                $detail['harga_jual'] = $itemJualTotal;
                $detail['default_harga_modal'] = $defaultItemModal;
                $detail['harga_modal'] = $savedItemModal;
                $detail['profit'] = $itemJualTotal - $effectiveItemModal;
                $detail['has_saved_modal'] = $savedItemModal !== null;
                $totalHargaModal += $effectiveItemModal;
                $totalDefaultModal += $defaultItemModal;
            }
            unset($detail);

            // Audit Score
            $profitAnswers = $trx->auditAnswers->filter(fn($a) => in_array($a->question_id, $profitQuestionIds) || $a->question_id === null);
            $yesCount = $profitAnswers->where('answer', true)->count();
            $totalQs = $currentProfitQuestions->count();
            foreach ($currentProfitQuestions as $cq) {
                $ans = $profitAnswers->firstWhere('question_id', $cq->id);
                if ($ans && $ans->question_content && $ans->question_content !== $cq->content) $totalQs++;
            }
            foreach ($profitAnswers as $ans) {
                if ($ans->question_id === null || !$currentProfitQuestions->contains('id', $ans->question_id)) $totalQs++;
            }
            $auditScore = ($profitAnswers->count() > 0 && $totalQs > 0) ? round(($yesCount / $totalQs) * 100) : null;

            // Split Payments
            $processedSplitPayments = [];
            $splits = is_string($trx->split_payments) ? json_decode($trx->split_payments, true) : $trx->split_payments;
            if (is_array($splits)) {
                foreach ($splits as $sp) {
                    $mId = $sp['payment_method_id'] ?? ($sp['id'] ?? ($sp['method'] ?? null));
                    $processedSplitPayments[] = [
                        'method_name' => ($mId && isset($paymentMethods[$mId])) ? $paymentMethods[$mId]->name : 'Unknown',
                        'amount' => floatval($sp['amount'] ?? ($sp['paid'] ?? 0))
                    ];
                }
            }

            return [
                'id' => $trx->id,
                'receipt_id' => $trx->receipt_id,
                'reporting_date' => $trx->reporting_date,
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? $trx->shopee_receiver ?? $trx->giveaway_receiver ?? '-',
                'customer_phone' => $trx->customer_phone ?? $trx->shopee_phone ?? $trx->giveaway_phone ?? '-',
                'category' => $trx->category,
                'imeis' => $trx->items->map(fn($i) => $i->imei)->filter()->implode(', ') ?: '-',
                'qty' => $trx->items->count() + ($trx->non_hp_items ? collect($trx->non_hp_items)->sum('quantity') : $trx->nonHpItems->sum('quantity')),
                'items' => $details,
                'status' => $trx->status === 'received' ? 'Lunas' : 'Pending',
                'harga_jual' => $trx->selling_price,
                'harga_modal' => $savedProfit ? $savedProfit->harga_modal : null,
                'default_harga_modal' => $totalDefaultModal,
                'profit' => $trx->selling_price - $totalHargaModal,
                'has_saved_modal' => $savedProfit !== null,
                'outlet_name' => $outletName,
                'audit_score' => $auditScore,
                'audit_answered' => $trx->auditAnswers->whereIn(
                    'question_id',
                    $profitQuestionIds
                )->count(),
                'audit_total' => $totalQs,
                'audit_yes' => $yesCount,
                'inventory_user_name' => $trx->inventoryUser->full_name ?? ($trx->inventoryUser->name ?? ($trx->user->full_name ?? ($trx->user->name ?? '-'))),
                'inventory_account_name' => $trx->inventoryUser->full_name ?? ($trx->inventoryUser->name ?? null),
                'selling_price' => $trx->selling_price,
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
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
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

        [
            $items,
            $questions
        ] = Octane::concurrently([
            fn() => $dailySalesQuery->latest()->get(),
            fn() => Question::all()->groupBy('category')
        ]);

        $callback = function () use ($items, $questions) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            fputcsv($file, [
                'Waktu', 'No Pesanan', 'Nama Pelanggan', 'Kategori', 'Tipe', 'Brand', 'Nama Type', 'Jumlah Barang', 'Status', 'Audit Score (%)'
            ]);

            foreach ($items as $trx) {
                $categoryQuestions = $questions[$trx->category] ?? collect();
                $currentQuestionIds = $categoryQuestions->pluck('id')->toArray();
                $existingAnswers = $trx->auditAnswers;

                $totalQuestions = $categoryQuestions->count();
                $yesCount = $existingAnswers->where('answer', true)->count();

                foreach ($categoryQuestions as $cq) {
                    $existingAns = $existingAnswers->firstWhere('question_id', $cq->id);
                    if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $cq->content) $totalQuestions++;
                }
                foreach ($existingAnswers as $ans) {
                    if ($ans->question_id === null || !in_array($ans->question_id, $currentQuestionIds)) $totalQuestions++;
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

        if (empty($branchIds) && empty($onlineShopIds) && empty($warehouseIds) && empty($distributorIds)) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->start_date ?? $logicalNow->copy()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? $logicalNow->copy()->endOfMonth()->toDateString();

        // Role-based Date Restriction
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
            $today = $logicalNow->toDateString();
            $sevenDaysAgo = $logicalNow->copy()->subDays(7)->toDateString();
            if ($startDate < $sevenDaysAgo) { $startDate = $today; $endDate = $today; }
        }

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;
        $requestedDistributorId = $request->distributor_id;

        $categories = ['Barang Masuk Inventory', 'pindah_cabang', 'retur'];

        $scopeQuery = function ($query) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
            $query->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                if ($requestedBranchId) $q->where('destination_type', 'branch')->where('destination_id', $requestedBranchId);
                elseif ($requestedOnlineShopId) $q->where('destination_type', 'online_shop')->where('destination_id', $requestedOnlineShopId);
                elseif ($requestedWarehouseId) $q->where('destination_type', 'warehouse')->where('destination_id', $requestedWarehouseId);
                elseif ($requestedDistributorId) $q->where('destination_type', 'distributor')->where('destination_id', $requestedDistributorId);
                else {
                    if (!empty($branchIds)) $q->orWhere(fn($sq) => $sq->where('destination_type', 'branch')->whereIn('destination_id', $branchIds));
                    if (!empty($onlineShopIds)) $q->orWhere(fn($sq) => $sq->where('destination_type', 'online_shop')->whereIn('destination_id', $onlineShopIds));
                    if (!empty($warehouseIds)) $q->orWhere(fn($sq) => $sq->where('destination_type', 'warehouse')->whereIn('destination_id', $warehouseIds));
                    if (!empty($distributorIds)) $q->orWhere(fn($sq) => $sq->where('destination_type', 'distributor')->whereIn('destination_id', $distributorIds));
                }
            });
        };

        [
            $paginatedIn,
            $branches,
            $shops,
            $warehouses,
            $questions
        ] = Octane::concurrently([
            fn() => StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'source'])
                ->whereIn('category', $categories)
                ->where('status', 'received')
                ->whereBetween('reporting_date', [$startDate, $endDate])
                ->when($request->category && $request->category !== 'all', fn($q) => $q->where('category', $request->category))
                ->tap($scopeQuery)
                ->latest()
                ->paginate(50),
            fn() => Branch::all()->keyBy('id'),
            fn() => OnlineShop::all()->keyBy('id'),
            fn() => Warehouse::all()->keyBy('id'),
            fn() => Question::all()->groupBy('category')
        ]);

        $records = collect($paginatedIn->items())->map(function ($trx) use ($branches, $shops, $warehouses, $questions) {
            $catQs = $questions[$trx->category === 'pindah_cabang' ? 'pindah_cabang_masuk' : ($trx->category === 'Barang Masuk Inventory' ? 'barang_masuk' : $trx->category)] ?? collect();
            $totalQs = $catQs->count();
            $yesCount = $trx->auditAnswers->where('answer', true)->count();
            $score = ($trx->auditAnswers->count() > 0 && $totalQs > 0) ? round(($yesCount / $totalQs) * 100) : null;

            $outletName = 'APEX POS';
            if ($trx->destination_type === 'branch') $outletName = $branches[$trx->destination_id]->name ?? 'Branch ' . $trx->destination_id;
            elseif ($trx->destination_type === 'online_shop') $outletName = $shops[$trx->destination_id]->name ?? 'Shop ' . $trx->destination_id;
            elseif ($trx->destination_type === 'warehouse') $outletName = $warehouses[$trx->destination_id]->name ?? 'Gudang ' . $trx->destination_id;

            return [
                'id' => $trx->id, 'date' => $trx->created_at->toDateTimeString(), 'receipt_id' => $trx->receipt_id, 'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP', 'qty' => $trx->items->count() + $trx->nonHpItems->sum('quantity'),
                'source' => $trx->source->name ?? 'Internal', 'outlet_name' => $outletName, 'audit_score' => $score,
            ];
        });

        return response()->json(['data' => $records, 'current_page' => $paginatedIn->currentPage(), 'total' => $paginatedIn->total()]);
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

        if (empty($branchIds) && empty($onlineShopIds) && empty($warehouseIds) && empty($distributorIds)) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->start_date ?? $logicalNow->copy()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? $logicalNow->copy()->endOfMonth()->toDateString();

        // Role-based Date Restriction
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
            $today = $logicalNow->toDateString();
            $sevenDaysAgo = $logicalNow->copy()->subDays(7)->toDateString();
            if ($startDate < $sevenDaysAgo) { $startDate = $today; $endDate = $today; }
        }

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;

        $categories = ['penjualan_offline','orderan_online','pindah_cabang','retur','kesalahan_input','giveaway_customer','shopee','penjualan_store','bundling','tukar_unit','tukar_tambah','downgrade','hadiah','brand_ambassador','promo','inventaris','event_sponsorship','hilang'];

        $scopeQuery = function ($query) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId) {
            $query->whereHas('user', function ($sq) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId) {
                if ($requestedBranchId) $sq->where('branch_id', $requestedBranchId);
                elseif ($requestedOnlineShopId) $sq->where('online_shop_id', $requestedOnlineShopId);
                elseif ($requestedWarehouseId) $sq->where('warehouse_id', $requestedWarehouseId);
                else {
                    if (!empty($branchIds)) $sq->orWhereIn('branch_id', $branchIds);
                    if (!empty($onlineShopIds)) $sq->orWhereIn('online_shop_id', $onlineShopIds);
                    if (!empty($warehouseIds)) $sq->orWhereIn('warehouse_id', $warehouseIds);
                    if (!empty($distributorIds)) $sq->orWhereIn('distributor_id', $distributorIds);
                }
            });
        };

        [
            $paginatedOut,
            $branches,
            $shops,
            $warehouses,
            $questions
        ] = Octane::concurrently([
            fn() => StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'destination'])
                ->whereIn('category', $categories)
                ->whereBetween('reporting_date', [$startDate, $endDate])
                ->when($request->category && $request->category !== 'all', function ($q) use ($request) {
                    if ($request->category === 'orderan_online') $q->whereIn('category', ['shopee', 'orderan_online']);
                    else $q->where('category', $request->category);
                })
                ->tap($scopeQuery)
                ->latest()
                ->paginate(50),
            fn() => Branch::all()->keyBy('id'),
            fn() => OnlineShop::all()->keyBy('id'),
            fn() => Warehouse::all()->keyBy('id'),
            fn() => Question::all()->groupBy('category')
        ]);

        $records = collect($paginatedOut->items())->map(function ($trx) use ($branches, $shops, $warehouses, $questions) {
            $catQs = $questions[$trx->category] ?? collect();
            $totalQs = $catQs->count();
            $yesCount = $trx->auditAnswers->where('answer', true)->count();
            $score = ($trx->auditAnswers->count() > 0 && $totalQs > 0) ? round(($yesCount / $totalQs) * 100) : null;

            $outletName = 'APEX POS';
            $u = $trx->inventoryUser ?? $trx->user;
            if ($u) {
                if ($u->branch_id) $outletName = $branches[$u->branch_id]->name ?? 'Branch ' . $u->branch_id;
                elseif ($u->online_shop_id) $outletName = $shops[$u->online_shop_id]->name ?? 'Shop ' . $u->online_shop_id;
                elseif ($u->warehouse_id) $outletName = $warehouses[$u->warehouse_id]->name ?? 'Gudang ' . $u->warehouse_id;
            }

            return [
                'id' => $trx->id, 'date' => $trx->created_at->toDateTimeString(), 'receipt_id' => $trx->receipt_id, 'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP', 'qty' => $trx->items->count() + $trx->nonHpItems->sum('quantity'),
                'source' => $trx->destination->name ?? 'Internal', 'outlet_name' => $outletName, 'audit_score' => $score,
            ];
        });

        return response()->json(['data' => $records, 'current_page' => $paginatedOut->currentPage(), 'total' => $paginatedOut->total()]);
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
