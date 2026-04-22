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
use Illuminate\Support\Collection;

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

        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        // No more clipping logic here to prevent data loss on monthly reports.
        // Frontend will handle valid date ranges.

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedDistributorId = $request->distributor_id;
        $requestedWarehouseId = $request->warehouse_id;
        $requestedCategory = $request->category;
        $requestedSearch = $request->search;
        $requestedCondition = $request->condition;
        $requestedProductTypeId = $request->product_type_id;
        $requestedCapacity = $request->capacity;

        $scopeToAccess = function ($query) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
            $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                if ($requestedBranchId) {
                    if (empty($branchIds) || in_array($requestedBranchId, $branchIds)) {
                        $q->where('branch_id', $requestedBranchId);
                    } else {
                        $q->whereRaw('1=0');
                    }
                } elseif ($requestedOnlineShopId) {
                    if (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) {
                        $q->where('online_shop_id', $requestedOnlineShopId);
                    } else {
                        $q->whereRaw('1=0');
                    }
                } elseif ($requestedWarehouseId) {
                    if (empty($warehouseIds) || in_array($requestedWarehouseId, $warehouseIds)) {
                        $q->where('warehouse_id', $requestedWarehouseId);
                    } else {
                        $q->whereRaw('1=0');
                    }
                } elseif ($requestedDistributorId) {
                    if (empty($distributorIds) || in_array($requestedDistributorId, $distributorIds)) {
                        $q->where('distributor_id', $requestedDistributorId);
                    } else {
                        $q->whereRaw('1=0');
                    }
                } else {
                    $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                        if (!empty($branchIds))
                            $sub->orWhereIn('branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds))
                            $sub->orWhereIn('warehouse_id', $warehouseIds);
                        if (!empty($distributorIds))
                            $sub->orWhereIn('distributor_id', $distributorIds);
                    });
                }
            });
        };

        // Scoper for DB::table queries that have 'users' join already
        $dbScope = function ($query) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
            $query->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                if ($requestedBranchId) {
                    if (empty($branchIds) || in_array($requestedBranchId, $branchIds)) {
                        $sub->where('users.branch_id', $requestedBranchId);
                    } else {
                        $sub->whereRaw('1=0');
                    }
                } elseif ($requestedOnlineShopId) {
                    if (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) {
                        $sub->where('users.online_shop_id', $requestedOnlineShopId);
                    } else {
                        $sub->whereRaw('1=0');
                    }
                } elseif ($requestedWarehouseId) {
                    if (empty($warehouseIds) || in_array($requestedWarehouseId, $warehouseIds)) {
                        $sub->where('users.warehouse_id', $requestedWarehouseId);
                    } else {
                        $sub->whereRaw('1=0');
                    }
                } elseif ($requestedDistributorId) {
                    if (empty($distributorIds) || in_array($requestedDistributorId, $distributorIds)) {
                        $sub->where('users.distributor_id', $requestedDistributorId);
                    } else {
                        $sub->whereRaw('1=0');
                    }
                } else {
                    $sub->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                        if (!empty($branchIds))
                            $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds))
                            $q->orWhereIn('users.warehouse_id', $warehouseIds);
                        if (!empty($distributorIds))
                            $q->orWhereIn('users.distributor_id', $distributorIds);
                    });
                }
            });
        };

        $successCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'cancel_penjualan'];
        $activityCategories = ['refund', 'angkat_barang'];
        $salesCategories = array_merge($successCategories, $activityCategories);

        // Optimization: Pre-fetch meta data to avoid N+1 in loops
        $branches = Branch::all()->keyBy('id');
        $onlineShops = OnlineShop::all()->keyBy('id');
        $warehouses = Warehouse::all()->keyBy('id');
        $questions = Question::all()->groupBy('category');
        $paymentMethods = PaymentMethod::all()->keyBy('id');

        // Define a manual helper because closures inside Octane can be tricky
        $helper_scopeUser = function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
            $q->where(function ($sub) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId)
                    $sub->where('users.branch_id', $requestedBranchId);
                elseif ($requestedOnlineShopId)
                    $sub->where('users.online_shop_id', $requestedOnlineShopId);
                else {
                    if (!empty($branchIds))
                        $sub->orWhereIn('users.branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $sub->orWhereIn('users.online_shop_id', $onlineShopIds);
                }
            });
        };

        // Use Octane to run independent queries in parallel
        [$paginatedSales, $brandSalesRaw, $csSalesRaw, $dailyHistoryRaw, $typeStatsRaw, $conditionStatsRaw, $distributorStatsRaw, $soldProducts, $soldDistributors, $reportSummary] = Octane::concurrently([
            // 1. Paginated Sales Query
            function () use ($salesCategories, $startDate, $endDate, $requestedCategory, $requestedSearch, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                return StockOut::with(['items.product', 'nonHpDetails.product', 'user', 'inventoryUser', 'auditAnswers', 'paymentMethod'])
                    ->whereIn('category', $salesCategories)
                    ->whereBetween('reporting_date', [$startDate, $endDate])
                    ->when($requestedCategory && $requestedCategory !== 'all', function ($q) use ($requestedCategory) {
                        if ($requestedCategory === 'orderan_online' || $requestedCategory === 'shopee')
                            $q->whereIn('category', ['shopee', 'orderan_online']);
                        elseif ($requestedCategory === 'penjualan_store' || $requestedCategory === 'penjualan_offline')
                            $q->whereIn('category', ['penjualan_store', 'penjualan_offline']);
                        else
                            $q->where('category', $requestedCategory);
                    })
                    ->when($requestedSearch, function ($q) use ($requestedSearch) {
                        $s = $requestedSearch;
                        $q->where(function ($sq) use ($s) {
                            $sq->where('receipt_id', 'like', "%$s%")->orWhere('customer_name', 'like', "%$s%")->orWhere('receiver_name', 'like', "%$s%")->orWhere('shopee_receiver', 'like', "%$s%")->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%$s%"))->orWhereHas('items', fn($iq) => $iq->where('imei', 'like', "%$s%"))->orWhereHas('items.product', fn($pq) => $pq->where('name', 'like', "%$s%"))->orWhereHas('nonHpDetails.product', fn($pq) => $pq->where('name', 'like', "%$s%"));
                        });
                    })
                    ->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                        if ($requestedBranchId)
                            $q->where('branch_id', $requestedBranchId);
                        elseif ($requestedOnlineShopId)
                            $q->where('online_shop_id', $requestedOnlineShopId);
                        else {
                            $q->where(function ($sub) use ($branchIds, $onlineShopIds) {
                                if (!empty($branchIds))
                                    $sub->orWhereIn('branch_id', $branchIds);
                                if (!empty($onlineShopIds))
                                    $sub->orWhereIn('online_shop_id', $onlineShopIds);
                            });
                        }
                    })
                    ->latest()->paginate(50);
            },

            // 2. Brand Stats
            function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId, $requestedCondition, $requestedProductTypeId, $requestedCapacity, $requestedDistributorId) {
                $hpQuery = DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->join('products', 'product_details.product_id', '=', 'products.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->leftJoin('distributors', 'product_details.distributor_id', '=', 'distributors.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->when($requestedCondition, fn($q) => $q->where('product_details.condition', $requestedCondition))->when($requestedProductTypeId, fn($q) => $q->where('products.id', $requestedProductTypeId))->when($requestedCapacity, fn($q) => $q->where('product_details.storage', $requestedCapacity))->when($requestedDistributorId, fn($q) => $q->where('product_details.distributor_id', $requestedDistributorId))->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId)
                        $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId)
                        $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds))
                            $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })->select('products.brand', 'products.name', 'product_details.condition', 'product_details.storage', 'distributors.name as distributor_name', DB::raw('count(*) as qty'))->groupBy('products.brand', 'products.name', 'product_details.condition', 'product_details.storage', 'distributors.name')->get();
                $nhpQuery = DB::table('stock_out_non_hp_items')->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId)
                        $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId)
                        $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds))
                            $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })->select('products.brand', 'products.name', DB::raw('sum(quantity) as qty'))->groupBy('products.brand', 'products.name')->get();
                return ['hp' => $hpQuery, 'nhp' => $nhpQuery];
            },

            // 3. CS Sales Stats
            function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                $baseQuery = DB::table('stock_outs')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId)
                        $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId)
                        $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds))
                            $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                });
                $itemStatsQuery = (clone $baseQuery)->leftJoin('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')->leftJoin('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->leftJoin('products', 'product_details.product_id', '=', 'products.id')->select(DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id) as owner_id'), DB::raw("sum(case when products.brand = 'Apple' then 1 else 0 end) as iphone_units"), DB::raw("sum(case when products.brand != 'Apple' and products.brand is not null then 1 else 0 end) as android_units"))->groupBy('owner_id')->get()->keyBy('owner_id');
                $nhpStatsQuery = (clone $baseQuery)->leftJoin('stock_out_non_hp_items', 'stock_outs.id', '=', 'stock_out_non_hp_items.stock_out_id')->select(DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id) as owner_id'), DB::raw("sum(stock_out_non_hp_items.quantity) as non_hp_units"))->groupBy('owner_id')->get()->keyBy('owner_id');
                $mainStats = (clone $baseQuery)->leftJoin('users as owners', function ($join) {
                    $join->on('owners.id', '=', DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id)')); })->select('owners.id as owner_id', 'owners.name as cs_name', 'owners.full_name as full_name', 'owners.photo as photo', 'owners.photo_inventory as photo_inv', DB::raw("sum(case when stock_outs.category in ('shopee','orderan_online','penjualan_offline','penjualan_store','tukar_unit','tukar_tambah','downgrade','cancel_penjualan') then stock_outs.selling_price when stock_outs.category = 'refund' then -stock_outs.selling_price else 0 end) as grand_total"), DB::raw("sum(case when stock_outs.category in ('tukar_tambah','tukar_unit','angkat_barang','downgrade') then 1 else 0 end) as total_angkat_barang"), DB::raw("sum(case when stock_outs.category = 'refund' then 1 else 0 end) as total_refund"))->groupBy('owners.id', 'owners.name', 'owners.full_name', 'owners.photo', 'owners.photo_inventory')->get();
                return $mainStats->map(function ($stat) use ($itemStatsQuery, $nhpStatsQuery) {
                    $items = $itemStatsQuery->get($stat->owner_id);
                    $nhp = $nhpStatsQuery->get($stat->owner_id);
                    $iphone = (int) ($items->iphone_units ?? 0);
                    $android = (int) ($items->android_units ?? 0);
                    $nonHp = (int) ($nhp->non_hp_units ?? 0);
                    return ['owner_id' => $stat->owner_id, 'cs_name' => $stat->cs_name ?? 'Unknown', 'photo' => $stat->photo ?? $stat->photo_inv, 'grand_total' => (float) $stat->grand_total, 'total_angkat_barang' => (int) $stat->total_angkat_barang, 'total_refund' => (int) $stat->total_refund, 'iphone_units' => $iphone, 'android_units' => $android, 'non_hp_units' => $nonHp, 'total_sales' => $iphone + $android + $nonHp]; });
            },

            // 4. Daily History
            function () use ($startDate, $endDate, $successCategories, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                $baseQuery = DB::table('stock_outs')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $successCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId)
                        $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId)
                        $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds))
                            $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                });

                $hpStats = (clone $baseQuery)->leftJoin('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')->leftJoin('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->leftJoin('products', 'product_details.product_id', '=', 'products.id')->select('reporting_date', DB::raw("sum(case when products.brand = 'Apple' then 1 else 0 end) as iphone_units"), DB::raw("sum(case when products.brand != 'Apple' and products.brand is not null then 1 else 0 end) as android_units"))->groupBy('reporting_date')->get()->keyBy('reporting_date');
                $nhpStats = (clone $baseQuery)->leftJoin('stock_out_non_hp_items', 'stock_outs.id', '=', 'stock_out_non_hp_items.stock_out_id')->select('reporting_date', DB::raw("sum(stock_out_non_hp_items.quantity) as non_hp_units"))->groupBy('reporting_date')->get()->keyBy('reporting_date');
                $mainStats = (clone $baseQuery)->select('reporting_date', DB::raw('sum(selling_price) as total_omset'))->groupBy('reporting_date')->orderByDesc('reporting_date')->get();

                return $mainStats->map(function ($stat) use ($hpStats, $nhpStats) {
                    $hp = $hpStats->get($stat->reporting_date);
                    $nhp = $nhpStats->get($stat->reporting_date);
                    $iphone = (int) ($hp->iphone_units ?? 0);
                    $android = (int) ($hp->android_units ?? 0);
                    $nonHp = (int) ($nhp->non_hp_units ?? 0);
                    return [
                        'reporting_date' => $stat->reporting_date,
                        'total_omset' => (float) $stat->total_omset,
                        'iphone_units' => $iphone,
                        'android_units' => $android,
                        'non_hp_units' => $nonHp,
                        'total_units' => $iphone + $android + $nonHp
                    ];
                });
            },

            // 5. Type Stats
            function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                return DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->join('products', 'product_details.product_id', '=', 'products.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId)
                        $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId)
                        $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds))
                            $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })->select('products.name', 'products.brand', DB::raw('count(*) as qty'))->groupBy('products.name', 'products.brand')->get();
            },

            // 6. Condition Stats
            function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                return DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId)
                        $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId)
                        $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds))
                            $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })->select('product_details.condition', DB::raw('count(*) as qty'))->groupBy('product_details.condition')->get();
            },

            // 7. Distributor Stats
            function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                return DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->leftJoin('distributors', 'product_details.distributor_id', '=', 'distributors.id')->join('products', 'product_details.product_id', '=', 'products.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId)
                        $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId)
                        $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds))
                            $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })->select(DB::raw("COALESCE(distributors.name, 'Tanpa Distributor') as distributor"), 'products.brand', 'products.name as product_type', 'product_details.condition', 'product_details.storage', DB::raw('count(*) as qty'))->groupBy('distributor', 'products.brand', 'product_type', 'product_details.condition', 'product_details.storage')->get();
            },

            // 8. Products Filter
            function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                return DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->join('products', 'product_details.product_id', '=', 'products.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId)
                        $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId)
                        $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds))
                            $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })->select('products.id', 'products.name', 'products.brand')->distinct()->orderBy('products.name')->get();
            },

            // 9. Distributor Filter
            function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                return DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->join('distributors', 'product_details.distributor_id', '=', 'distributors.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId)
                        $q->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId)
                        $q->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds))
                            $q->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })->select('distributors.id', 'distributors.name')->distinct()->orderBy('distributors.name')->get();
            },

            // 10. Unified Report Summary
            function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId, $paymentMethods) {
                try {
                    // Use the proven exact scope strategy from Daily History & Brand Stats
                    $applyLocalScope = function ($query) use ($startDate, $endDate, $branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                        $query->leftJoin('users', 'stock_outs.user_id', '=', 'users.id')
                            ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                            ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                                if ($requestedBranchId)
                                    $q->where('users.branch_id', $requestedBranchId);
                                elseif ($requestedOnlineShopId)
                                    $q->where('users.online_shop_id', $requestedOnlineShopId);
                                else {
                                    if (!empty($branchIds))
                                        $q->orWhereIn('users.branch_id', $branchIds);
                                    if (!empty($onlineShopIds))
                                        $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                                }
                            });
                    };

                    // 1. Total Omset & Payments
                    $pQuery = DB::table('stock_outs')->whereIn('stock_outs.category', $salesCategories);
                    $applyLocalScope($pQuery);
                    $payments = $pQuery->select('stock_outs.selling_price', 'stock_outs.payment_method_id', 'stock_outs.split_payments')->get();

                    $pSums = [];
                    $paymentTotal = 0;
                    foreach ($payments as $p) {
                        $amt = (float) $p->selling_price;
                        $paymentTotal += $amt;
                        $mName = $p->payment_method_id ? ($paymentMethods->get($p->payment_method_id)?->name ?? 'Lainnya') : 'Belum Lunas / Lainnya';
                        $splits = $p->split_payments ? (is_string($p->split_payments) ? json_decode($p->split_payments, true) : $p->split_payments) : null;
                        if (is_array($splits)) {
                            foreach ($splits as $sp) {
                                $method = $paymentMethods->get($sp['payment_method_id'] ?? ($sp['method_id'] ?? null));
                                $pSums[$method?->name ?? 'Lainnya'] = ($pSums[$method?->name ?? 'Lainnya'] ?? 0) + (float) ($sp['amount'] ?? 0);
                            }
                        } else {
                            $pSums[$mName] = ($pSums[$mName] ?? 0) + $amt;
                        }
                    }

                    // 2. Unit Mapping
                    $map = [
                        'apple_lux' => 0,
                        'hp' => 0,
                        'accessories' => 0,
                        'apply' => 0,
                        'debs' => 0,
                        'arcis' => 0,
                        'dokter_pstore' => 0,
                        'perdana' => 0,
                        'jaringan' => 0,
                        'iphone' => 0,
                        'android' => 0,
                        'laptop' => 0,
                        'tv' => 0
                    ];
                    $mapRp = $map;
                    $processedStockOuts = [];
                    $stockReport = [
                        'apple_lux' => 0,
                        'accessories' => 0,
                        'apply' => 0,
                        'arcis' => 0,
                        'laptop' => 0,
                        'tv' => 0,
                        'perdana' => 0,
                        'jaringan' => 0
                    ];
                    $stockDetails = [
                        'apple_lux' => [],
                        'accessories' => [],
                        'apply' => [],
                        'arcis' => [],
                        'laptop' => [],
                        'tv' => [],
                        'perdana' => [],
                        'jaringan' => []
                    ];

                    $soldDetails = [
                        'apple_lux' => [],
                        'hp' => [],
                        'accessories' => [],
                        'apply' => [],
                        'debs' => [],
                        'arcis' => [],
                        'dokter_pstore' => [],
                        'laptop' => [],
                        'tv' => []
                    ];

                    $hpItemsQuery = DB::table('stock_out_items')
                        ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
                        ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                        ->join('products', 'product_details.product_id', '=', 'products.id')
                        ->leftJoin('distributors', 'product_details.distributor_id', '=', 'distributors.id')
                        ->whereIn('stock_outs.category', $salesCategories);
                    $applyLocalScope($hpItemsQuery);
                    $hpData = $hpItemsQuery->select(
                        'products.name',
                        'products.brand',
                        DB::raw('COALESCE(stock_out_items.distributor_id, product_details.distributor_id) as distributor_id'),
                        'distributors.name as dist_name',
                        'stock_outs.id as stock_out_id',
                        'stock_out_items.selling_price as item_price',
                        'stock_out_items.item_discount'
                    )->get();

                    // Pre-map transaction distributors to handle bundles/mixed items inheritance
                    $trxDistMap = $hpData->filter(fn($i) => !empty($i->distributor_id))
                        ->groupBy('stock_out_id')
                        ->map(fn($g) => $g->first()->distributor_id);

                    // Map specific IDs for Apple Luxury for fast check
                    $appleLuxIds = DB::table('distributors')->where('name', 'ilike', 'Apple Luxury')->pluck('id')->toArray();

                    foreach ($hpData as $item) {
                        $pNameNormal = $item->name ?? 'Unknown HP';
                        $brand = strtolower($item->brand ?? '');
                        
                        // Inheritance: Use item's distributor, or fallback to any distributor in the same transaction
                        $distId = $item->distributor_id ?? ($trxDistMap[$item->stock_out_id] ?? null);
                        
                        $isAppleLux = in_array($distId, $appleLuxIds);

                        $cat = $isAppleLux ? 'apple_lux' : 'hp';
                        $map[$cat]++;

                        if (str_contains($brand, 'apple') || str_contains($brand, 'iphone')) {
                            $map['iphone']++;
                        } else {
                            $map['android']++;
                        }

                        $soldDetails[$cat][$pNameNormal] = ($soldDetails[$cat][$pNameNormal] ?? 0) + 1;

                        $price = (float) ($item->item_price ?? 0) - (float) ($item->item_discount ?? 0);
                        if ($isAppleLux)
                            $mapRp['apple_lux'] += $price;
                        else
                            $mapRp['hp'] += $price;
                    }

                    $nhpItemsQuery = DB::table('stock_out_non_hp_items')
                        ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
                        ->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')
                        ->whereIn('stock_outs.category', $salesCategories);
                    $applyLocalScope($nhpItemsQuery);

                    $nhpData = $nhpItemsQuery->leftJoin('distributors', 'stock_out_non_hp_items.distributor_id', '=', 'distributors.id')
                        ->select(
                            'products.name',
                            'products.brand',
                            'stock_out_non_hp_items.quantity',
                            'stock_out_non_hp_items.selling_price as item_price',
                            'stock_out_non_hp_items.item_discount',
                            'stock_outs.id as stock_out_id',
                            DB::raw('COALESCE(stock_out_non_hp_items.distributor_id, users.distributor_id) as distributor_id'),
                            'distributors.name as dist_name'
                        )->get();

                    // Pre-map other distributor-based categories
                    $catDistMap = [
                        'apply' => DB::table('distributors')->where('name', 'ilike', '%Apply%')->pluck('id')->toArray(),
                        'debs' => DB::table('distributors')->where('name', 'ilike', '%Debs%')->pluck('id')->toArray(),
                        'arcis' => DB::table('distributors')->where('name', 'ilike', '%Arcis%')->pluck('id')->toArray(),
                        'dokter_pstore' => DB::table('distributors')->where('name', 'ilike', '%Dokter Pstore%')->pluck('id')->toArray(),
                        'accessories' => DB::table('distributors')->where('name', 'ilike', '%Accesories%')->pluck('id')->toArray(),
                        'perdana' => DB::table('distributors')->where('name', 'ilike', '%Sim Card%')->orWhere('name', 'ilike', '%Perdana%')->pluck('id')->toArray(),
                        'laptop' => DB::table('distributors')->where('name', 'ilike', '%Laptop%')->pluck('id')->toArray(),
                        'tv' => DB::table('distributors')->where('name', 'ilike', '%TV%')->orWhere('name', 'ilike', '%tvstOre%')->pluck('id')->toArray(),
                    ];

                    // Hardcoded Strict Mapping from Tinker Audit
                    $catDistMap = [
                        'apply' => [11],
                        'arcis' => [14],
                        'debs' => [13],
                        'dokter_pstore' => [15],
                        'accessories' => [10],
                        'perdana' => [18],
                        'laptop' => [16],
                        'tv' => [17],
                    ];

                    foreach ($nhpData as $item) {
                        $name = strtolower($item->name);
                        $brand = strtolower($item->brand ?? '');
                        $qty = (int) $item->quantity;
                        $pName = $item->name ?? 'Unknown Item';
                        $distId = $item->distributor_id;

                        $price = ((float) ($item->item_price ?? 0) - (float) ($item->item_discount ?? 0)) * $qty;

                        $cat = null;
                        
                        $isAppleLuxNhp = (int)$distId === 6 || in_array((int)$distId, $appleLuxIds);
                        
                        if ($isAppleLuxNhp) {
                            $cat = 'apple_lux';
                        } elseif (in_array((int)$distId, $catDistMap['apply'])) {
                            $cat = 'apply';
                        } elseif (in_array((int)$distId, $catDistMap['accessories'])) {
                            $cat = 'accessories';
                        } elseif (in_array((int)$distId, $catDistMap['debs'])) {
                            $cat = 'debs';
                        } elseif (in_array((int)$distId, $catDistMap['arcis'])) {
                            $cat = 'arcis';
                        } elseif (in_array((int)$distId, $catDistMap['dokter_pstore'])) {
                            $cat = 'dokter_pstore';
                        } elseif (in_array((int)$distId, $catDistMap['laptop'])) {
                            $cat = 'laptop';
                        } elseif (in_array((int)$distId, $catDistMap['tv'])) {
                            $cat = 'tv';
                        } elseif (in_array((int)$distId, [7, 8, 9])) {
                            $cat = 'hp';
                        } elseif (in_array((int)$distId, $catDistMap['perdana'])) {
                            // Split between Perdana and Jaringan based on keywords but keeping ID 18
                            if (str_contains($brand, 'jasa') || str_contains($name, 'jasa') || str_contains($name, '4g') || str_contains($name, 'jaringan')) {
                                $map['jaringan'] += $qty;
                                $mapRp['jaringan'] += $price;
                                $cat = null;
                            } else {
                                $map['perdana'] += $qty;
                                $mapRp['perdana'] += $price;
                                $cat = null;
                            }
                        }

                        if ($cat) {
                            $map[$cat] = ($map[$cat] ?? 0) + $qty;
                            $mapRp[$cat] = ($mapRp[$cat] ?? 0) + $price;
                            $soldDetails[$cat][$pName] = ($soldDetails[$cat][$pName] ?? 0) + $qty;
                        }
                    }



                    $appleLuxQuery = DB::table('product_details')
                        ->join('products', 'product_details.product_id', '=', 'products.id')
                        ->where('product_details.status', 'available')
                        ->whereIn('product_details.distributor_id', $appleLuxIds);
                    $applyLocationFilters = function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                        if ($requestedBranchId) {
                            $q->where(function($sq) use ($requestedBranchId) {
                                $sq->where(fn($qq) => $qq->whereIn('placement_type', ['branch', 'App\Models\Branch'])->where('placement_id', $requestedBranchId));
                            });
                        } elseif ($requestedOnlineShopId) {
                            $q->where(function($sq) use ($requestedOnlineShopId) {
                                $sq->where(fn($qq) => $qq->whereIn('placement_type', ['online_shop', 'App\Models\OnlineShop'])->where('placement_id', $requestedOnlineShopId));
                            });
                        } else {
                            $q->where(function ($sub) use ($branchIds, $onlineShopIds) {
                                if (!empty($branchIds))
                                    $sub->orWhere(fn($qq) => $qq->whereIn('placement_type', ['branch', 'App\Models\Branch'])->whereIn('placement_id', $branchIds));
                                if (!empty($onlineShopIds))
                                    $sub->orWhere(fn($qq) => $qq->whereIn('placement_type', ['online_shop', 'App\Models\OnlineShop'])->whereIn('placement_id', $onlineShopIds));
                            });
                        }
                    };
                    $applyLocationFilters($appleLuxQuery);

                    $appleLuxStock = $appleLuxQuery->select('products.name', 'product_details.imei', 'product_details.storage', 'product_details.condition')->get();
                    foreach ($appleLuxStock as $s) {
                        $stockReport['apple_lux']++;
                        $stockDetails['apple_lux'][] = [
                            'name' => $s->name,
                            'imei' => $s->imei,
                            'storage' => $s->storage,
                            'condition' => $s->condition
                        ];
                    }

                    // 4. Other Stock Info (Accessories, Apply, etc.)
                    $otherStocksQuery = DB::table('inventories')
                        ->leftJoin('products', 'inventories.product_id', '=', 'products.id')
                        ->leftJoin('distributors', 'inventories.distributor_id', '=', 'distributors.id')
                        ->where('inventories.quantity', '>', 0);

                    $applyLocationFilters($otherStocksQuery);

                     $otherStocks = $otherStocksQuery->leftJoin('users', 'inventories.user_id', '=', 'users.id')
                        ->select(
                            'products.name',
                            'products.brand',
                            'inventories.quantity',
                            DB::raw('COALESCE(inventories.distributor_id, users.distributor_id) as distributor_id')
                        )->get();
                    $catDistMap = [
                        'apply' => [11],
                        'arcis' => [14],
                        'debs' => [13],
                        'dokter_pstore' => [15],
                        'accessories' => [10],
                        'perdana' => [18],
                        'laptop' => [16],
                        'tv' => [17],
                    ];

                    foreach ($otherStocks as $s) {
                        $name = strtolower($s->name);
                        $brand = strtolower($s->brand ?? '');
                        $qty = (int) $s->quantity;
                        // Normalize whitespace to merge duplicates like "Item Name " and "Item  Name"
                        $pName = preg_replace('/\s+/', ' ', trim($s->name));
                        $distId = $s->distributor_id;

                        $cat = null;
                        
                        if ((int)$distId === 6) {
                            $cat = 'apple_lux';
                        } elseif (in_array((int)$distId, $catDistMap['apply'])) {
                            $cat = 'apply';
                        } elseif (in_array((int)$distId, $catDistMap['accessories'])) {
                            $cat = 'accessories';
                        } elseif (in_array((int)$distId, $catDistMap['debs'])) {
                            $cat = 'debs';
                        } elseif (in_array((int)$distId, $catDistMap['arcis'])) {
                            $cat = 'arcis';
                        } elseif (in_array((int)$distId, $catDistMap['dokter_pstore'])) {
                            $cat = 'dokter_pstore';
                        } elseif (in_array((int)$distId, $catDistMap['laptop'])) {
                            $cat = 'laptop';
                        } elseif (in_array((int)$distId, $catDistMap['tv'])) {
                            $cat = 'tv';
                        } elseif (in_array((int)$distId, $catDistMap['perdana'])) {
                            // Split ID 18 between Sim Card (perdana) and 4G (jaringan)
                            if (str_contains($brand, 'jasa') || str_contains($name, 'jasa') || str_contains($name, '4g') || str_contains($name, 'jaringan')) {
                                $cat = 'jaringan';
                            } else {
                                $cat = 'perdana';
                            }
                        }

                        if ($cat) {
                            $stockReport[$cat] = ($stockReport[$cat] ?? 0) + $qty;
                            $stockDetails[$cat][$pName] = ($stockDetails[$cat][$pName] ?? 0) + $qty;
                        }
                    }


                    $aStatsQuery = DB::table('stock_outs')->whereIn('stock_outs.category', $salesCategories);
                    $applyLocalScope($aStatsQuery);
                    $aStatsList = $aStatsQuery->select('stock_outs.category', DB::raw("count(*) as qty"))->groupBy('stock_outs.category')->get()->pluck('qty', 'category');

                    // 5. Stock In Summary (Simplified to prevent crash/timeout on large ranges)
                    $inMap = [
                        'hp' => 0,
                        'apple_lux' => 0,
                        'accessories' => 0,
                        'apply' => 0,
                        'laptop' => 0,
                        'arcis' => 0,
                        'dokter_pstore' => 0,
                        'debs' => 0,
                        'perdana' => 0,
                        'jaringan' => 0
                    ];
                    $inDetails = [];

                    return [
                        'payments' => $pSums,
                        'payment_total' => $paymentTotal,
                        'dist_map' => $map,
                        'dist_map_rp' => $mapRp,
                        'dist_in_map' => $inMap,
                        'stock_report' => $stockReport,
                        'stock_details' => $stockDetails,
                        'sold_details' => $soldDetails,
                        'in_details' => $inDetails,
                        'activities' => $aStatsList,
                        'debug_dates' => ['start' => $startDate, 'end' => $endDate]
                    ];
                } catch (\Exception $e) {
                    return [
                        'payments' => [],
                        'payment_total' => 0,
                        'dist_map' => [],
                        'dist_map_rp' => [],
                        'dist_in_map' => [],
                        'stock_report' => [],
                        'sold_details' => [],
                        'stock_details' => [],
                        'in_details' => [],
                        'activities' => [],
                        'error' => $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine(),
                        'debug_dates' => ['start' => $startDate ?? 'not set', 'end' => $endDate ?? 'not set']
                    ];
                }
            }
        ]);

        // Process paginated data with pre-fetched relations
        $dailySales = collect($paginatedSales->items())->map(function ($trx) use ($paymentMethods, $branches, $onlineShops, $warehouses, $questions) {
            $details = [];
            $calculatedTotal = 0;

            $hpItems = $trx->items;
            $nonHpItems = $trx->nonHpDetails;

            $bundleHpId = null;
            $bundleNonHpId = null;

            if ($trx->is_bundle && ($hpItems->isNotEmpty() || $nonHpItems->isNotEmpty())) {
                $mainHp = $hpItems->first();
                $mainNonHp = $nonHpItems->first();
                $bundlePrice = 0;
                $bundleName = $trx->bundle_description ?: ($mainHp ? ($mainHp->product?->name . ' + BUNDLING') : 'PAKET BUNDLING');
                
                // Inheritance Logic: Bundle follows the main item's distributor
                $bundleDistributor = 'KOSONG';
                if ($mainHp) {
                    $dId = $mainHp->pivot->distributor_id ?? $mainHp->distributor_id;
                    if ($dId) {
                        $bundleDistributor = \App\Models\Distributor::find($dId)->name ?? 'KOSONG';
                    }
                }

                if ($mainHp) {
                    $bundlePrice += ($mainHp->pivot?->selling_price ?? 0) - ($mainHp->pivot?->item_discount ?? 0);
                    $bundleHpId = $mainHp->id;
                }
                if ($mainNonHp) {
                    $bundlePrice += ($mainNonHp->selling_price ?? 0) - ($mainNonHp->item_discount ?? 0);
                    $bundleNonHpId = $mainNonHp->id;
                }
                $details[] = [
                    'name' => $bundleName, 
                    'qty' => 1, 
                    'price' => $bundlePrice, 
                    'item_discount' => 0, 
                    'distributed_discount' => 0, 
                    'is_fixed' => true, 
                    'type' => 'Bundle', 
                    'imei' => $mainHp ? $mainHp->imei : '-',
                    'distributor_name' => $bundleDistributor // Inherited distributor
                ];
                $calculatedTotal += $bundlePrice;
            }

            foreach ($hpItems as $item) {
                // Determine distributor: history first, then current product link
                $dId = $item->pivot->distributor_id ?? $item->distributor_id;
                $dName = 'KOSONG';
                if ($dId) {
                    $dName = \App\Models\Distributor::find($dId)->name ?? 'KOSONG';
                }

                $netPrice = ($item->pivot?->selling_price ?? $item->selling_price ?? 0) - ($item->pivot?->item_discount ?? 0);
                
                $details[] = [
                    'name' => $item->product?->name ?? 'Unknown HP',
                    'qty' => 1,
                    'price' => $netPrice, // Show original price
                    'item_discount' => 0,
                    'distributed_discount' => 0,
                    'is_fixed' => true,
                    'brand' => $item->product?->brand ?? '-',
                    'type' => 'HP',
                    'imei' => $item->imei ?? '-',
                    'storage' => $item->storage ?? null,
                    'condition' => $item->condition === 'new' ? 'new' : ($item->condition === 'ex_ibox' ? 'ex_ibox' : ($item->condition ?? 'second')),
                    'distributor_name' => $dName
                ];
                
                if ($item->id !== $bundleHpId) {
                    $calculatedTotal += $netPrice;
                }
            }

            foreach ($nonHpItems as $item) {
                $qty = $item->quantity;
                $price = ($item->selling_price ?? 0) - ($item->item_discount ?? 0);
                
                $dId = $item->distributor_id;
                $dName = 'KOSONG';
                if ($dId) {
                    $dName = \App\Models\Distributor::find($dId)->name ?? 'KOSONG';
                }
                
                $details[] = [
                    'name' => $item->product?->name ?? 'Item Non-HP',
                    'qty' => $qty,
                    'price' => $price,
                    'item_discount' => 0,
                    'distributed_discount' => 0,
                    'is_fixed' => true,
                    'brand' => $item->product?->brand ?? '-',
                    'type' => 'Non-HP',
                    'category' => $item->product?->non_imei_category ?? null,
                    'imei' => '-',
                    'distributor_name' => $dName
                ];

                if ($item->id !== $bundleNonHpId) {
                    $calculatedTotal += ($price * $qty);
                }
            }

            $sourceUser = $trx->inventoryUser ?? $trx->user;
            $outletName = 'APEX POS';
            $outletAddress = 'Indonesia';
            if ($sourceUser) {
                if ($sourceUser->branch_id && isset($branches[$sourceUser->branch_id])) {
                    $b = $branches[$sourceUser->branch_id];
                    $outletName = $b->name;
                    $outletAddress = $b->address ?? '-';
                } elseif ($sourceUser->online_shop_id && isset($onlineShops[$sourceUser->online_shop_id])) {
                    $s = $onlineShops[$sourceUser->online_shop_id];
                    $outletName = $s->name;
                    $outletAddress = ucfirst($s->platform ?? 'Toko Online');
                } elseif ($sourceUser->warehouse_id && isset($warehouses[$sourceUser->warehouse_id])) {
                    $w = $warehouses[$sourceUser->warehouse_id];
                    $outletName = $w->name;
                    $outletAddress = $w->address ?? '-';
                }
            }

            $currentQuestions = $questions->get($trx->category, collect());
            $answers = $trx->auditAnswers ?? collect();
            $yesCount = $answers->where('answer', true)->count();
            $totalQuestions = $currentQuestions->count();
            foreach ($currentQuestions as $cq) {
                $existingAns = $answers->firstWhere('question_id', $cq->id);
                if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $cq->content)
                    $totalQuestions++;
            }
            foreach ($answers as $ans) {
                if ($ans->question_id === null || !$currentQuestions->contains('id', $ans->question_id))
                    $totalQuestions++;
            }

            $auditScore = ($answers->isNotEmpty() && $totalQuestions > 0) ? round(($yesCount / $totalQuestions) * 100) : null;

            $cash = 0;
            $transfer = 0;
            $edc = 0;
            $processedSplitPayments = [];
            $method = $paymentMethods->get($trx->payment_method_id);
            if ($trx->split_payments) {
                $splits = is_string($trx->split_payments) ? json_decode($trx->split_payments, true) : $trx->split_payments;
                foreach ((array) $splits as $sp) {
                    $mid = $sp['payment_method_id'] ?? ($sp['method_id'] ?? ($sp['id'] ?? null));
                    $amt = (float) ($sp['amount'] ?? 0);
                    $m = $paymentMethods->get($mid);
                    $mName = $m->name ?? 'Unknown';
                    $processedSplitPayments[] = ['method_name' => $mName, 'amount' => $amt];
                    $cat = strtolower($m?->category ?? '');
                    $nm = strtolower($mName);
                    if ($cat === 'tunai' || str_contains($nm, 'cash') || str_contains($nm, 'tunai'))
                        $cash += $amt;
                    elseif ($cat === 'edc' || str_contains($nm, 'edc') || str_contains($nm, 'debit'))
                        $edc += $amt;
                    else
                        $transfer += $amt;
                }
            } else {
                $mCat = strtolower($method?->category ?? '');
                $mName = strtolower($method?->name ?? '');
                if ($mCat === 'tunai' || str_contains($mName, 'cash') || str_contains($mName, 'tunai'))
                    $cash = $trx->selling_price;
                elseif ($mCat === 'edc' || str_contains($mName, 'edc') || str_contains($mName, 'debit'))
                    $edc = $trx->selling_price;
                else
                    $transfer = $trx->selling_price;
            }

            return [
                'id' => $trx->id,
                'date' => $trx->created_at?->toDateTimeString() ?? '-',
                'order_no' => $trx->receipt_id,
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? '-',
                'category' => $trx->category,
                'qty' => $hpItems->count() + $nonHpItems->sum('quantity'),
                'items' => $details,
                'cash' => $cash,
                'transfer' => $transfer,
                'edc' => $edc,
                'grand_total' => $trx->selling_price,
                'audit_score' => $auditScore,
                'audit_total' => $totalQuestions,
                'outlet_name' => $outletName,
                'outlet_address' => $outletAddress,
                'sales_name' => $trx->inventoryUser?->name ?? $trx->user?->name ?? 'PSTORE',
                'split_payments_data' => $processedSplitPayments,
                'brand_names' => collect()->concat($hpItems->map(fn($i) => $i->product?->brand))->concat($nonHpItems->map(fn($i) => $i->product?->brand))->unique()->filter()->implode(', ') ?: '-',
            ];
        });

        // Format other stats
        $formattedBrandSales = collect($brandSalesRaw['hp'])->map(fn($i) => [...(array) $i, 'is_hp' => true])
            ->concat(collect($brandSalesRaw['nhp'])->map(fn($i) => [...(array) $i, 'condition' => '-', 'storage' => '-', 'distributor' => '-', 'is_hp' => false]))
            ->toArray();

        return response()->json([
            'daily_sales' => ['data' => $dailySales, 'current_page' => $paginatedSales->currentPage(), 'last_page' => $paginatedSales->lastPage(), 'total' => $paginatedSales->total()],
            'brand_sales' => $formattedBrandSales,
            'type_sales' => $typeStatsRaw,
            'condition_sales' => $conditionStatsRaw,
            'distributor_sales' => $distributorStatsRaw,
            'cs_sales' => $csSalesRaw,
            'daily_history' => $dailyHistoryRaw,
            'report_summary' => $reportSummary,
            'filter_options' => ['products' => $soldProducts, 'distributors' => $soldDistributors]
        ]);
        ;

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
            $onlineShopIds = [];
            $warehouseIds = [];
            $distributorIds = [];
        } elseif ($requestedOnlineShopId) {
            $onlineShopIds = (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) ? [$requestedOnlineShopId] : [];
            $branchIds = [];
            $warehouseIds = [];
            $distributorIds = [];
        } elseif ($requestedWarehouseId) {
            $warehouseIds = (empty($warehouseIds) || in_array($requestedWarehouseId, $warehouseIds)) ? [$requestedWarehouseId] : [];
            $branchIds = [];
            $onlineShopIds = [];
            $distributorIds = [];
        } elseif ($requestedDistributorId) {
            $distributorIds = (empty($distributorIds) || in_array($requestedDistributorId, $distributorIds)) ? [$requestedDistributorId] : [];
            $branchIds = [];
            $onlineShopIds = [];
            $warehouseIds = [];
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

        // Use Octane to run independent counts in parallel for sub-100ms response
        // Extract IDs for serialization-friendly capture in closures
        $bIds = $branchIds;
        $osIds = $onlineShopIds;
        $wIds = $warehouseIds;
        $dIds = $distributorIds;

        [$hpStock, $nonHpStock, $inHp, $inNonHp, $outHp, $outNonHp] = Octane::concurrently([
            // 1. Current HP Stock
            fn() => ProductDetail::where('status', 'available')
                ->where(function ($q) use ($bIds, $osIds, $wIds, $dIds) {
                    if (!empty($bIds))
                        $q->orWhere(fn($s) => $s->where('placement_type', 'branch')->whereIn('placement_id', $bIds));
                    if (!empty($osIds))
                        $q->orWhere(fn($s) => $s->where('placement_type', 'online_shop')->whereIn('placement_id', $osIds));
                    if (!empty($wIds))
                        $q->orWhere(fn($s) => $s->where('placement_type', 'warehouse')->whereIn('placement_id', $wIds));
                    if (!empty($dIds))
                        $q->orWhere(fn($s) => $s->where('placement_type', 'distributor')->whereIn('placement_id', $dIds));
                })->count(),

            // 2. Current Non-HP Stock
            fn() => (int) Inventory::where(function ($q) use ($bIds, $osIds, $wIds, $dIds) {
                if (!empty($bIds))
                    $q->orWhere(fn($s) => $s->where('placement_type', 'branch')->whereIn('placement_id', $bIds));
                if (!empty($osIds))
                    $q->orWhere(fn($s) => $s->where('placement_type', 'online_shop')->whereIn('placement_id', $osIds));
                if (!empty($wIds))
                    $q->orWhere(fn($s) => $s->where('placement_type', 'warehouse')->whereIn('placement_id', $wIds));
                if (!empty($dIds))
                    $q->orWhere(fn($s) => $s->where('placement_type', 'distributor')->whereIn('placement_id', $dIds));
            })->sum('quantity'),

            // 3. Stock In HP (This Month)
            fn() => DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
                ->where('stock_outs.status', 'received')->whereMonth('stock_outs.reporting_date', now()->month)->whereYear('stock_outs.reporting_date', now()->year)
                ->where(function ($q) use ($bIds, $osIds, $wIds, $dIds) {
                    if (!empty($bIds))
                        $q->orWhere(fn($s) => $s->where('stock_outs.destination_type', 'branch')->whereIn('stock_outs.destination_id', $bIds));
                    if (!empty($osIds))
                        $q->orWhere(fn($s) => $s->where('stock_outs.destination_type', 'online_shop')->whereIn('stock_outs.destination_id', $osIds));
                    if (!empty($wIds))
                        $q->orWhere(fn($s) => $s->where('stock_outs.destination_type', 'warehouse')->whereIn('stock_outs.destination_id', $wIds));
                    if (!empty($dIds))
                        $q->orWhere(fn($s) => $s->where('stock_outs.destination_type', 'distributor')->whereIn('stock_outs.destination_id', $dIds));
                })->count(),

            // 4. Stock In Non-HP (This Month)
            fn() => (int) DB::table('stock_out_non_hp_items')->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
                ->where('stock_outs.status', 'received')->whereMonth('stock_outs.reporting_date', now()->month)->whereYear('stock_outs.reporting_date', now()->year)
                ->where(function ($q) use ($bIds, $osIds, $wIds, $dIds) {
                    if (!empty($bIds))
                        $q->orWhere(fn($s) => $s->where('stock_outs.destination_type', 'branch')->whereIn('stock_outs.destination_id', $bIds));
                    if (!empty($osIds))
                        $q->orWhere(fn($s) => $s->where('stock_outs.destination_type', 'online_shop')->whereIn('stock_outs.destination_id', $osIds));
                    if (!empty($wIds))
                        $q->orWhere(fn($s) => $s->where('stock_outs.destination_type', 'warehouse')->whereIn('stock_outs.destination_id', $wIds));
                    if (!empty($dIds))
                        $q->orWhere(fn($s) => $s->where('stock_outs.destination_type', 'distributor')->whereIn('stock_outs.destination_id', $dIds));
                })->sum('quantity'),

            // 5. Stock Out HP (This Month)
            fn() => DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->whereMonth('stock_outs.reporting_date', now()->month)->whereYear('stock_outs.reporting_date', now()->year)
                ->where(function ($q) use ($bIds, $osIds, $wIds, $dIds) {
                    if (!empty($bIds))
                        $q->orWhereIn('users.branch_id', $bIds);
                    if (!empty($osIds))
                        $q->orWhereIn('users.online_shop_id', $osIds);
                    if (!empty($wIds))
                        $q->orWhereIn('users.warehouse_id', $wIds);
                    if (!empty($dIds))
                        $q->orWhereIn('users.distributor_id', $dIds);
                })->count(),

            // 6. Stock Out Non-HP (This Month)
            fn() => (int) DB::table('stock_out_non_hp_items')->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->whereMonth('stock_outs.reporting_date', now()->month)->whereYear('stock_outs.reporting_date', now()->year)
                ->where(function ($q) use ($bIds, $osIds, $wIds, $dIds) {
                    if (!empty($bIds))
                        $q->orWhereIn('users.branch_id', $bIds);
                    if (!empty($osIds))
                        $q->orWhereIn('users.online_shop_id', $osIds);
                    if (!empty($wIds))
                        $q->orWhereIn('users.warehouse_id', $wIds);
                    if (!empty($dIds))
                        $q->orWhereIn('users.distributor_id', $dIds);
                })->sum('quantity'),
        ]);

        return response()->json([
            'stock' => $hpStock + $nonHpStock,
            'stock_hp' => $hpStock,
            'stock_non_hp' => $nonHpStock,
            'in' => $inHp + $inNonHp,
            'in_hp' => $inHp,
            'in_non_hp' => (int) $inNonHp,
            'out' => $outHp + $outNonHp,
            'out_hp' => $outHp,
            'out_non_hp' => (int) $outNonHp
        ]);
        ;
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
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
            $currentYear = $logicalNow->format('Y');
            $currentMonth = (int) $logicalNow->format('n');
            $prevMonth = $currentMonth === 1 ? 12 : $currentMonth - 1;
            // If restricted and restricted to current year, prevMonthYear must also be restricted to current year
            // But if it's January, then last month was last year.
            // Following 'only current year' literally:
            $prevMonthYear = $currentMonth === 1 ? $currentYear : $currentYear;

            // Enforce current year
            $year = $currentYear;

            // Enforce this month or last month
            if ($month) {
                $month = (int) $month;
                if ($month !== $currentMonth && ($month !== $prevMonth || $year !== $prevMonthYear)) {
                    $month = $currentMonth;
                }
            }
        }

        // Base Query Categories
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;
        $requestedDistributorId = $request->distributor_id;

        // Optimized Aggregation using SQL and Octane Concurrency
        [$summaryRaw, $trendRaw, $breakdownRaw] = Octane::concurrently([
            // 1. Summary Stats
            fn() => StockOut::whereIn('category', $salesCategories)->whereYear('reporting_date', $year)
                ->when($month, fn($q) => $q->whereMonth('reporting_date', $month))
                ->when($request->date, fn($q) => $q->where('reporting_date', $request->date))
                ->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        if ($requestedBranchId)
                            $sub->where('branch_id', $requestedBranchId);
                        elseif ($requestedOnlineShopId)
                            $sub->where('online_shop_id', $requestedOnlineShopId);
                        else {
                            if (!empty($branchIds))
                                $sub->orWhereIn('branch_id', $branchIds);
                            if (!empty($onlineShopIds))
                                $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        }
                    });
                })->selectRaw('SUM(selling_price) as revenue, COUNT(*) as trx_count')->first(),

            // 2. Daily Trend
            fn() => StockOut::whereIn('category', $salesCategories)->whereYear('reporting_date', $year)
                ->when($month, fn($q) => $q->whereMonth('reporting_date', $month))
                ->when($request->date, fn($q) => $q->where('reporting_date', $request->date))
                ->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        if ($requestedBranchId)
                            $sub->where('branch_id', $requestedBranchId);
                        elseif ($requestedOnlineShopId)
                            $sub->where('online_shop_id', $requestedOnlineShopId);
                        else {
                            if (!empty($branchIds))
                                $sub->orWhereIn('branch_id', $branchIds);
                            if (!empty($onlineShopIds))
                                $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        }
                    });
                })->groupBy('reporting_date')->select('reporting_date', DB::raw('SUM(selling_price) as revenue'))->get(),

            // 3. User Breakdown
            fn() => StockOut::whereIn('category', $salesCategories)->whereYear('reporting_date', $year)
                ->when($month, fn($q) => $q->whereMonth('reporting_date', $month))
                ->when($request->date, fn($q) => $q->where('reporting_date', $request->date))
                ->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    if ($requestedBranchId)
                        $sub->where('users.branch_id', $requestedBranchId);
                    elseif ($requestedOnlineShopId)
                        $sub->where('users.online_shop_id', $requestedOnlineShopId);
                    else {
                        if (!empty($branchIds))
                            $sub->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $sub->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                })->groupBy('users.id', 'users.name', 'users.full_name')
                ->select('users.name', 'users.full_name as full_name', DB::raw('SUM(selling_price) as revenue'), DB::raw('COUNT(*) as count'))
                ->get(),
        ]);
        ;

        return response()->json([
            'summary' => [
                'total_revenue' => (float) $summaryRaw->revenue,
                'total_items' => (int) $summaryRaw->trx_count, // Fallback to trx count for simplicity in aggregate
            ],
            'profit_trend' => $trendRaw->map(fn($t) => ['date' => $t->reporting_date, 'revenue' => (float) $t->revenue]),
            'sales_breakdown' => $breakdownRaw->map(fn($b) => ['name' => $b->full_name ?: $b->name, 'revenue' => (float) $b->revenue, 'items' => $b->count]),
        ]);
        ;
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
                        if (!empty($branchIds))
                            $sub->orWhereIn('branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds))
                            $sub->orWhereIn('warehouse_id', $warehouseIds);
                        if (!empty($distributorIds))
                            $sub->orWhereIn('distributor_id', $distributorIds);
                    }
                });
            });
        };

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'cancel_penjualan'];

        $dailySalesQuery = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'auditProfit'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$startDate, $endDate])
            ->when($request->category && $request->category !== 'all', function ($q) use ($request) {
                if ($request->category === 'orderan_online') {
                    $q->whereIn('category', ['shopee', 'orderan_online']);
                } else {
                    $q->where('category', $request->category);
                }
            });

        $scopeToAccess($dailySalesQuery);

        // Optimization: Pre-fetch meta data to avoid N+1 in loops
        $branches = Branch::all()->keyBy('id');
        $onlineShops = OnlineShop::all()->keyBy('id');
        $questions = Question::where('category', 'profit')->get();
        $paymentMethods = PaymentMethod::all()->keyBy('id');

        $paginatedProfit = $dailySalesQuery->latest()->paginate(50);

        $dailySales = collect($paginatedProfit->items())->map(function ($trx) use ($branches, $onlineShops, $questions, $paymentMethods) {
            $details = [];
            $calculatedTotal = 0;

            // HP Items
            foreach ($trx->items as $item) {
                $price = ($item->pivot->selling_price > 0) ? $item->pivot->selling_price : ($item->product->price ?? 0);
                $details[] = [
                    'id' => 'hp_' . $item->id,
                    'name' => $item->product->name ?? 'Unknown HP',
                    'qty' => 1,
                    'price' => $price,
                    'is_fixed' => true,
                    'brand' => $item->product->brand ?? '-',
                    'type' => 'HP',
                    'imei' => $item->imei ?? '-',
                    'storage' => $item->storage ?? null,
                    'condition' => $item->condition ?? 'second',
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
                        'brand' => $product->brand ?? '-',
                        'type' => 'Non-HP',
                        'raw_cost_price' => (float) ($product->cost_price ?? 0)
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

            // Gap handling (Disk/Admin)
            $remainingBalance = $trx->selling_price - $calculatedTotal;
            if (abs($remainingBalance) > 1) {
                $details[] = ['id' => 'gap_1', 'name' => $remainingBalance > 0 ? 'Biaya Admin / Tambahan' : 'Diskon', 'qty' => 1, 'price' => $remainingBalance, 'brand' => '-', 'type' => 'Lainnya', 'raw_cost_price' => 0];
            }

            // Outlet Name (Pre-fetched)
            $sourceUser = $trx->inventoryUser ?? $trx->user;
            $outletName = 'APEX POS';
            if ($sourceUser) {
                if ($sourceUser->branch_id && isset($branches[$sourceUser->branch_id]))
                    $outletName = $branches[$sourceUser->branch_id]->name;
                elseif ($sourceUser->online_shop_id && isset($onlineShops[$sourceUser->online_shop_id]))
                    $outletName = $onlineShops[$sourceUser->online_shop_id]->name;
            }

            // Profit & Audit Logic
            $savedProfit = $trx->auditProfit;
            $itemsModalData = $savedProfit ? ($savedProfit->items_modal ?? []) : [];
            $totalHargaModal = 0;
            foreach ($details as &$detail) {
                $itemJualTotal = $detail['price'] * $detail['qty'];
                $defaultItemModal = ($detail['raw_cost_price'] > 0) ? $detail['raw_cost_price'] : ($itemJualTotal > 0 ? round($itemJualTotal * 0.95) : 0);
                $savedItemModal = isset($itemsModalData[$detail['id']]) ? (float) $itemsModalData[$detail['id']] : null;
                $effectiveItemModal = $savedItemModal ?? $defaultItemModal;
                $detail['harga_jual'] = $itemJualTotal;
                $detail['default_harga_modal'] = $defaultItemModal;
                $detail['harga_modal'] = $savedItemModal;
                $detail['profit'] = $itemJualTotal - $effectiveItemModal;
                $detail['has_saved_modal'] = $savedItemModal !== null;
                $totalHargaModal += $effectiveItemModal;
            }
            unset($detail);

            $hargaJual = (float) ($trx->selling_price ?? 0);
            $hargaModal = $savedProfit ? (float) $savedProfit->harga_modal : null;
            $defaultHargaModal = $hargaJual > 0 ? round($hargaJual * 0.95) : 0;
            $profit = $hargaJual - $totalHargaModal;

            $answers = $trx->auditAnswers->filter(fn($a) => $questions->contains('id', $a->question_id) || $a->question_id === null);
            $yesCount = $answers->where('answer', true)->count();
            $totalQuestions = $questions->count();
            foreach ($questions as $cq) {
                $existingAns = $answers->firstWhere('question_id', $cq->id);
                if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $cq->content)
                    $totalQuestions++;
            }
            foreach ($answers as $ans)
                if ($ans->question_id === null || !$questions->contains('id', $ans->question_id))
                    $totalQuestions++;

            $auditScore = ($answers->isNotEmpty() && $totalQuestions > 0) ? round(($yesCount / $totalQuestions) * 100) : null;

            // Split Payments (Pre-fetched)
            $processedSplitPayments = [];
            if ($trx->split_payments) {
                $splits = is_string($trx->split_payments) ? json_decode($trx->split_payments, true) : $trx->split_payments;
                foreach ((array) $splits as $sp) {
                    $mid = $sp['payment_method_id'] ?? ($sp['method_id'] ?? null);
                    $processedSplitPayments[] = ['method_name' => $paymentMethods[$mid]->name ?? 'Unknown', 'amount' => (float) ($sp['amount'] ?? 0)];
                }
            }

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'order_no' => $trx->receipt_id,
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? '-',
                'category' => $trx->category,
                'qty' => count($details),
                'items' => $details,
                'harga_jual' => $hargaJual,
                'harga_modal' => $hargaModal,
                'default_harga_modal' => $defaultHargaModal,
                'profit' => $profit,
                'outlet_name' => $outletName,
                'audit_score' => $auditScore,
                'audit_total' => $totalQuestions,
                'audit_yes' => $yesCount,
                'inventory_account_name' => $trx->inventoryUser->full_name ?? $trx->user->full_name ?? '-',
                'split_payments_data' => $processedSplitPayments,
                'selling_price' => $hargaJual,
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
        ;
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
                        if (!empty($branchIds))
                            $sub->orWhereIn('branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds))
                            $sub->orWhereIn('warehouse_id', $warehouseIds);
                        if (!empty($distributorIds))
                            $sub->orWhereIn('distributor_id', $distributorIds);
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

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;
        $requestedDistributorId = $request->distributor_id;

        $categories = ['barang_masuk', 'pindah_cabang_masuk', 'pindah_cabang'];

        $query = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'destination'])
            ->whereIn('category', $categories)
            ->whereBetween('reporting_date', [$startDate, $endDate])
            ->when($request->category && $request->category !== 'all', function ($q) use ($request) {
                $q->where('category', $request->category);
            });

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
                        if (!empty($branchIds))
                            $sq->orWhereIn('branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $sq->orWhereIn('online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds))
                            $sq->orWhereIn('warehouse_id', $warehouseIds);
                        if (!empty($distributorIds))
                            $sq->orWhereIn('distributor_id', $distributorIds);
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
                    if ($branch)
                        $outletName = $branch->name;
                } elseif ($invUser->online_shop_id) {
                    $shop = \App\Models\OnlineShop::find($invUser->online_shop_id);
                    if ($shop)
                        $outletName = $shop->name;
                } elseif ($invUser->warehouse_id) {
                    $warehouse = \App\Models\Warehouse::find($invUser->warehouse_id);
                    if ($warehouse)
                        $outletName = $warehouse->name;
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
            ->whereBetween('reporting_date', [$startDate, $endDate])
            ->when($request->category && $request->category !== 'all', function ($q) use ($request) {
                if ($request->category === 'orderan_online') {
                    $q->whereIn('category', ['shopee', 'orderan_online']);
                } else {
                    $q->where('category', $request->category);
                }
            });

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
                    if (!empty($branchIds))
                        $sq->orWhereIn('branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $sq->orWhereIn('online_shop_id', $onlineShopIds);
                    if (!empty($warehouseIds))
                        $sq->orWhereIn('warehouse_id', $warehouseIds);
                    if (!empty($distributorIds))
                        $sq->orWhereIn('distributor_id', $distributorIds);
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
                    if (!empty($branchIds))
                        $sq->orWhereIn('branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $sq->orWhereIn('online_shop_id', $onlineShopIds);
                    if (!empty($warehouseIds))
                        $sq->orWhereIn('warehouse_id', $warehouseIds);
                    if (!empty($distributorIds))
                        $sq->orWhereIn('distributor_id', $distributorIds);
                }
            });
        });

        $paginatedOut = $query->latest()->paginate(50);

        // Pre-fetch meta data to avoid N+1 in map loop
        $branches = Branch::all()->keyBy('id');
        $onlineShops = OnlineShop::all()->keyBy('id');
        $warehouses = Warehouse::all()->keyBy('id');
        $questions = Question::all()->groupBy('category');

        $records = collect($paginatedOut->items())->map(function ($trx) use ($branches, $onlineShops, $warehouses, $questions) {
            $hpItemsCount = $trx->items->count();
            $nonHpItemsCount = 0;
            if ($trx->items->isEmpty()) {
                if (is_array($trx->non_hp_items))
                    $nonHpItemsCount = collect($trx->non_hp_items)->sum('quantity');
                elseif ($trx->nonHpItems->isNotEmpty())
                    $nonHpItemsCount = $trx->nonHpItems->sum('quantity');
            }

            $answers = $trx->auditAnswers;
            $yesCount = $answers->where('answer', true)->count();
            $currentQuestions = $questions->get($trx->category, collect());
            $totalQuestions = $currentQuestions->count();

            foreach ($currentQuestions as $cq) {
                $existingAns = $answers->firstWhere('question_id', $cq->id);
                if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $cq->content)
                    $totalQuestions++;
            }
            foreach ($answers as $ans) {
                if ($ans->question_id === null || !$currentQuestions->contains('id', $ans->question_id))
                    $totalQuestions++;
            }

            $score = ($answers->isNotEmpty() && $totalQuestions > 0) ? round(($yesCount / $totalQuestions) * 100) : null;

            $sourceLabel = 'Manual Entry';
            if ($trx->category === 'pindah_cabang' && $trx->destination)
                $sourceLabel = $trx->destination->name;
            elseif (in_array($trx->category, ['penjualan_offline', 'orderan_online']))
                $sourceLabel = 'Customer';

            $outletName = 'APEX POS';
            $invUser = $trx->inventoryUser ?? $trx->user;
            if ($invUser) {
                if ($invUser->branch_id && isset($branches[$invUser->branch_id]))
                    $outletName = $branches[$invUser->branch_id]->name;
                elseif ($invUser->online_shop_id && isset($onlineShops[$invUser->online_shop_id]))
                    $outletName = $onlineShops[$invUser->online_shop_id]->name;
                elseif ($invUser->warehouse_id && isset($warehouses[$invUser->warehouse_id]))
                    $outletName = $warehouses[$invUser->warehouse_id]->name;
            }

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'receipt_id' => $trx->receipt_id,
                'category' => $trx->category,
                'type' => $hpItemsCount > 0 ? 'HP' : 'Non-HP',
                'brand_names' => collect()->concat($trx->items->map(fn($i) => $i->product->brand ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->brand ?? '-'))->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-',
                'product_names' => collect()->concat($trx->items->map(fn($i) => $i->product->name ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->name ?? '-'))->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: '-',
                'imeis' => $trx->items->map(fn($i) => $i->imei)->filter()->implode(', ') ?: '-',
                'qty' => $hpItemsCount + $nonHpItemsCount,
                'source' => $sourceLabel,
                'outlet_name' => $outletName,
                'audit_score' => $score,
                'audit_total' => $totalQuestions,
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
