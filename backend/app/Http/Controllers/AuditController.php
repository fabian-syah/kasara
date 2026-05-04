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
        try {
            $user = $request->user();
            $branchIds = $user->getAccessibleBranchIds();
            $onlineShopIds = $user->getAccessibleOnlineShopIds();
            $warehouseIds = $user->getAccessibleWarehouseIds();
            $distributorIds = $user->getAccessibleDistributorIds();

            // Global exclusion for super_admin and analist roles
            if ($user->hasAnyRole(['super_admin', 'analist', 'analis'])) {
                $excludedTerms = ['trial', 'huft', 'anu', 'test', 'testing'];
                
                if (!empty($branchIds)) {
                    $branchIds = Branch::whereIn('id', $branchIds)
                        ->where(function ($q) use ($excludedTerms) {
                            foreach ($excludedTerms as $term) {
                                $q->where('name', 'not ilike', '%' . $term . '%');
                            }
                        })->pluck('id')->toArray();
                }

                if (!empty($onlineShopIds)) {
                    $onlineShopIds = OnlineShop::whereIn('id', $onlineShopIds)
                        ->where(function ($q) use ($excludedTerms) {
                            foreach ($excludedTerms as $term) {
                                $q->where('name', 'not ilike', '%' . $term . '%');
                            }
                        })->pluck('id')->toArray();
                }

                if (!empty($warehouseIds)) {
                    $warehouseIds = Warehouse::whereIn('id', $warehouseIds)
                        ->where(function ($q) use ($excludedTerms) {
                            foreach ($excludedTerms as $term) {
                                $q->where('name', 'not ilike', '%' . $term . '%');
                            }
                        })->pluck('id')->toArray();
                }
            }

            if (empty($branchIds) && empty($onlineShopIds) && empty($warehouseIds) && empty($distributorIds)) {
                return response()->json([
                    'daily_sales' => ['data' => []],
                    'brand_sales' => [],
                    'cs_sales' => [],
                    'report_summary' => null
                ]);
            }

            $startDate = $request->filled('start_date') ? $request->start_date : now()->startOfMonth()->toDateString();
            $endDate = $request->filled('end_date') ? $request->end_date : now()->toDateString();

            // No more clipping logic here to prevent data loss on monthly reports.
            // Frontend will handle valid date ranges.

            $requestedBranchId = $request->branch_id;
            $requestedOnlineShopId = $request->online_shop_id;
            $requestedDistributorId = $request->distributor_id;
            $requestedWarehouseId = $request->warehouse_id;
            $requestedLocationType = $request->location_type; // Capture location type (branch/online)

            // Stock all-time logic
            $stockStartDate = '2000-01-01';
            $stockEndDate = now()->toDateString();

            $isUnrestricted = $user->hasAnyRole(['super_admin', 'owner', 'pimpinan', 'management', 'admin', 'analist', 'analis', 'leader', 'developer', 'pimpinan_pusat']);
            $isAnalist = $user->hasAnyRole(['analist', 'analis']);
            $isSuperAdmin = $user->hasRole('super_admin');
            $currentRoles = $user->roles()->pluck('name')->toArray();

            // Fallback: If ID is not numeric, it might be a name
            if ($requestedBranchId && !is_numeric($requestedBranchId)) {
                $foundBranch = Branch::where('name', 'ilike', '%' . $requestedBranchId . '%')->first();
                $requestedBranchId = $foundBranch ? $foundBranch->id : null;
            }
            if ($requestedOnlineShopId && !is_numeric($requestedOnlineShopId)) {
                $foundOs = OnlineShop::where('name', 'ilike', '%' . $requestedOnlineShopId . '%')->first();
                $requestedOnlineShopId = $foundOs ? $foundOs->id : null;
            }
            if ($requestedWarehouseId && !is_numeric($requestedWarehouseId)) {
                $foundWarehouse = Warehouse::where('name', 'ilike', '%' . $requestedWarehouseId . '%')->first();
                $requestedWarehouseId = $foundWarehouse ? $foundWarehouse->id : null;
            }
            if ($requestedDistributorId && !is_numeric($requestedDistributorId)) {
                $foundDistributor = Distributor::where('name', 'ilike', '%' . $requestedDistributorId . '%')->first();
                $requestedDistributorId = $foundDistributor ? $foundDistributor->id : null;
            }

            $requestedCategory = $request->category;
            $requestedSearch = $request->search;
            $requestedCondition = $request->condition;
            $requestedProductTypeId = $request->product_type_id;
            $requestedCapacity = $request->capacity;

            // Apply global partitioning based on location type
            if ($requestedLocationType === 'branch') {
                $onlineShopIds = [];
                $warehouseIds = [];
                $distributorIds = [];
            } elseif ($requestedLocationType === 'online') {
                $branchIds = [];
                $warehouseIds = [];
                $distributorIds = [];
            }

            $scopeToAccess = function ($query) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                $query->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    if ($requestedBranchId) {
                        $q->where('stock_outs.branch_id', $requestedBranchId)
                            ->orWhereHas('user', fn($uq) => $uq->where('branch_id', $requestedBranchId));
                    } elseif ($requestedOnlineShopId) {
                        $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)
                            ->orWhereHas('user', fn($uq) => $uq->where('online_shop_id', $requestedOnlineShopId));
                    } elseif ($requestedWarehouseId) {
                        $q->where('stock_outs.warehouse_id', $requestedWarehouseId)
                            ->orWhereHas('user', fn($uq) => $uq->where('warehouse_id', $requestedWarehouseId));
                    } elseif ($requestedDistributorId) {
                        $q->whereHas('user', fn($uq) => $uq->where('distributor_id', $requestedDistributorId));
                    } else {
                        $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                            if (!empty($branchIds)) {
                                $sub->orWhereIn('stock_outs.branch_id', $branchIds)
                                    ->orWhereHas('user', fn($uq) => $uq->whereIn('branch_id', $branchIds));
                            }
                            if (!empty($onlineShopIds)) {
                                $sub->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)
                                    ->orWhereHas('user', fn($uq) => $uq->whereIn('online_shop_id', $onlineShopIds));
                            }
                            if (!empty($warehouseIds)) {
                                $sub->orWhereIn('stock_outs.warehouse_id', $warehouseIds)
                                    ->orWhereHas('user', fn($uq) => $uq->whereIn('warehouse_id', $warehouseIds));
                            }
                            if (!empty($distributorIds)) {
                                $sub->orWhereHas('user', fn($uq) => $uq->whereIn('distributor_id', $distributorIds));
                            }
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

            $successCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'sale', 'pos', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store'];
            $activityCategories = ['refund', 'angkat_barang'];
            $salesCategories = array_merge($successCategories, $activityCategories);

            // Optimization: Pre-fetch meta data to avoid N+1 in loops
            $branches = Branch::all()->keyBy('id');
            $onlineShops = OnlineShop::all()->keyBy('id');
            $warehouses = Warehouse::all()->keyBy('id');
            $questions = Question::all()->groupBy('category');
            $paymentMethods = PaymentMethod::all()->keyBy('id');
            $distributors = Distributor::all()->keyBy('id');

            // Define a manual helper because closures inside Octane can be tricky
            $helper_scopeUser = function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    if ($requestedBranchId) {
                        $sub->where('users.branch_id', $requestedBranchId);
                    } elseif ($requestedOnlineShopId) {
                        $sub->where('users.online_shop_id', $requestedOnlineShopId);
                    } elseif ($requestedWarehouseId) {
                        $sub->where('users.warehouse_id', $requestedWarehouseId);
                    } elseif ($requestedDistributorId) {
                        $sub->where('users.distributor_id', $requestedDistributorId);
                    } else {
                        if (!empty($branchIds))
                            $sub->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $sub->orWhereIn('users.online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds))
                            $sub->orWhereIn('users.warehouse_id', $warehouseIds);
                        if (!empty($distributorIds))
                            $sub->orWhereIn('users.distributor_id', $distributorIds);

                        // If restricted but no access
                        if (empty($branchIds) && empty($onlineShopIds) && empty($warehouseIds) && empty($distributorIds)) {
                            $sub->whereRaw('1=0');
                        }
                    }
                });
            };

            // Use Octane to run independent queries in parallel
            [$paginatedSales, $brandSalesRaw, $csSalesRaw, $dailyHistoryRaw, $typeStatsRaw, $conditionStatsRaw, $distributorStatsRaw, $soldProducts, $soldDistributors, $reportSummary] = Octane::concurrently([
                // 1. Paginated Sales Query
                function () use ($salesCategories, $startDate, $endDate, $requestedCategory, $requestedSearch, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $isAnalist) {
                    return StockOut::with(['items.product', 'items.distributor', 'nonHpDetails.product', 'nonHpDetails.distributor', 'user.branch', 'inventoryUser.branch', 'auditAnswers', 'paymentMethod'])
                        ->whereIn('category', $salesCategories)
                        ->where(function ($q) use ($startDate, $endDate) {
                            $startTS = $startDate . ' 05:00:00';
                            $endTS = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59';
                            $q->whereBetween('reporting_date', [$startDate, $endDate])
                              ->orWhereBetween('created_at', [$startTS, $endTS]);
                        })
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
                        ->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                            if ($requestedBranchId) {
                                $q->where('stock_outs.branch_id', $requestedBranchId)
                                    ->orWhereHas('user', fn($uq) => $uq->where('branch_id', $requestedBranchId));
                            } elseif ($requestedOnlineShopId) {
                                $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)
                                    ->orWhereHas('user', fn($uq) => $uq->where('online_shop_id', $requestedOnlineShopId));
                            } elseif ($requestedWarehouseId) {
                                $q->where('stock_outs.warehouse_id', $requestedWarehouseId)
                                    ->orWhereHas('user', fn($uq) => $uq->where('warehouse_id', $requestedWarehouseId));
                            } elseif ($requestedDistributorId) {
                                $q->whereHas('user', fn($uq) => $uq->where('distributor_id', $requestedDistributorId));
                            } else {
                                $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                                    if (!empty($branchIds)) {
                                        $sub->orWhereIn('stock_outs.branch_id', $branchIds)
                                            ->orWhereHas('user', fn($uq) => $uq->whereIn('branch_id', $branchIds));
                                    }
                                    if (!empty($onlineShopIds)) {
                                        $sub->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)
                                            ->orWhereHas('user', fn($uq) => $uq->whereIn('online_shop_id', $onlineShopIds));
                                    }
                                    if (!empty($warehouseIds)) {
                                        $sub->orWhereIn('stock_outs.warehouse_id', $warehouseIds)
                                            ->orWhereHas('user', fn($uq) => $uq->whereIn('warehouse_id', $warehouseIds));
                                    }
                                    if (!empty($distributorIds)) {
                                        $sub->orWhereHas('user', fn($uq) => $uq->whereIn('distributor_id', $distributorIds));
                                    }
                                });
                            }
                        })
                        ->latest()->paginate(50);
                },

                // 2. Brand Stats
                function () use ($successCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $requestedCondition, $requestedProductTypeId, $requestedCapacity, $isAnalist) {
                    $startTS = $startDate . ' 05:00:00';
                    $endTS = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59';

                    $hpQuery = DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->join('products', 'product_details.product_id', '=', 'products.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->leftJoin('distributors', 'product_details.distributor_id', '=', 'distributors.id')->whereIn('stock_outs.category', $successCategories)
                        ->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
                            $q->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                              ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
                        })
                        ->when($requestedCondition, fn($q) => $q->where('product_details.condition', $requestedCondition))->when($requestedProductTypeId, fn($q) => $q->where('products.id', $requestedProductTypeId))->when($requestedCapacity, fn($q) => $q->where('product_details.storage', $requestedCapacity))->when($requestedDistributorId, fn($q) => $q->where('product_details.distributor_id', $requestedDistributorId))->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        $userCheck = function($sq, $col, $ids) {
                            $sq->orWhereIn("stock_outs.$col", $ids)
                               ->orWhereExists(function($sub) use ($col, $ids) {
                                   $sub->select(DB::raw(1))->from('users')
                                       ->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")
                                       ->whereIn("users.$col", $ids);
                               });
                        };

                        if ($requestedBranchId) {
                            $q->where('stock_outs.branch_id', $requestedBranchId)
                              ->orWhereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.branch_id', $requestedBranchId));
                        } elseif ($requestedOnlineShopId) {
                            $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)
                              ->orWhereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.online_shop_id', $requestedOnlineShopId));
                        } elseif ($requestedWarehouseId) {
                            $q->where('stock_outs.warehouse_id', $requestedWarehouseId)
                              ->orWhereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.warehouse_id', $requestedWarehouseId));
                        } elseif ($requestedDistributorId) {
                            $q->whereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.distributor_id', $requestedDistributorId));
                        } else {
                            $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $userCheck) {
                                if (!empty($branchIds)) $userCheck($sub, 'branch_id', $branchIds);
                                if (!empty($onlineShopIds)) $userCheck($sub, 'online_shop_id', $onlineShopIds);
                                if (!empty($warehouseIds)) $userCheck($sub, 'warehouse_id', $warehouseIds);
                                if (!empty($distributorIds)) {
                                    $sub->orWhereExists(fn($ssq) => $ssq->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->whereIn('users.distributor_id', $distributorIds));
                                }
                            });
                        }
                    })->select('products.brand', 'products.name', 'product_details.condition', 'product_details.storage', 'distributors.name as distributor_name', DB::raw('count(*) as qty'))->groupBy('products.brand', 'products.name', 'product_details.condition', 'product_details.storage', 'distributors.name')->get();
                    $nhpQuery = DB::table('stock_out_non_hp_items')->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $successCategories)
                        ->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
                            $q->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                              ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
                        })
                        ->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        if ($requestedBranchId) {
                            $q->where('stock_outs.branch_id', $requestedBranchId)
                              ->orWhereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.branch_id', $requestedBranchId));
                        } elseif ($requestedOnlineShopId) {
                            $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)
                              ->orWhereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.online_shop_id', $requestedOnlineShopId));
                        } elseif ($requestedWarehouseId) {
                            $q->where('stock_outs.warehouse_id', $requestedWarehouseId)
                              ->orWhereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.warehouse_id', $requestedWarehouseId));
                        } elseif ($requestedDistributorId) {
                            $q->whereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.distributor_id', $requestedDistributorId));
                        } else {
                            $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                                if (!empty($branchIds)) {
                                    $sub->orWhereIn('stock_outs.branch_id', $branchIds)
                                        ->orWhereExists(fn($ssq) => $ssq->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->whereIn('users.branch_id', $branchIds));
                                }
                                if (!empty($onlineShopIds)) {
                                    $sub->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)
                                        ->orWhereExists(fn($ssq) => $ssq->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->whereIn('users.online_shop_id', $onlineShopIds));
                                }
                            });
                        }
                    })->select('products.brand', 'products.name', DB::raw('sum(quantity) as qty'))->groupBy('products.brand', 'products.name')->get();
                    return ['hp' => $hpQuery, 'nhp' => $nhpQuery];
                },

                // 3. CS Sales Stats
                function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $isAnalist) {
                    $baseQuery = DB::table('stock_outs')->leftJoin('users', 'stock_outs.user_id', '=', 'users.id');

                    // AUTO-REPAIR: If inventory_user_id is missing or same as user_id but sales_account string exists, sync it
                    // This fixes existing transactions that were misattributed to the main account
                    DB::table('stock_outs')
                        ->where(function($q) {
                            $q->whereNull('inventory_user_id')
                              ->orWhereRaw('inventory_user_id = user_id');
                        })
                        ->whereNotNull('sales_account')
                        ->whereBetween('reporting_date', [$startDate, $endDate])
                        ->get()
                        ->each(function($trx) {
                            $user = \App\Models\User::where('name', $trx->sales_account)->first();
                            if ($user && $user->id != $trx->inventory_user_id) {
                                DB::table('stock_outs')->where('id', $trx->id)->update(['inventory_user_id' => $user->id]);
                            }
                        });
                    
                    // Unified date logic with created_at fallback
                    $startTS = $startDate . ' 05:00:00';
                    $endTS = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59';
                    $baseQuery->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
                        $q->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                          ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
                    });

                    $baseQuery->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $isAnalist) {
                        $userCheck = function($sq, $col, $ids) {
                            $sq->orWhereIn("stock_outs.$col", $ids)
                               ->orWhere(function ($ssq) use ($col, $ids) {
                                   $ssq->whereNull("stock_outs.$col")
                                       ->whereExists(function($sub) use ($col, $ids) {
                                           $sub->select(DB::raw(1))->from('users')
                                               ->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")
                                               ->whereIn("users.$col", $ids);
                                       });
                               });
                        };

                        if ($requestedBranchId) {
                            $q->where('stock_outs.branch_id', $requestedBranchId)
                              ->orWhere(fn($sq) => $sq->whereNull('stock_outs.branch_id')->whereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.branch_id', $requestedBranchId)));
                        } elseif ($requestedOnlineShopId) {
                            $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)
                              ->orWhere(fn($sq) => $sq->whereNull('stock_outs.online_shop_id')->whereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.online_shop_id', $requestedOnlineShopId)));
                        } elseif ($requestedWarehouseId) {
                            $q->where('stock_outs.warehouse_id', $requestedWarehouseId)
                              ->orWhere(fn($sq) => $sq->whereNull('stock_outs.warehouse_id')->whereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.warehouse_id', $requestedWarehouseId)));
                        } elseif ($requestedDistributorId) {
                            $q->whereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.distributor_id', $requestedDistributorId));
                        } else {
                            $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $userCheck) {
                                if (!empty($branchIds)) $userCheck($sub, 'branch_id', $branchIds);
                                if (!empty($onlineShopIds)) $userCheck($sub, 'online_shop_id', $onlineShopIds);
                                if (!empty($warehouseIds)) $userCheck($sub, 'warehouse_id', $warehouseIds);
                                if (!empty($distributorIds)) {
                                    $sub->orWhereExists(fn($ssq) => $ssq->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->whereIn('users.distributor_id', $distributorIds));
                                }
                            });
                        }
                    });

                    // Breakdown Query - Include All (Sales & Activity)
                    $breakdownBase = (clone $baseQuery);

                    $hpBreakdown = (clone $breakdownBase)->join('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
                        ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                        ->join('products', 'product_details.product_id', '=', 'products.id')
                        ->leftJoin('distributors', 'product_details.distributor_id', '=', 'distributors.id')
                        ->select(
                            DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id) as owner_id'),
                            'products.brand',
                            'products.name',
                            'product_details.condition',
                            'product_details.storage',
                            'distributors.name as distributor',
                            'stock_outs.category',
                            DB::raw('count(*) as qty')
                        )
                        ->groupBy('owner_id', 'products.brand', 'products.name', 'product_details.condition', 'product_details.storage', 'distributors.name', 'stock_outs.category')
                        ->get()->groupBy('owner_id');

                    $nhpBreakdown = (clone $breakdownBase)->join('stock_out_non_hp_items', 'stock_outs.id', '=', 'stock_out_non_hp_items.stock_out_id')
                        ->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')
                        ->leftJoin('distributors', 'stock_out_non_hp_items.distributor_id', '=', 'distributors.id')
                        ->select(
                            DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id) as owner_id'),
                            'products.brand',
                            'products.name',
                            'distributors.name as distributor',
                            'stock_outs.category',
                            DB::raw('sum(stock_out_non_hp_items.quantity) as qty')
                        )
                        ->groupBy('owner_id', 'products.brand', 'products.name', 'distributors.name', 'stock_outs.category')
                        ->get()->groupBy('owner_id');

                    // Define specific categories
                    $stdSalesCats = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'sale', 'pos', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'bundling'];
                    $activityCats = ['tukar_unit', 'tukar_tambah', 'downgrade', 'angkat_barang', 'refund', 'retur'];

                    // Sales Stats Query (Only standard sales)
                    $salesBase = (clone $baseQuery)->where(function($q) use ($stdSalesCats) {
                        $q->whereIn('stock_outs.category', $stdSalesCats)
                          ->orWhereNull('stock_outs.category');
                    });
                    
                    $itemStatsQuery = (clone $salesBase)->leftJoin('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')->leftJoin('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->leftJoin('products', 'product_details.product_id', '=', 'products.id')->select(DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id) as owner_id'), DB::raw("sum(case when UPPER(products.brand) LIKE '%APPLE%' OR UPPER(products.brand) LIKE '%IPHONE%' then 1 else 0 end) as iphone_units"), DB::raw("sum(case when UPPER(products.brand) NOT LIKE '%APPLE%' AND UPPER(products.brand) NOT LIKE '%IPHONE%' and products.brand is not null then 1 else 0 end) as android_units"))->groupBy('owner_id')->get()->keyBy('owner_id');
                    $nhpStatsQuery = (clone $salesBase)->leftJoin('stock_out_non_hp_items', 'stock_outs.id', '=', 'stock_out_non_hp_items.stock_out_id')->select(DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id) as owner_id'), DB::raw("sum(stock_out_non_hp_items.quantity) as non_hp_units"))->groupBy('owner_id')->get()->keyBy('owner_id');

                    // Activity Unit breakdown (IMEI + Non-HP)
                    $activityItemQuery = (clone $baseQuery)->whereIn('stock_outs.category', ['refund', 'tukar_tambah', 'downgrade', 'angkat_barang', 'tukar_unit', 'retur'])
                        ->leftJoin('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
                        ->select(DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id) as owner_id'), 
                            DB::raw("sum(case when stock_outs.category = 'tukar_unit' then 1 else 0 end) as tu_imei"),
                            DB::raw("sum(case when stock_outs.category = 'tukar_tambah' then 1 else 0 end) as tt_imei"),
                            DB::raw("sum(case when stock_outs.category = 'downgrade' then 1 else 0 end) as dw_imei"),
                            DB::raw("sum(case when stock_outs.category = 'angkat_barang' then 1 else 0 end) as ab_imei"),
                            DB::raw("sum(case when stock_outs.category = 'refund' then 1 else 0 end) as rf_imei"),
                            DB::raw("sum(case when stock_outs.category = 'retur' then 1 else 0 end) as rt_imei")
                        )->groupBy('owner_id')->get()->keyBy('owner_id');

                    $activityNhpQuery = (clone $baseQuery)->whereIn('stock_outs.category', ['refund', 'tukar_tambah', 'downgrade', 'angkat_barang', 'tukar_unit', 'retur'])
                        ->leftJoin('stock_out_non_hp_items', 'stock_outs.id', '=', 'stock_out_non_hp_items.stock_out_id')
                        ->select(DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id) as owner_id'), 
                            DB::raw("sum(case when stock_outs.category = 'tukar_unit' then stock_out_non_hp_items.quantity else 0 end) as tu_nhp"),
                            DB::raw("sum(case when stock_outs.category = 'tukar_tambah' then stock_out_non_hp_items.quantity else 0 end) as tt_nhp"),
                            DB::raw("sum(case when stock_outs.category = 'downgrade' then stock_out_non_hp_items.quantity else 0 end) as dw_nhp"),
                            DB::raw("sum(case when stock_outs.category = 'angkat_barang' then stock_out_non_hp_items.quantity else 0 end) as ab_nhp"),
                            DB::raw("sum(case when stock_outs.category = 'refund' then stock_out_non_hp_items.quantity else 0 end) as rf_nhp"),
                            DB::raw("sum(case when stock_outs.category = 'retur' then stock_out_non_hp_items.quantity else 0 end) as rt_nhp")
                        )->groupBy('owner_id')->get()->keyBy('owner_id');
                    
                    $mainStats = (clone $baseQuery)->leftJoin('users as owners', function ($join) {
                        $join->on('owners.id', '=', DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id)'));
                    })->select('owners.id as owner_id', 'owners.name as cs_name', 'owners.full_name as full_name', 'owners.photo as photo', 'owners.photo_inventory as photo_inv', 
                        DB::raw("sum(case when stock_outs.category IS NULL OR stock_outs.category IN ('" . implode("','", $stdSalesCats) . "') then stock_outs.selling_price else 0 end) as grand_total"), 
                        DB::raw("sum(case when stock_outs.category IN ('refund', 'angkat_barang') then stock_outs.selling_price else 0 end) as total_activity_rp"))
                        ->groupBy('owners.id', 'owners.name', 'owners.full_name', 'owners.photo', 'owners.photo_inventory')->get();
                    
                    return $mainStats->map(function ($stat) use ($itemStatsQuery, $nhpStatsQuery, $activityItemQuery, $activityNhpQuery, $hpBreakdown, $nhpBreakdown) {
                        $items = $itemStatsQuery->get($stat->owner_id);
                        $nhp = $nhpStatsQuery->get($stat->owner_id);
                        $actItems = $activityItemQuery->get($stat->owner_id);
                        $actNhp = $activityNhpQuery->get($stat->owner_id);

                        $stat->total_tu = (int)($actItems->tu_imei ?? 0) + (int)($actNhp->tu_nhp ?? 0);
                        $stat->total_tt = (int)($actItems->tt_imei ?? 0) + (int)($actNhp->tt_nhp ?? 0);
                        $stat->total_dw = (int)($actItems->dw_imei ?? 0) + (int)($actNhp->dw_nhp ?? 0);
                        $stat->total_ab = (int)($actItems->ab_imei ?? 0) + (int)($actNhp->ab_nhp ?? 0);
                        $stat->total_refund = (int)($actItems->rf_imei ?? 0) + (int)($actNhp->rf_nhp ?? 0);
                        $stat->total_retur = (int)($actItems->rt_imei ?? 0) + (int)($actNhp->rt_nhp ?? 0);
                        $iphone = (int) ($items->iphone_units ?? 0);
                        $android = (int) ($items->android_units ?? 0);
                        $nonHp = (int) ($nhp->non_hp_units ?? 0);

                        $breakdown = ($hpBreakdown->get($stat->owner_id) ?? collect())->map(function($b) {
                            return [
                                'brand' => $b->brand,
                                'name' => $b->name,
                                'condition' => $b->condition,
                                'storage' => $b->storage,
                                'distributor' => $b->distributor,
                                'qty' => (int)$b->qty,
                                'category' => $b->category,
                                'is_hp' => true
                            ];
                        })->concat(($nhpBreakdown->get($stat->owner_id) ?? collect())->map(function($b) {
                            return [
                                'brand' => $b->brand,
                                'name' => $b->name,
                                'condition' => 'new',
                                'storage' => null,
                                'distributor' => $b->distributor,
                                'qty' => (int)$b->qty,
                                'category' => $b->category,
                                'is_hp' => false
                            ];
                        }));

                        return [
                            'owner_id' => $stat->owner_id,
                            'cs_name' => $stat->cs_name ?? 'Unknown',
                            'photo' => $stat->photo ?? $stat->photo_inv,
                            'grand_total' => (float) $stat->grand_total,
                            'total_activity_rp' => (float) $stat->total_activity_rp,
                            'total_tu' => (int) $stat->total_tu,
                            'total_tt' => (int) $stat->total_tt,
                            'total_dw' => (int) $stat->total_dw,
                            'total_ab' => (int) $stat->total_ab,
                            'total_refund' => (int) $stat->total_refund,
                            'total_retur' => (int) $stat->total_retur,
                            'iphone_units' => $iphone,
                            'android_units' => $android,
                            'non_hp_units' => $nonHp,
                            'total_sales' => $iphone + $android + $nonHp,
                            'breakdown' => $breakdown->values()
                        ];
                    });
                },

                // 4. Daily History
                function () use ($startDate, $endDate, $successCategories, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedLocationType, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $isAnalist) {
                    $startTS = $startDate . ' 05:00:00';
                    $endTS = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59';
                    
                    $baseQuery = DB::table('stock_outs')->leftJoin('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $successCategories)
                        ->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
                            $q->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                              ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
                        })
                        ->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedLocationType, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $isAnalist) {
                        if ($requestedBranchId) {
                            $q->where('stock_outs.branch_id', $requestedBranchId)
                              ->orWhere(fn($sq) => $sq->whereNull('stock_outs.branch_id')->whereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.branch_id', $requestedBranchId)));
                        } elseif ($requestedOnlineShopId) {
                            $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)
                              ->orWhere(fn($sq) => $sq->whereNull('stock_outs.online_shop_id')->whereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.online_shop_id', $requestedOnlineShopId)));
                        } elseif ($requestedWarehouseId) {
                            $q->where('stock_outs.warehouse_id', $requestedWarehouseId)
                              ->orWhere(fn($sq) => $sq->whereNull('stock_outs.warehouse_id')->whereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.warehouse_id', $requestedWarehouseId)));
                        } elseif ($requestedDistributorId) {
                            $q->whereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->where('users.distributor_id', $requestedDistributorId));
                        } else {
                            $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedLocationType, $isAnalist) {
                                // If location_type is provided, restrict to that type even if ID is null
                                if ($requestedLocationType === 'branch') {
                                    $sub->whereNull('stock_outs.online_shop_id')
                                        ->whereNotExists(function ($sq) {
                                        $sq->select(DB::raw(1))
                                            ->from('users')
                                            ->whereRaw('users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id')
                                            ->whereNotNull('users.online_shop_id');
                                    });
                                } elseif ($requestedLocationType === 'online') {
                                    $sub->where(function ($sq) {
                                        $sq->whereNotNull('stock_outs.online_shop_id')
                                            ->orWhereExists(function ($ssq) {
                                                $ssq->select(DB::raw(1))
                                                    ->from('users')
                                                    ->whereRaw('users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id')
                                                    ->whereNotNull('users.online_shop_id');
                                            });
                                    });
                                }

                                if (!empty($branchIds)) {
                                    $sub->orWhereIn('stock_outs.branch_id', $branchIds)
                                        ->orWhere(fn($sq) => $sq->whereNull('stock_outs.branch_id')->whereExists(fn($ssq) => $ssq->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->whereIn('users.branch_id', $branchIds)));
                                }
                                if (!empty($onlineShopIds)) {
                                    $sub->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)
                                        ->orWhere(fn($sq) => $sq->whereNull('stock_outs.online_shop_id')->whereExists(fn($ssq) => $ssq->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->whereIn('users.online_shop_id', $onlineShopIds)));
                                }
                                if (!empty($warehouseIds)) {
                                    $sub->orWhereIn('stock_outs.warehouse_id', $warehouseIds)
                                        ->orWhere(fn($sq) => $sq->whereNull('stock_outs.warehouse_id')->whereExists(fn($ssq) => $ssq->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->whereIn('users.warehouse_id', $warehouseIds)));
                                }
                                if (!empty($distributorIds)) {
                                    $sub->orWhereExists(fn($ssq) => $ssq->select(DB::raw(1))->from('users')->whereRaw("users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id")->whereIn('users.distributor_id', $distributorIds));
                                }
                            });
                        }
                    });

                    $hpStats = (clone $baseQuery)->leftJoin('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
                        ->leftJoin('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                        ->leftJoin('products', 'product_details.product_id', '=', 'products.id')
                        ->whereNotIn('stock_outs.category', ['refund', 'angkat_barang', 'tukar_unit', 'downgrade'])
                        ->select('reporting_date', 
                            DB::raw("sum(case when (UPPER(products.brand) LIKE '%APPLE%' OR UPPER(products.brand) LIKE '%IPHONE%' OR UPPER(products.name) LIKE '%IPHONE%') then 1 else 0 end) as iphone_units"), 
                            DB::raw("sum(case when UPPER(products.brand) NOT LIKE '%APPLE%' AND UPPER(products.brand) NOT LIKE '%IPHONE%' and products.brand is not null then 1 else 0 end) as android_units"))
                        ->groupBy('reporting_date')->get()->keyBy('reporting_date');

                    $nhpStats = (clone $baseQuery)->leftJoin('stock_out_non_hp_items', 'stock_outs.id', '=', 'stock_out_non_hp_items.stock_out_id')
                        ->whereNotIn('stock_outs.category', ['refund', 'angkat_barang', 'tukar_unit', 'downgrade'])
                        ->select('reporting_date', DB::raw("sum(stock_out_non_hp_items.quantity) as non_hp_units"))
                        ->groupBy('reporting_date')->get()->keyBy('reporting_date');

                    $mainStats = (clone $baseQuery)->select('reporting_date', 
                        DB::raw('sum(CASE WHEN category = \'tukar_tambah\' THEN ABS(selling_price) WHEN category IN (\'downgrade\', \'tukar_unit\', \'refund\', \'angkat_barang\') THEN 0 ELSE selling_price END) as total_omset'))
                        ->groupBy('reporting_date')->orderByDesc('reporting_date')->get();

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
                function () use ($successCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $isAnalist) {
                    $hp = DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->join('products', 'product_details.product_id', '=', 'products.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $successCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        if ($requestedBranchId) { $q->where('stock_outs.branch_id', $requestedBranchId)->orWhere('users.branch_id', $requestedBranchId); }
                        elseif ($requestedOnlineShopId) { $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)->orWhere('users.online_shop_id', $requestedOnlineShopId); }
                        elseif ($requestedWarehouseId) { $q->where('stock_outs.warehouse_id', $requestedWarehouseId)->orWhere('users.warehouse_id', $requestedWarehouseId); }
                        elseif ($requestedDistributorId) { $q->where('users.distributor_id', $requestedDistributorId); }
                        else {
                            if (!empty($branchIds)) $q->orWhereIn('stock_outs.branch_id', $branchIds)->orWhereIn('users.branch_id', $branchIds);
                            if (!empty($onlineShopIds)) $q->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)->orWhereIn('users.online_shop_id', $onlineShopIds);
                            if (!empty($warehouseIds)) $q->orWhereIn('stock_outs.warehouse_id', $warehouseIds)->orWhereIn('users.warehouse_id', $warehouseIds);
                            if (!empty($distributorIds)) $q->orWhereIn('users.distributor_id', $distributorIds);
                        }
                    })->select('products.name', 'products.brand', DB::raw('count(*) as qty'))->groupBy('products.name', 'products.brand');

                    $nhp = DB::table('stock_out_non_hp_items')->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $successCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        if ($requestedBranchId) { $q->where('stock_outs.branch_id', $requestedBranchId)->orWhere('users.branch_id', $requestedBranchId); }
                        elseif ($requestedOnlineShopId) { $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)->orWhere('users.online_shop_id', $requestedOnlineShopId); }
                        elseif ($requestedWarehouseId) { $q->where('stock_outs.warehouse_id', $requestedWarehouseId)->orWhere('users.warehouse_id', $requestedWarehouseId); }
                        elseif ($requestedDistributorId) { $q->where('users.distributor_id', $requestedDistributorId); }
                        else {
                            if (!empty($branchIds)) $q->orWhereIn('stock_outs.branch_id', $branchIds)->orWhereIn('users.branch_id', $branchIds);
                            if (!empty($onlineShopIds)) $q->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)->orWhereIn('users.online_shop_id', $onlineShopIds);
                            if (!empty($warehouseIds)) $q->orWhereIn('stock_outs.warehouse_id', $warehouseIds)->orWhereIn('users.warehouse_id', $warehouseIds);
                            if (!empty($distributorIds)) $q->orWhereIn('users.distributor_id', $distributorIds);
                        }
                    })->select('products.name', 'products.brand', DB::raw('sum(quantity) as qty'))->groupBy('products.name', 'products.brand');

                    return $hp->get()->concat($nhp->get())->groupBy(fn($i) => $i->brand . '|' . $i->name)->map(function($group) {
                        return (object)[ 'brand' => $group[0]->brand, 'name' => $group[0]->name, 'qty' => $group->sum('qty') ];
                    })->values();
                },

                // 6. Condition Stats
                function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $isAnalist) {
                    $hp = DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        if ($requestedBranchId) { $q->where('stock_outs.branch_id', $requestedBranchId)->orWhere('users.branch_id', $requestedBranchId); }
                        elseif ($requestedOnlineShopId) { $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)->orWhere('users.online_shop_id', $requestedOnlineShopId); }
                        elseif ($requestedWarehouseId) { $q->where('stock_outs.warehouse_id', $requestedWarehouseId)->orWhere('users.warehouse_id', $requestedWarehouseId); }
                        elseif ($requestedDistributorId) { $q->where('users.distributor_id', $requestedDistributorId); }
                        else {
                            if (!empty($branchIds)) $q->orWhereIn('stock_outs.branch_id', $branchIds)->orWhereIn('users.branch_id', $branchIds);
                            if (!empty($onlineShopIds)) $q->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)->orWhereIn('users.online_shop_id', $onlineShopIds);
                            if (!empty($warehouseIds)) $q->orWhereIn('stock_outs.warehouse_id', $warehouseIds)->orWhereIn('users.warehouse_id', $warehouseIds);
                            if (!empty($distributorIds)) $q->orWhereIn('users.distributor_id', $distributorIds);
                        }
                    })->select('product_details.condition', DB::raw('count(*) as qty'))->groupBy('product_details.condition');

                    $nhp = DB::table('stock_out_non_hp_items')->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        if ($requestedBranchId) { $q->where('stock_outs.branch_id', $requestedBranchId)->orWhere('users.branch_id', $requestedBranchId); }
                        elseif ($requestedOnlineShopId) { $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)->orWhere('users.online_shop_id', $requestedOnlineShopId); }
                        elseif ($requestedWarehouseId) { $q->where('stock_outs.warehouse_id', $requestedWarehouseId)->orWhere('users.warehouse_id', $requestedWarehouseId); }
                        elseif ($requestedDistributorId) { $q->where('users.distributor_id', $requestedDistributorId); }
                        else {
                            if (!empty($branchIds)) $q->orWhereIn('stock_outs.branch_id', $branchIds)->orWhereIn('users.branch_id', $branchIds);
                            if (!empty($onlineShopIds)) $q->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)->orWhereIn('users.online_shop_id', $onlineShopIds);
                            if (!empty($warehouseIds)) $q->orWhereIn('stock_outs.warehouse_id', $warehouseIds)->orWhereIn('users.warehouse_id', $warehouseIds);
                            if (!empty($distributorIds)) $q->orWhereIn('users.distributor_id', $distributorIds);
                        }
                    })->select(DB::raw("'new' as condition"), DB::raw('sum(quantity) as qty'))->groupBy('condition');

                    return $hp->get()->concat($nhp->get())->groupBy('condition')->map(function($group) {
                        return (object)[ 'condition' => $group[0]->condition, 'qty' => $group->sum('qty') ];
                    })->values();
                },

                // 7. Distributor Stats
                function () use ($successCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $isAnalist) {
                    $hp = DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->leftJoin('distributors', 'product_details.distributor_id', '=', 'distributors.id')->join('products', 'product_details.product_id', '=', 'products.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $successCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        if ($requestedBranchId) { $q->where('stock_outs.branch_id', $requestedBranchId)->orWhere('users.branch_id', $requestedBranchId); }
                        elseif ($requestedOnlineShopId) { $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)->orWhere('users.online_shop_id', $requestedOnlineShopId); }
                        elseif ($requestedWarehouseId) { $q->where('stock_outs.warehouse_id', $requestedWarehouseId)->orWhere('users.warehouse_id', $requestedWarehouseId); }
                        elseif ($requestedDistributorId) { $q->where('users.distributor_id', $requestedDistributorId); }
                        else {
                            if (!empty($branchIds)) $q->orWhereIn('stock_outs.branch_id', $branchIds)->orWhereIn('users.branch_id', $branchIds);
                            if (!empty($onlineShopIds)) $q->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)->orWhereIn('users.online_shop_id', $onlineShopIds);
                            if (!empty($warehouseIds)) $q->orWhereIn('stock_outs.warehouse_id', $warehouseIds)->orWhereIn('users.warehouse_id', $warehouseIds);
                            if (!empty($distributorIds)) $q->orWhereIn('users.distributor_id', $distributorIds);
                        }
                    })->select(DB::raw("COALESCE(distributors.name, 'Tanpa Distributor') as distributor"), DB::raw('count(*) as qty'))->groupBy('distributor');

                    $nhp = DB::table('stock_out_non_hp_items')->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')->leftJoin('distributors', 'stock_out_non_hp_items.distributor_id', '=', 'distributors.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $successCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        if ($requestedBranchId) { $q->where('stock_outs.branch_id', $requestedBranchId)->orWhere('users.branch_id', $requestedBranchId); }
                        elseif ($requestedOnlineShopId) { $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)->orWhere('users.online_shop_id', $requestedOnlineShopId); }
                        elseif ($requestedWarehouseId) { $q->where('stock_outs.warehouse_id', $requestedWarehouseId)->orWhere('users.warehouse_id', $requestedWarehouseId); }
                        elseif ($requestedDistributorId) { $q->where('users.distributor_id', $requestedDistributorId); }
                        else {
                            if (!empty($branchIds)) $q->orWhereIn('stock_outs.branch_id', $branchIds)->orWhereIn('users.branch_id', $branchIds);
                            if (!empty($onlineShopIds)) $q->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)->orWhereIn('users.online_shop_id', $onlineShopIds);
                            if (!empty($warehouseIds)) $q->orWhereIn('stock_outs.warehouse_id', $warehouseIds)->orWhereIn('users.warehouse_id', $warehouseIds);
                            if (!empty($distributorIds)) $q->orWhereIn('users.distributor_id', $distributorIds);
                        }
                    })->select(DB::raw("COALESCE(distributors.name, 'Tanpa Distributor') as distributor"), DB::raw('sum(quantity) as qty'))->groupBy('distributor');

                    return $hp->get()->concat($nhp->get())->groupBy('distributor')->map(function($group) {
                        return (object)[ 'distributor' => $group[0]->distributor, 'qty' => $group->sum('qty') ];
                    })->values();
                },

                // 8. Products Filter
                function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    return DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->join('products', 'product_details.product_id', '=', 'products.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        if ($requestedBranchId) {
                            $q->where('stock_outs.branch_id', $requestedBranchId)
                                ->orWhere('users.branch_id', $requestedBranchId);
                        } elseif ($requestedOnlineShopId) {
                            $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)
                                ->orWhere('users.online_shop_id', $requestedOnlineShopId);
                        } elseif ($requestedWarehouseId) {
                            $q->where('stock_outs.warehouse_id', $requestedWarehouseId)
                                ->orWhere('users.warehouse_id', $requestedWarehouseId);
                        } elseif ($requestedDistributorId) {
                            $q->where('users.distributor_id', $requestedDistributorId);
                        } else {
                            if (!empty($branchIds))
                                $q->orWhereIn('stock_outs.branch_id', $branchIds)
                                    ->orWhereIn('users.branch_id', $branchIds);
                            if (!empty($onlineShopIds))
                                $q->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)
                                    ->orWhereIn('users.online_shop_id', $onlineShopIds);
                            if (!empty($warehouseIds))
                                $q->orWhereIn('stock_outs.warehouse_id', $warehouseIds)
                                    ->orWhereIn('users.warehouse_id', $warehouseIds);
                            if (!empty($distributorIds))
                                $q->orWhereIn('users.distributor_id', $distributorIds);
                        }
                    })->select('products.id', 'products.name', 'products.brand')->distinct()->orderBy('products.name')->get();
                },

                // 9. Distributor Filter
                function () use ($salesCategories, $startDate, $endDate, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    return DB::table('stock_out_items')->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')->join('distributors', 'product_details.distributor_id', '=', 'distributors.id')->join('users', 'stock_outs.user_id', '=', 'users.id')->whereIn('stock_outs.category', $salesCategories)->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                        if ($requestedBranchId) {
                            $q->where('stock_outs.branch_id', $requestedBranchId)
                                ->orWhere('users.branch_id', $requestedBranchId);
                        } elseif ($requestedOnlineShopId) {
                            $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)
                                ->orWhere('users.online_shop_id', $requestedOnlineShopId);
                        } elseif ($requestedWarehouseId) {
                            $q->where('stock_outs.warehouse_id', $requestedWarehouseId)
                                ->orWhere('users.warehouse_id', $requestedWarehouseId);
                        } elseif ($requestedDistributorId) {
                            $q->where('users.distributor_id', $requestedDistributorId);
                        } else {
                            if (!empty($branchIds))
                                $q->orWhereIn('stock_outs.branch_id', $branchIds)
                                    ->orWhereIn('users.branch_id', $branchIds);
                            if (!empty($onlineShopIds))
                                $q->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)
                                    ->orWhereIn('users.online_shop_id', $onlineShopIds);
                            if (!empty($warehouseIds))
                                $q->orWhereIn('stock_outs.warehouse_id', $warehouseIds)
                                    ->orWhereIn('users.warehouse_id', $warehouseIds);
                            if (!empty($distributorIds))
                                $q->orWhereIn('users.distributor_id', $distributorIds);
                        }
                    })->select('distributors.id', 'distributors.name')->distinct()->orderBy('distributors.name')->get();
                },

                // 10. Unified Report Summary
                function () use ($salesCategories, $startDate, $endDate, $stockStartDate, $stockEndDate, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $requestedLocationType, $paymentMethods, $distributors, $isUnrestricted, $isAnalist, $isSuperAdmin, $currentRoles) {
                    try {
                        $applyLocalScope = function ($query) use ($startDate, $endDate, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $requestedLocationType, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $isUnrestricted, $isAnalist, $isSuperAdmin) {
                            $startTS = $startDate . ' 05:00:00';
                            $endTS = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59';
                            
                            $query->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
                                $q->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                                    ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
                            });

                            $query->where(function ($q) use ($requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $requestedLocationType, $branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $isUnrestricted, $isAnalist, $isSuperAdmin) {
                                $scoper = function ($qq, $col, $val) {
                                    $qq->where(function ($sq) use ($col, $val) {
                                        $sq->where("stock_outs.$col", $val)
                                            ->orWhere(function ($ssq) use ($col, $val) {
                                                $ssq->whereNull("stock_outs.$col")
                                                    ->whereExists(function ($sub) use ($col, $val) {
                                                        $sub->select(DB::raw(1))
                                                            ->from('users')
                                                            ->whereRaw('users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id')
                                                            ->where("users.$col", $val);
                                                    });
                                            });
                                    });
                                };

                                if ($requestedBranchId) {
                                    $scoper($q, 'branch_id', $requestedBranchId);
                                } elseif ($requestedOnlineShopId) {
                                    $scoper($q, 'online_shop_id', $requestedOnlineShopId);
                                } elseif ($requestedWarehouseId) {
                                    $scoper($q, 'warehouse_id', $requestedWarehouseId);
                                } elseif ($requestedDistributorId) {
                                    $q->whereExists(function ($sub) use ($requestedDistributorId) {
                                        $sub->select(DB::raw(1))
                                            ->from('users')
                                            ->whereRaw('users.id = stock_outs.user_id')
                                            ->where('users.distributor_id', $requestedDistributorId);
                                    });
                                } elseif ($isUnrestricted && !$isAnalist && !$isSuperAdmin) {
                                    // Superadmins/Owners (non-filtered) see everything of the requested type (if provided)
                                    if ($requestedLocationType === 'branch') {
                                        $q->whereNotNull('stock_outs.branch_id')
                                            ->orWhereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw('users.id = stock_outs.user_id')->whereNotNull('users.branch_id'));
                                    } elseif ($requestedLocationType === 'online') {
                                        $q->whereNotNull('stock_outs.online_shop_id')
                                            ->orWhereExists(fn($sub) => $sub->select(DB::raw(1))->from('users')->whereRaw('users.id = stock_outs.user_id')->whereNotNull('users.online_shop_id'));
                                    }
                                } else {
                                    // Restricted users (Staff, Leaders, etc.) OR Filtered Unrestricted (Analist, SuperAdmin) use their accessible IDs
                                    $q->where(function ($sub) use ($branchIds, $onlineShopIds) {
                                        if (!empty($branchIds)) {
                                            $sub->orWhereIn('stock_outs.branch_id', $branchIds)
                                                ->orWhere(fn($sq) => $sq->whereNull('stock_outs.branch_id')->whereExists(fn($s) => $s->select(DB::raw(1))->from('users')->whereRaw('users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id')->whereIn('users.branch_id', $branchIds)));
                                        }
                                        if (!empty($onlineShopIds)) {
                                            $sub->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)
                                                ->orWhere(fn($sq) => $sq->whereNull('stock_outs.online_shop_id')->whereExists(fn($s) => $s->select(DB::raw(1))->from('users')->whereRaw('users.id = stock_outs.user_id OR users.id = stock_outs.inventory_user_id')->whereIn('users.online_shop_id', $onlineShopIds)));
                                        }

                                        if (empty($branchIds) && empty($onlineShopIds)) {
                                            $sub->whereRaw('1=0');
                                        }
                                    });
                                }
                            });
                        };

                        $applyStockScope = function ($q) use ($requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $branchIds, $onlineShopIds, $isUnrestricted, $isAnalist, $isSuperAdmin) {
                            $q->where(function ($sub) use ($requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $branchIds, $onlineShopIds, $isUnrestricted, $isAnalist, $isSuperAdmin) {
                                if ($requestedBranchId) {
                                    $sub->where('placement_id', $requestedBranchId)->whereRaw('LOWER(placement_type) LIKE ?', ['%branch%']);
                                } elseif ($requestedOnlineShopId) {
                                    $sub->where('placement_id', $requestedOnlineShopId)->whereRaw('LOWER(placement_type) LIKE ?', ['%online_shop%']);
                                } elseif ($requestedWarehouseId) {
                                    $sub->where('placement_id', $requestedWarehouseId)->whereRaw('LOWER(placement_type) LIKE ?', ['%warehouse%']);
                                } elseif ($requestedDistributorId) {
                                    $sub->where('placement_id', $requestedDistributorId)->whereRaw('LOWER(placement_type) LIKE ?', ['%distributor%']);
                                } elseif ($isUnrestricted && !$isAnalist && !$isSuperAdmin) {
                                    return;
                                } else {
                                    if (!empty($branchIds))
                                        $sub->orWhere(fn($iq) => $iq->whereIn('placement_id', $branchIds)->whereRaw('LOWER(placement_type) LIKE ?', ['%branch%']));
                                    if (!empty($onlineShopIds))
                                        $sub->orWhere(fn($iq) => $iq->whereIn('placement_id', $onlineShopIds)->whereRaw('LOWER(placement_type) LIKE ?', ['%online_shop%']));
                                }
                            });
                        };

                        $applyInScope = function ($q) use ($startDate, $endDate, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $branchIds, $onlineShopIds, $isUnrestricted, $isAnalist, $isSuperAdmin) {
                            $startTS = $startDate . ' 05:00:00';
                            $endTS = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59';
                            $q->where(function ($qq) use ($startDate, $endDate, $startTS, $endTS) {
                                $qq->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                                    ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
                            });
                            $q->where(function ($sub) use ($requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId, $branchIds, $onlineShopIds, $isUnrestricted, $isAnalist, $isSuperAdmin) {
                                if ($requestedBranchId)
                                    $sub->where('stock_outs.branch_id', $requestedBranchId);
                                elseif ($requestedOnlineShopId)
                                    $sub->where('stock_outs.online_shop_id', $requestedOnlineShopId);
                                elseif ($requestedWarehouseId)
                                    $sub->where('stock_outs.warehouse_id', $requestedWarehouseId);
                                elseif ($requestedDistributorId) {
                                    $sub->whereExists(function ($ss) use ($requestedDistributorId) {
                                        $ss->select(DB::raw(1))->from('users')->whereRaw('users.id = stock_outs.user_id')->where('users.distributor_id', $requestedDistributorId);
                                    });
                                } elseif ($isUnrestricted && !$isAnalist && !$isSuperAdmin) {
                                    return;
                                } else {
                                    if (!empty($branchIds)) {
                                        $sub->orWhereIn('stock_outs.branch_id', $branchIds)
                                            ->orWhereExists(fn($ss) => $ss->select(DB::raw(1))->from('users')->whereRaw('users.id = stock_outs.user_id')->whereIn('users.branch_id', $branchIds));
                                    }
                                    if (!empty($onlineShopIds)) {
                                        $sub->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)
                                            ->orWhereExists(fn($ss) => $ss->select(DB::raw(1))->from('users')->whereRaw('users.id = stock_outs.user_id')->whereIn('users.online_shop_id', $onlineShopIds));
                                    }
                                    if (!empty($distributorIds)) {
                                        $sub->orWhereExists(function ($ss) use ($distributorIds) {
                                            $ss->select(DB::raw(1))->from('users')->whereRaw('users.id = stock_outs.user_id')->whereIn('users.distributor_id', $distributorIds);
                                        });
                                    }
                                }
                            });
                        };


                        // 1. Total Omset & Payments (Memory-efficient aggregation)
                        $pQuery = DB::table('stock_outs');
                        $applyLocalScope($pQuery);
                        
                        $pSums = [];
                        
                        // Categories that count towards Omset (Revenue)
                        $omsetCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'tukar_unit', 'tukar_tambah', 'downgrade'];

                        // Direct aggregation for Total Omset to match CheckSales logic
                        $paymentTotal = (clone $pQuery)->whereIn('stock_outs.category', $omsetCategories)
                            ->select(DB::raw("SUM(CASE WHEN category = 'tukar_tambah' THEN ABS(selling_price) WHEN category IN ('refund', 'angkat_barang', 'downgrade', 'tukar_unit') THEN 0 ELSE selling_price END) as total"))
                            ->value('total') ?? 0;
                        
                        // Now calculate breakdown for UI (payment method rows)
                        // Separate query for non-split payments to handle ABS() logic
                        
                        // Separate query for non-split payments to handle ABS() logic
                        $paymentStats = $pQuery->whereIn('stock_outs.category', $omsetCategories)
                            ->whereNull('split_payments')
                            ->select(
                                'payment_method_id',
                                'category',
                                'selling_price'
                            )
                            ->get();

                        foreach ($paymentStats as $ps) {
                            $mName = $ps->payment_method_id ? ($paymentMethods->get($ps->payment_method_id)?->name ?? 'Lainnya') : 'CASH TOKO';
                            $cat = $ps->category;
                            
                            // Total Omset formula: Base Sales + Selisih Tukar Tambah
                            // Downgrade and Tukar Unit are EXCLUDED from Total Omset
                            if ($cat === 'tukar_tambah') {
                                $amount = abs((float)$ps->selling_price);
                            } elseif (in_array($cat, ['downgrade', 'tukar_unit', 'refund', 'angkat_barang'])) {
                                $amount = 0;
                            } else {
                                $amount = (float)$ps->selling_price;
                            }
                                
                            $pSums[$mName] = ($pSums[$mName] ?? 0) + $amount;
                        }

                        // Handle splits separately across the small set of split transactions (usually few)
                        $splitQuery = DB::table('stock_outs');
                        $applyLocalScope($splitQuery);
                        $splits = $splitQuery->whereIn('stock_outs.category', $omsetCategories)
                            ->whereNotNull('split_payments')
                            ->select('split_payments', 'category')->get();

                        $omsetBersih = $paymentTotal; // Initial base (will be adjusted below)

                        foreach ($splits as $s) {
                            $sData = is_string($s->split_payments) ? json_decode($s->split_payments, true) : $s->split_payments;
                            if (is_array($sData)) {
                                foreach ($sData as $sp) {
                                    $amt = (float) ($sp['amount'] ?? 0);
                                    $cat = $s->category;
                                    
                                    // Total Omset formula for splits
                                    if ($cat === 'tukar_tambah') {
                                        $amt = abs($amt);
                                    } elseif (in_array($cat, ['downgrade', 'tukar_unit', 'refund', 'angkat_barang'])) {
                                        $amt = 0;
                                    }
                                    
                                    $pm = $paymentMethods->get($sp['payment_method_id'] ?? ($sp['method_id'] ?? null));
                                    $mName = $pm?->name ?? 'Lainnya';
                                    $pSums[$mName] = ($pSums[$mName] ?? 0) + $amt;
                                }
                            }
                        }

                        // Calculate Net Revenue (Omset Bersih)
                        // Omset Bersih = Base Sales - (Refund + Angkat Barang + Downgrade)
                        // Note: $paymentTotal already includes abs(TT) and abs(DG) in our modified logic above.
                        // We need the raw Base Sales (standard sales only)
                        $rawBaseQuery = DB::table('stock_outs');
                        $applyLocalScope($rawBaseQuery);
                        $baseSalesOnly = (float) $rawBaseQuery->whereIn('stock_outs.category', ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store'])->sum('selling_price');

                        $deductionQuery = DB::table('stock_outs');
                        $applyLocalScope($deductionQuery);
                        $deductions = (float) $deductionQuery->whereIn('stock_outs.category', ['refund', 'angkat_barang', 'downgrade'])->sum(DB::raw('ABS(selling_price)'));
                        
                        $omsetBersih = $baseSalesOnly - $deductions;

                        $map = ['apple_lux' => 0, 'hp' => 0, 'iphone' => 0, 'android' => 0, 'apply' => 0, 'arcis' => 0, 'debs' => 0, 'dokter_pstore' => 0, 'jaringan' => 0, 'sim_card' => 0, 'laptop' => 0, 'tv' => 0, 'accessories' => 0, 'others' => 0];
                        $mapRp = ['apple_lux' => 0, 'hp' => 0, 'accessories' => 0, 'apply' => 0, 'arcis' => 0, 'debs' => 0, 'dokter_pstore' => 0, 'jaringan' => 0, 'sim_card' => 0, 'laptop' => 0, 'tv' => 0, 'others' => 0];
                        $soldDetails = [];
                        $stockReport = ['apple_lux' => 0, 'hp' => 0, 'accessories' => 0, 'apply' => 0, 'arcis' => 0, 'debs' => 0, 'dokter_pstore' => 0, 'jaringan' => 0, 'sim_card' => 0, 'laptop' => 0, 'tv' => 0, 'others' => 0];
                        $rawStockDetails = ['hp' => [], 'apple_lux' => [], 'accessories' => [], 'apply' => [], 'arcis' => [], 'debs' => [], 'dokter_pstore' => [], 'laptop' => [], 'tv' => [], 'jaringan' => [], 'sim_card' => [], 'others' => []];

                        $getCategoryByItem = function ($did, $isHp = false) {
                            $did = (int) $did;
                            
                            $idMap = [
                                6  => 'apple_lux',      // Apple Luxury
                                7  => 'hp',              // Android New
                                8  => 'hp',              // Apple Merakyat
                                9  => 'hp',              // Android Second
                                10 => 'accessories',     // Pstore Accesories
                                11 => 'apply',           // Apply
                                13 => 'debs',            // Debs
                                14 => 'arcis',           // Arcis
                                15 => 'dokter_pstore',   // Dokter Pstore
                                16 => 'laptop',          // Laptopsss
                                17 => 'tv',              // tvstOre
                                18 => 'sim_card',        // Sim Card
                                19 => 'jaringan',        // network
                                20 => 'jasa',            // Jasa
                            ];

                            if (isset($idMap[$did])) {
                                return $idMap[$did];
                            }

                            return $isHp ? 'hp' : 'others';
                        };

                        $addUnitToMap = function (&$map, $brand, $itemCategory, $trxCategory = null) {
                            $brand = strtolower($brand ?? '');
                            $trxCategory = strtolower($trxCategory ?? '');

                            // Only count towards HP totals if it's a standard sale, not a return/retrieval or special activity
                            $isStandardSale = !in_array($trxCategory, ['refund', 'angkat_barang', 'cancel_penjualan', 'tukar_unit', 'downgrade']);

                            if ($isStandardSale) {
                                if ($itemCategory === 'apple_lux') {
                                    $map['apple_lux']++;
                                } elseif ($brand === 'apple' || str_contains($brand, 'iphone')) {
                                    $map['iphone']++;
                                } else {
                                    // Count all other HP items as Android for summary purposes
                                    $map['android']++;
                                }
                            }

                            if ($isStandardSale) {
                                if (!isset($map[$itemCategory])) $map[$itemCategory] = 0;
                                $map[$itemCategory]++;
                            }
                        };

                        // 1. HP transactions from stock_out_items
                        $hpItemsQuery = DB::table('stock_out_items')
                            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
                            ->leftJoin('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                            ->leftJoin('products', 'product_details.product_id', '=', 'products.id')
                            ->whereIn('stock_outs.category', ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'cancel_penjualan', 'refund', 'angkat_barang', 'sale', 'pos', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store']);
                        $applyLocalScope($hpItemsQuery);

                        $activityDetails = ['refund' => [], 'retur' => [], 'angkat_barang' => [], 'tukar_unit' => [], 'tukar_tambah' => [], 'downgrade' => []];

                        foreach ($hpItemsQuery->select('products.name', 'products.brand', 'product_details.distributor_id', 'product_details.storage', 'product_details.cost_price', 'stock_out_items.selling_price as item_price', 'stock_out_items.item_discount', 'stock_outs.category', 'product_details.imei', 'stock_outs.selling_price as total_diff')->get() as $hp) {
                            $catLower = strtolower($hp->category ?? '');
                            if (in_array($catLower, ['refund', 'retur', 'angkat_barang', 'tukar_unit', 'tukar_tambah', 'downgrade'])) {
                                $activityDetails[$catLower][] = [
                                    'name' => $hp->name,
                                    'imei' => $hp->imei,
                                    'storage' => $hp->storage,
                                    'price' => (float) ($hp->item_price > 0 ? $hp->item_price : ($hp->cost_price ?? 0))
                                ];
                            }

                            // Standard sales criteria: Exclude refunds, unit exchanges, and downgrades from standard unit/revenue maps
                            $isStandardSale = !in_array($catLower, ['refund', 'angkat_barang', 'cancel_penjualan', 'tukar_unit', 'downgrade']);
                            
                            $itemCat = $getCategoryByItem($hp->distributor_id, true);
                            $addUnitToMap($map, $hp->brand, $itemCat, $hp->category);

                            if ($isStandardSale || $catLower === 'tukar_tambah') {
                                // For Tukar Tambah, use absolute price difference from the record itself, not the item price
                                $price = ($catLower === 'tukar_tambah') 
                                    ? abs((float)$hp->total_diff) 
                                    : (float) $hp->item_price - (float) ($hp->item_discount ?? 0);
                                    
                                if (!isset($mapRp[$itemCat])) $mapRp[$itemCat] = 0;
                                $mapRp[$itemCat] += $price;
                                $soldDetails[$itemCat][$hp->name ?? 'Unknown item'] = ($soldDetails[$itemCat][$hp->name ?? 'Unknown item'] ?? 0) + 1;
                            }
                        }

                        // 2. Non-IMEI transactions
                        $nhpItems = \App\Models\StockOutNonHpItem::with(['product', 'stockOut'])
                            ->whereHas('stockOut', function ($q) use ($startDate, $endDate, $salesCategories, $applyLocalScope) {
                                $q->whereIn('category', $salesCategories)
                                    ->whereBetween('reporting_date', [$startDate, $endDate]);
                                $applyLocalScope($q);
                            })
                            ->get();

                        foreach ($nhpItems as $item) {
                            $trx = $item->stockOut;
                            if (!$trx) continue;

                            $catLower = strtolower($trx->category ?? '');
                            
                            if (in_array($catLower, ['refund', 'retur', 'angkat_barang', 'tukar_unit', 'tukar_tambah', 'downgrade'])) {
                                $activityDetails[$catLower][] = [
                                    'name' => ($item->product?->name ?? 'Unknown') . " (Qty: {$item->quantity})",
                                    'imei' => null,
                                    'price' => (float) $item->selling_price
                                ];
                            }

                            $did = $item->distributor_id;
                            
                            // Fallback 1: Product's default distributor
                            if (!$did && $item->product) {
                                $did = $item->product->distributor_id;
                            }

                            // Fallback for transactions (inventory logs)
                            if (!$did) {
                                $lastLog = DB::table('inventory_logs')
                                    ->where('product_id', $item->product_id)
                                    ->where('type', 'in')
                                    ->where(function ($q) use ($trx) {
                                        if ($trx->branch_id)
                                            $q->where('branch_id', $trx->branch_id);
                                        elseif ($trx->warehouse_id)
                                            $q->where('warehouse_id', $trx->warehouse_id);
                                        elseif ($trx->online_shop_id)
                                            $q->where('online_shop_id', $trx->online_shop_id);
                                    })
                                    ->latest()
                                    ->first();
                                $did = $lastLog->distributor_id ?? null;
                            }

                            $qty = (int) $item->quantity;
                            $cat = $getCategoryByItem($did);

                            // Breakdown for non-IMEI HP if any
                            if ($cat === 'hp' || $cat === 'apple_lux') {
                                $brand = strtolower($item->product?->brand ?? '');
                                $isStandardSale = !in_array($catLower, ['refund', 'angkat_barang', 'cancel_penjualan', 'tukar_unit', 'downgrade']);

                                if ($isStandardSale || $catLower === 'tukar_tambah') {
                                    if ($brand === 'apple' || str_contains($brand, 'iphone'))
                                        $map['iphone'] += $qty;
                                    elseif ($cat === 'apple_lux')
                                        $map['apple_lux'] += $qty;
                                    else
                                        $map['android'] += $qty;
                                }
                            }

                            // Only count towards totals if it's a standard sale or TT
                            $isStandardSale = !in_array($catLower, ['refund', 'angkat_barang', 'cancel_penjualan', 'tukar_unit', 'downgrade']);
                            
                            if ($isStandardSale || $catLower === 'tukar_tambah') {
                                if (!isset($map[$cat])) $map[$cat] = 0;
                                if (!isset($mapRp[$cat])) $mapRp[$cat] = 0;
                                
                                // For Tukar Tambah, use absolute price difference
                                $pricePerItem = ($catLower === 'tukar_tambah')
                                    ? abs((float)$item->selling_price)
                                    : (float) ($item->selling_price ?? 0) - (float) ($item->item_discount ?? 0);
                                
                                $map[$cat] += $qty;
                                $mapRp[$cat] += $pricePerItem * $qty;
                                
                                $soldDetails[$cat][$item->product?->name ?? 'Unknown non-hp'] = ($soldDetails[$cat][$item->product?->name ?? 'Unknown non-hp'] ?? 0) + $qty;
                            }
                        }





                        // IMEI Stock - current available stock
                        $alStock = DB::table('product_details')
                            ->join('products', 'product_details.product_id', '=', 'products.id')
                            ->leftJoin('distributors', 'product_details.distributor_id', '=', 'distributors.id')
                            ->where('product_details.status', 'available')
                            ->when($requestedDistributorId, fn($q) => $q->where('product_details.distributor_id', $requestedDistributorId));

                        $applyStockScope($alStock);
                        foreach ($alStock->select('products.name', 'product_details.distributor_id', DB::raw('count(*) as qty'))->groupBy('products.name', 'product_details.distributor_id')->get() as $s) {
                            $cat = $getCategoryByItem($s->distributor_id);
                            $cleanName = trim($s->name);
                            if (!isset($rawStockDetails[$cat])) $rawStockDetails[$cat] = [];
                            if (!isset($stockReport[$cat])) $stockReport[$cat] = 0;
                            $rawStockDetails[$cat][$cleanName] = ($rawStockDetails[$cat][$cleanName] ?? 0) + $s->qty;
                            $stockReport[$cat] += $s->qty;
                        }

                        // Use a slightly more optimized approach for NHP stock to find distributors
                        $oStock = DB::table('inventories')
                            ->join('products', 'inventories.product_id', '=', 'products.id')
                            ->where('inventories.quantity', '>', 0)
                            ->when($requestedDistributorId, fn($q) => $q->where('inventories.distributor_id', $requestedDistributorId));
                        $applyStockScope($oStock);

                        $oStockItems = $oStock->select('products.name', 'inventories.quantity', 'inventories.distributor_id', 'inventories.product_id', 'inventories.placement_type', 'inventories.placement_id')->get();

                        foreach ($oStockItems as $s) {
                            $did = $s->distributor_id;

                            // Fallback to logs if distributor_id is missing in inventories table
                            if (!$did) {
                                $lastLog = DB::table('inventory_logs')
                                    ->where('product_id', $s->product_id)
                                    ->where('type', 'in')
                                    ->where(function ($q) use ($s) {
                                        $pt = strtolower($s->placement_type ?? '');
                                        if (str_contains($pt, 'branch'))
                                            $q->where('branch_id', $s->placement_id);
                                        elseif (str_contains($pt, 'warehouse'))
                                            $q->where('warehouse_id', $s->placement_id);
                                        elseif (str_contains($pt, 'online_shop'))
                                            $q->where('online_shop_id', $s->placement_id);
                                    })
                                    ->latest()
                                    ->first();
                                $did = $lastLog->distributor_id ?? null;
                            }

                            $cat = $getCategoryByItem($did);
                            $cleanName = trim($s->name);
                            $qty = (int) $s->quantity;
                            if (!isset($rawStockDetails[$cat])) $rawStockDetails[$cat] = [];
                            if (!isset($stockReport[$cat])) $stockReport[$cat] = 0;
                            $rawStockDetails[$cat][$cleanName] = ($rawStockDetails[$cat][$cleanName] ?? 0) + $qty;
                            $stockReport[$cat] += $qty;
                        }

                        // 4. Final Totals
                        $totalHpItems = $hpItemsQuery->count();
                        $totalNhpItems = $nhpItems->sum('quantity');

                        return [
                            'payments' => $pSums,
                            'payment_total' => $paymentTotal,
                            'omset_bersih' => $omsetBersih,
                            'dist_map' => $map,
                            'dist_map_rp' => $mapRp,
                            'stock_report' => $stockReport,
                            'stock_details' => $rawStockDetails,
                            'sold_details' => $soldDetails,
                            'activities' => [
                                'tukar_unit' => count($activityDetails['tukar_unit'] ?? []),
                                'tukar_tambah' => count($activityDetails['tukar_tambah'] ?? []),
                                'downgrade' => count($activityDetails['downgrade'] ?? []),
                                'refund' => count($activityDetails['refund'] ?? []),
                                'retur' => count($activityDetails['retur'] ?? []),
                                'angkat_barang' => count($activityDetails['angkat_barang'] ?? []),
                                'details' => $activityDetails
                            ],
                            'debug' => [
                                'requested_branch_id' => $requestedBranchId,
                                'requested_online_shop_id' => $requestedOnlineShopId,
                                'resolved_target_id' => $requestedBranchId ? (string) $requestedBranchId : ($requestedOnlineShopId ? (string) $requestedOnlineShopId : 'ALL'),
                                'total_payments_found' => $paymentTotal,
                                'omset_bersih' => $omsetBersih,
                                'total_hp_items' => $totalHpItems,
                                'total_nhp_items' => $totalNhpItems,
                                'date_range' => [$startDate, $endDate],
                                'is_unrestricted' => $isUnrestricted,
                                'current_roles' => $currentRoles,
                                'dist_map_rp' => $mapRp,
                                'dist_map' => $map
                            ]
                        ];
                    } catch (\Throwable $e) {
                        return [
                            'payment_total' => 0,
                            'debug' => [
                                'error' => $e->getMessage() . ' | ROLES: ' . implode(',', $currentRoles),
                                'resolved_target_id' => 'ERROR',
                                'is_unrestricted' => $isUnrestricted ?? false,
                                'current_roles' => $currentRoles
                            ]
                        ];
                    }
                }
            ]);
            ;




            $receiptIds = collect($paginatedSales->items())->pluck('receipt_id')->unique()->toArray();
            $ttData = \App\Models\TukarTambah::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');
            $dgData = \App\Models\Downgrade::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');
            $ueData = \App\Models\UnitExchange::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');
            $rfData = \App\Models\Refund::whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');

            $dailySales = collect($paginatedSales->items())->map(function ($trx) use ($branches, $onlineShops, $questions, $paymentMethods, $distributors, $ttData, $dgData, $ueData, $rfData) {
                $details = [];
                $catLower = strtolower($trx->category);
                $receiptId = $trx->receipt_id;
                $isNeg = in_array($catLower, ['tukar_tambah', 'downgrade', 'refund', 'angkat_barang']);

                // Pre-identify exchange data
                $exchangeInfo = null;
                $exchangeType = '';
                if ($catLower === 'tukar_tambah') { $exchangeInfo = $ttData->get($receiptId); $exchangeType = 'TUKAR TAMBAH'; }
                elseif ($catLower === 'downgrade') { $exchangeInfo = $dgData->get($receiptId); $exchangeType = 'DOWNGRADE'; }
                elseif ($catLower === 'tukar_unit') { $exchangeInfo = $ueData->get($receiptId); $exchangeType = 'TUKAR UNIT'; }
                elseif ($catLower === 'refund') { $exchangeInfo = $rfData->get($receiptId); $exchangeType = 'REFUND'; }

                foreach ($trx->items as $item) {
                    $dId = $item->distributor_id ?? $item->pivot?->distributor_id;
                    $dist = $dId ? $distributors->get($dId) : null;
                    $dName = $dist ? ($dist->name ?? 'KOSONG') : ($item->supplier_name ?? 'KOSONG');
                    $basePrice = ($item->pivot?->selling_price ?? $item->selling_price ?? 0) - ($item->pivot?->item_discount ?? 0);

                    if ($basePrice <= 0 && in_array(strtolower($trx->category), ['angkat_barang', 'refund', 'tukar_unit', 'tukar_tambah', 'downgrade'])) {
                        $basePrice = (float) ($item->cost_price ?? 0);
                    }

                    $pName = ($trx->is_bundle ? '📦 ' : '') . ($item->product?->name ?? 'Unknown HP');
                    $pImei = $item->imei ?? '-';

                    if ($exchangeInfo && in_array($catLower, ['tukar_tambah', 'downgrade', 'tukar_unit'])) {
                        $inProd = ($exchangeInfo->incomingProductType->name ?? 'Unit Konsumen');
                        $inImei = $exchangeInfo->incoming_imei ?? '-';
                        
                        // Add a special "Incoming" item to the details array
                        // This makes it show as a second row in CheckSales and Nota
                        $details[] = [
                            'name' => "IN: " . $inProd,
                            'qty' => 1,
                            'price' => -(float)($exchangeInfo->incoming_cost_price ?? 0),
                            'brand' => $exchangeInfo->incomingProductType->brand->name ?? '-',
                            'type' => 'IN',
                            'is_hp' => true,
                            'imei' => $inImei,
                            'distributor_name' => $exchangeInfo->distributor->name ?? 'Konsumen',
                            'condition' => $exchangeInfo->incoming_condition ?? 'second',
                            'storage' => $exchangeInfo->incoming_storage ?? '-',
                            'is_incoming' => true
                        ];

                        $pName = "OUT: " . $pName;
                    } elseif (in_array($catLower, ['refund', 'angkat_barang'])) {
                        $pName = "IN: " . $pName;
                    }

                    $details[] = [
                        'name' => $pName,
                        'qty' => 1,
                        'price' => $basePrice,
                        'brand' => $item->product?->brand ?? '-',
                        'type' => 'HP',
                        'is_hp' => true,
                        'imei' => $pImei,
                        'distributor_name' => $dName,
                        'ram' => $item->storage,
                        'storage' => $item->storage,
                        'condition' => $item->condition,
                        'notes' => $item->pivot?->notes
                    ];
                }
                foreach ($trx->nonHpDetails as $item) {
                    $qty = $item->quantity;
                    $price = ($item->selling_price ?? 0) - ($item->item_discount ?? 0);
                    $did = $item->distributor_id;
                    if (!$did && $item->product) {
                        $did = $item->product->distributor_id;
                    }
                    $dist = $did ? $distributors->get($did) : null;
                    $dName = $dist ? ($dist->name ?? 'KOSONG') : ($item->supplier_name ?? 'KOSONG');

                    $details[] = [
                        'name' => ($trx->is_bundle ? '📦 ' : '') . ($item->product?->name ?? 'Item Non-HP'),
                        'qty' => $qty,
                        'price' => $price,
                        'brand' => $item->product?->brand ?? '-',
                        'type' => 'Item',
                        'is_hp' => false,
                        'imei' => '-',
                        'distributor_name' => $dName,
                        'notes' => $item->notes
                    ];
                }

                if (empty($details) && $trx->non_hp_items && is_array($trx->non_hp_items)) {
                    foreach ($trx->non_hp_items as $item) {
                        $pId = $item['product_id'] ?? null;
                        $prod = $pId ? \App\Models\Product::find($pId) : null;

                        $details[] = [
                            'name' => ($trx->is_bundle ? '📦 ' : '') . ($prod->name ?? 'Item Non-HP'),
                            'qty' => $item['quantity'] ?? 1,
                            'price' => $item['selling_price'] ?? 0,
                            'brand' => $prod->brand ?? '-',
                            'type' => 'Item',
                            'is_hp' => false,
                            'imei' => '-',
                            'distributor_name' => '-',
                            'notes' => $item['notes'] ?? null
                        ];
                    }
                }

                // GROUPING LOGIC FOR BUNDLES
                if ($trx->is_bundle) {
                    $grouped = [];
                    $bundles = [];
                    $fallbackBundleName = $trx->bundle_description ?: 'Paket Bundling';
                    
                    // Prepare bundle components for smarter matching (historical data)
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
                            // Try fuzzy match against remaining components for historical data
                            foreach ($bundleComponents as $idx => $comp) {
                                if (!empty($comp) && (str_contains(strtolower($cleanName), strtolower($comp)) || str_contains(strtolower($comp), strtolower($cleanName)))) {
                                    $isPartOfBundle = true;
                                    $groupKey = $fallbackBundleName;
                                    unset($bundleComponents[$idx]); // Consume this component
                                    break;
                                }
                            }
                        }
                        
                        if ($isPartOfBundle && $groupKey) {
                            if (!isset($bundles[$groupKey])) {
                                $bundles[$groupKey] = [
                                    'name' => '📦 ' . $groupKey,
                                    'qty' => 1,
                                    'price' => 0,
                                    'brand' => 'BUNDLE',
                                    'type' => 'Bundle',
                                    'is_hp' => false,
                                    'imei' => [],
                                    'distributor_name' => [],
                                    'storage' => '-',
                                    'condition' => '-',
                                    'ram' => '-',
                                    'is_bundle' => true
                                ];
                            }
                            $bundles[$groupKey]['price'] += ($d['price'] * $d['qty']);
                            if ($d['imei'] && $d['imei'] !== '-') {
                                $bundles[$groupKey]['imei'][] = $d['imei'];
                                $bundles[$groupKey]['is_hp'] = true;
                            }
                            if ($d['distributor_name'] && $d['distributor_name'] !== 'KOSONG') {
                                $bundles[$groupKey]['distributor_name'][] = $d['distributor_name'];
                            }
                        } else {
                            $grouped[] = $d;
                        }
                    }
                    
                    foreach ($bundles as $bName => $bData) {
                        $bData['imei'] = implode(', ', array_unique($bData['imei'])) ?: '-';
                        $bData['distributor_name'] = implode(', ', array_unique($bData['distributor_name'])) ?: 'KOSONG';
                        $grouped[] = $bData;
                    }
                    
                    if (!empty($bundles)) {
                        $details = $grouped;
                    }
                }

                $paymentMethodNames = [];
                if ($trx->payment_method_id) {
                    $pm = $paymentMethods->get($trx->payment_method_id);
                    if ($pm)
                        $paymentMethodNames[] = $pm->name;
                }
                if ($trx->split_payments) {
                    $splits = is_array($trx->split_payments) ? $trx->split_payments : json_decode($trx->split_payments, true);
                    if (is_array($splits)) {
                        foreach ($splits as $sp) {
                            $pmId = $sp['payment_method_id'] ?? ($sp['method_id'] ?? null);
                            if ($pmId) {
                                $pm = $paymentMethods->get($pmId);
                                if ($pm)
                                    $paymentMethodNames[] = $pm->name;
                            }
                        }
                    }
                }
                $finalPaymentMethods = implode(', ', array_unique($paymentMethodNames)) ?: '-';

                $detailedSplitPayments = collect($trx->split_payments_data ?? [])->map(function($sp) use ($isNeg) {
                    if ($isNeg) {
                        $sp['amount'] = -abs((float)($sp['amount'] ?? 0));
                    }
                    return $sp;
                })->toArray();

                $totalQty = collect($details)->sum('qty');


                $rawSellingPrice = (float) $trx->selling_price;
                $priceOut = $rawSellingPrice;
                if ($exchangeInfo) {
                    $pIn = (float)($exchangeInfo->incoming_cost_price ?? 0);
                    $pOut = (float)($exchangeInfo->outgoing_price ?? 0);
                    if ($pOut == 0) $pOut = (float) $trx->selling_price; // Fallback
                    $rawSellingPrice = $pOut - $pIn;
                    $priceOut = $pOut;
                }

                $finalPrice = $isNeg ? -abs($rawSellingPrice) : $rawSellingPrice;

                return [
                    'id' => $trx->id,
                    'date' => $trx->created_at?->toDateTimeString() ?? '-',
                    'order_no' => $trx->receipt_id,
                    'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? '-',
                    'customer_phone' => $trx->customer_wa ?? '-',
                    'category' => $trx->category,
                    'sales_name' => $trx->sales_account ?? ($trx->inventoryUser?->name) ?? '-',
                    'qty' => $totalQty,
                    'items' => $details,
                    'grand_total' => $finalPrice,
                    'selling_price' => $finalPrice,
                    'total_discount' => (float) $trx->total_discount,
                    'original_price' => (float) ($priceOut + $trx->total_discount),
                    'is_bundle' => (bool) $trx->is_bundle,
                    'bundle_description' => $trx->bundle_description,
                    'payment_method_name' => $finalPaymentMethods,
                    'split_payments_data' => $detailedSplitPayments,
                    'status' => ($catLower === 'penjualan_store' || $catLower === 'penjualan_offline') ? 'Lunas' : ($isNeg ? 'Belum Lunas' : ($trx->status ?? 'Lunas')),
                    'notes' => $trx->notes,
                    'proof_images' => collect([
                        $trx->proof_image,
                        $exchangeInfo->photo_unit ?? null,
                        $exchangeInfo->photo_customer ?? null
                    ])->filter()->unique()->map(fn($path) => asset('storage/' . $path))->values()->toArray(),
                    'proof_image' => $trx->proof_image 
                        ? asset('storage/' . $trx->proof_image) 
                        : ($exchangeInfo && $exchangeInfo->photo_unit 
                            ? asset('storage/' . $exchangeInfo->photo_unit) 
                            : null),
                    // Specialized Pricing for UI columns if needed
                    'price_out' => $exchangeInfo ? $priceOut : null,
                    'price_in' => $exchangeInfo ? (float)($exchangeInfo->incoming_cost_price ?? 0) : null,
                    'price_diff' => $finalPrice,
                ];
            });

            $formattedBrandSales = collect($brandSalesRaw['hp'])->map(fn($i) => [...(array) $i, 'is_hp' => true])->concat(collect($brandSalesRaw['nhp'])->map(fn($i) => [...(array) $i, 'condition' => '-', 'storage' => '-', 'distributor' => '-', 'is_hp' => false]))->toArray();

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
        } catch (\Throwable $e) {
            error_log("Global Sales Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
    
    public function downloadProof(Request $request)
    {
        $url = $request->url;
        if (!$url) return response()->json(['message' => 'URL is required'], 400);

        // Convert URL to local path relative to storage/app/public
        $baseUrl = asset('storage/');
        $path = str_replace($baseUrl . '/', '', $url);
        
        if ($path === $url) {
            $path = str_replace($baseUrl, '', $url);
        }

        if (str_contains($path, '..')) {
            return response()->json(['message' => 'Invalid path'], 403);
        }

        if (!\Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found in storage: ' . $path], 404);
        }

        return \Storage::disk('public')->download($path);
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

        // Fallback: If ID is not numeric, it might be a name
        if ($requestedBranchId && !is_numeric($requestedBranchId)) {
            $foundBranch = Branch::where('name', 'ilike', '%' . $requestedBranchId . '%')->first();
            $requestedBranchId = $foundBranch ? $foundBranch->id : null;
        }
        if ($requestedOnlineShopId && !is_numeric($requestedOnlineShopId)) {
            $foundOs = OnlineShop::where('name', 'ilike', '%' . $requestedOnlineShopId . '%')->first();
            $requestedOnlineShopId = $foundOs ? $foundOs->id : null;
        }
        if ($requestedWarehouseId && !is_numeric($requestedWarehouseId)) {
            $foundW = Warehouse::where('name', 'ilike', '%' . $requestedWarehouseId . '%')->first();
            $requestedWarehouseId = $foundW ? $foundW->id : null;
        }
        if ($requestedDistributorId && !is_numeric($requestedDistributorId)) {
            $foundD = Distributor::where('name', 'ilike', '%' . $requestedDistributorId . '%')->first();
            $requestedDistributorId = $foundD ? $foundD->id : null;
        }

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
                    if (!empty($bIds)) {
                        $q->orWhereIn('stock_outs.branch_id', $bIds)
                            ->orWhereIn('users.branch_id', $bIds);
                    }
                    if (!empty($osIds)) {
                        $q->orWhereIn('stock_outs.online_shop_id', $osIds)
                            ->orWhereIn('users.online_shop_id', $osIds);
                    }
                    if (!empty($wIds)) {
                        $q->orWhereIn('stock_outs.warehouse_id', $wIds)
                            ->orWhereIn('users.warehouse_id', $wIds);
                    }
                    if (!empty($dIds)) {
                        $q->orWhereIn('users.distributor_id', $dIds);
                    }
                })->count(),

            // 6. Stock Out Non-HP (This Month)
            fn() => (int) DB::table('stock_out_non_hp_items')->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')->join('users', 'stock_outs.user_id', '=', 'users.id')
                ->whereMonth('stock_outs.reporting_date', now()->month)->whereYear('stock_outs.reporting_date', now()->year)
                ->where(function ($q) use ($bIds, $osIds, $wIds, $dIds) {
                    if (!empty($bIds)) {
                        $q->orWhereIn('stock_outs.branch_id', $bIds)
                            ->orWhereIn('users.branch_id', $bIds);
                    }
                    if (!empty($osIds)) {
                        $q->orWhereIn('stock_outs.online_shop_id', $osIds)
                            ->orWhereIn('users.online_shop_id', $osIds);
                    }
                    if (!empty($wIds)) {
                        $q->orWhereIn('stock_outs.warehouse_id', $wIds)
                            ->orWhereIn('users.warehouse_id', $wIds);
                    }
                    if (!empty($dIds)) {
                        $q->orWhereIn('users.distributor_id', $dIds);
                    }
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

        // Fallback: If ID is not numeric, it might be a name
        if ($requestedBranchId && !is_numeric($requestedBranchId)) {
            $foundBranch = Branch::where('name', 'ilike', '%' . $requestedBranchId . '%')->first();
            $requestedBranchId = $foundBranch ? $foundBranch->id : null;
        }
        if ($requestedOnlineShopId && !is_numeric($requestedOnlineShopId)) {
            $foundOs = OnlineShop::where('name', 'ilike', '%' . $requestedOnlineShopId . '%')->first();
            $requestedOnlineShopId = $foundOs ? $foundOs->id : null;
        }
        if ($requestedWarehouseId && !is_numeric($requestedWarehouseId)) {
            $foundW = Warehouse::where('name', 'ilike', '%' . $requestedWarehouseId . '%')->first();
            $requestedWarehouseId = $foundW ? $foundW->id : null;
        }
        if ($requestedDistributorId && !is_numeric($requestedDistributorId)) {
            $foundD = Distributor::where('name', 'ilike', '%' . $requestedDistributorId . '%')->first();
            $requestedDistributorId = $foundD ? $foundD->id : null;
        }

        // Optimized Aggregation using SQL and Octane Concurrency
        [$summaryRaw, $trendRaw, $breakdownRaw] = Octane::concurrently([
            // 1. Summary Stats
            fn() => StockOut::whereIn('category', $salesCategories)->whereYear('reporting_date', $year)
                ->when($month, fn($q) => $q->whereMonth('reporting_date', $month))
                ->when($request->date, fn($q) => $q->where('reporting_date', $request->date))
                ->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    if ($requestedBranchId) {
                        $q->where('stock_outs.branch_id', $requestedBranchId)
                            ->orWhereHas('user', fn($uq) => $uq->where('branch_id', $requestedBranchId));
                    } elseif ($requestedOnlineShopId) {
                        $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)
                            ->orWhereHas('user', fn($uq) => $uq->where('online_shop_id', $requestedOnlineShopId));
                    } elseif ($requestedWarehouseId) {
                        $q->where('stock_outs.warehouse_id', $requestedWarehouseId)
                            ->orWhereHas('user', fn($uq) => $uq->where('warehouse_id', $requestedWarehouseId));
                    } elseif ($requestedDistributorId) {
                        $q->whereHas('user', fn($uq) => $uq->where('distributor_id', $requestedDistributorId));
                    } else {
                        $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                            if (!empty($branchIds)) {
                                $sub->orWhereIn('stock_outs.branch_id', $branchIds)
                                    ->orWhereHas('user', fn($uq) => $uq->whereIn('branch_id', $branchIds));
                            }
                            if (!empty($onlineShopIds)) {
                                $sub->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)
                                    ->orWhereHas('user', fn($uq) => $uq->whereIn('online_shop_id', $onlineShopIds));
                            }
                            if (!empty($warehouseIds)) {
                                $sub->orWhereIn('stock_outs.warehouse_id', $warehouseIds)
                                    ->orWhereHas('user', fn($uq) => $uq->whereIn('warehouse_id', $warehouseIds));
                            }
                            if (!empty($distributorIds)) {
                                $sub->orWhereHas('user', fn($uq) => $uq->whereIn('distributor_id', $distributorIds));
                            }
                        });
                    }
                })->selectRaw('SUM(selling_price) as revenue, COUNT(*) as trx_count')->first(),

            // 2. Daily Trend
            fn() => StockOut::whereIn('category', $salesCategories)->whereYear('reporting_date', $year)
                ->when($month, fn($q) => $q->whereMonth('reporting_date', $month))
                ->when($request->date, fn($q) => $q->where('reporting_date', $request->date))
                ->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId, $requestedDistributorId) {
                    if ($requestedBranchId) {
                        $q->where('stock_outs.branch_id', $requestedBranchId)
                            ->orWhereHas('user', fn($uq) => $uq->where('branch_id', $requestedBranchId));
                    } elseif ($requestedOnlineShopId) {
                        $q->where('stock_outs.online_shop_id', $requestedOnlineShopId)
                            ->orWhereHas('user', fn($uq) => $uq->where('online_shop_id', $requestedOnlineShopId));
                    } elseif ($requestedWarehouseId) {
                        $q->where('stock_outs.warehouse_id', $requestedWarehouseId)
                            ->orWhereHas('user', fn($uq) => $uq->where('warehouse_id', $requestedWarehouseId));
                    } elseif ($requestedDistributorId) {
                        $q->whereHas('user', fn($uq) => $uq->where('distributor_id', $requestedDistributorId));
                    } else {
                        $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $distributorIds) {
                            if (!empty($branchIds)) {
                                $sub->orWhereIn('stock_outs.branch_id', $branchIds)
                                    ->orWhereHas('user', fn($uq) => $uq->whereIn('branch_id', $branchIds));
                            }
                            if (!empty($onlineShopIds)) {
                                $sub->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)
                                    ->orWhereHas('user', fn($uq) => $uq->whereIn('online_shop_id', $onlineShopIds));
                            }
                            if (!empty($warehouseIds)) {
                                $sub->orWhereIn('stock_outs.warehouse_id', $warehouseIds)
                                    ->orWhereHas('user', fn($uq) => $uq->whereIn('warehouse_id', $warehouseIds));
                            }
                            if (!empty($distributorIds)) {
                                $sub->orWhereHas('user', fn($uq) => $uq->whereIn('distributor_id', $distributorIds));
                            }
                        });
                    }
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
                    elseif ($requestedWarehouseId)
                        $sub->where('users.warehouse_id', $requestedWarehouseId);
                    elseif ($requestedDistributorId)
                        $sub->where('users.distributor_id', $requestedDistributorId);
                    else {
                        if (!empty($branchIds))
                            $sub->orWhereIn('users.branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $sub->orWhereIn('users.online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds))
                            $sub->orWhereIn('users.warehouse_id', $warehouseIds);
                        if (!empty($distributorIds))
                            $sub->orWhereIn('users.distributor_id', $distributorIds);
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

        // Fallback: If ID is not numeric, it might be a name
        if ($requestedBranchId && !is_numeric($requestedBranchId)) {
            $foundBranch = Branch::where('name', 'ilike', '%' . $requestedBranchId . '%')->first();
            $requestedBranchId = $foundBranch ? $foundBranch->id : null;
        }
        if ($requestedOnlineShopId && !is_numeric($requestedOnlineShopId)) {
            $foundOs = OnlineShop::where('name', 'ilike', '%' . $requestedOnlineShopId . '%')->first();
            $requestedOnlineShopId = $foundOs ? $foundOs->id : null;
        }
        if ($requestedWarehouseId && !is_numeric($requestedWarehouseId)) {
            $foundWarehouse = Warehouse::where('name', 'ilike', '%' . $requestedWarehouseId . '%')->first();
            $requestedWarehouseId = $foundWarehouse ? $foundWarehouse->id : null;
        }
        if ($requestedDistributorId && !is_numeric($requestedDistributorId)) {
            $foundDistributor = Distributor::where('name', 'ilike', '%' . $requestedDistributorId . '%')->first();
            $requestedDistributorId = $foundDistributor ? $foundDistributor->id : null;
        }

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

        $dailySalesQuery = StockOut::with(['items.product.brandRelation', 'nonHpItems.product.brandRelation', 'user', 'inventoryUser', 'auditAnswers', 'auditProfit'])
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
                $price = ($item->pivot->selling_price > 0) ? $item->pivot->selling_price : ($item->product?->price ?? 0);
                $details[] = [
                    'id' => 'hp_' . $item->id,
                    'name' => $item->product?->name ?? 'Unknown HP',
                    'qty' => 1,
                    'price' => $price,
                    'is_fixed' => true,
                    'brand' => $item->product?->brand ?? $item->product?->brandRelation?->name ?? '-',
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
                        'brand' => $product?->brand ?? $product?->brandRelation?->name ?? '-',
                        'type' => 'Non-HP',
                        'raw_cost_price' => (float) ($product?->cost_price ?? 0)
                    ];
                    $calculatedTotal += ($price * $qty);
                }
            } else {
                foreach ($trx->nonHpItems as $nhp) {
                    $basePrice = $nhp->product?->price ?? 0;
                    $details[] = [
                        'id' => 'nonhp_' . $nhp->id,
                        'name' => $nhp->product?->name ?? 'Unknown Item',
                        'qty' => $nhp->quantity,
                        'price' => $basePrice,
                        'is_fixed' => true,
                        'brand' => $nhp->product?->brand ?? $nhp->product?->brandRelation?->name ?? '-',
                        'type' => 'Non-HP',
                        'raw_cost_price' => (float) ($nhp->product?->cost_price ?? 0)
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

            $catLower = strtolower($trx->category);
            $isNeg = in_array($catLower, ['tukar_tambah', 'downgrade', 'refund', 'angkat_barang']);

            $hargaJual = (float) ($trx->selling_price ?? 0);
            $hargaModal = $savedProfit ? (float) $savedProfit->harga_modal : null;
            $defaultHargaModal = $hargaJual > 0 ? round($hargaJual * 0.95) : 0;
            $profit = $hargaJual - $totalHargaModal;

            if ($isNeg) {
                $hargaJual = -abs($hargaJual);
                $profit = -abs($profit);
            }

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
     * Export Audit Sales data as Excel.
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

        // Role-based Date Restriction (similar to sales() method)
        if (!$user->hasAnyRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist', 'analis'])) {
            $today = $logicalNow->toDateString();
            $sixDaysAgo = $logicalNow->copy()->subDays(6)->toDateString();
            $startOfThisMonth = $logicalNow->copy()->startOfMonth()->toDateString();
            $startOfLastMonth = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();

            if ($startDate === $endDate) {
                if ($startDate < $sixDaysAgo) {
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

        $branchId = $request->branch_id;
        $onlineShopId = $request->online_shop_id;

        try {
            $export = new \App\Exports\SalesExport($branchId, $onlineShopId, $startDate, $endDate, $user);
            
            $locationName = 'ALL';
            if ($branchId) $locationName = \App\Models\Branch::find($branchId)?->name ?? 'CABANG';
            elseif ($onlineShopId) $locationName = \App\Models\OnlineShop::find($onlineShopId)?->name ?? 'ONLINE';
            
            $filename = "LAPORAN_PENJUALAN_" . strtoupper(str_replace(' ', '_', $locationName)) . "_{$startDate}_SD_{$endDate}.xlsx";

            // Log Export
            \App\Models\ExportLog::create([
                'user_id' => $user->id,
                'report_name' => 'Laporan Penjualan',
                'filename' => $filename,
                'params' => $request->all()
            ]);

            $xlsxData = [];
            $xlsxData[] = $export->headings();

            $rows = $export->collection();
            foreach ($rows as $row) {
                $cat = strtolower($row['category'] ?? '');
                $isExchange = str_contains($cat, 'tukar') || str_contains($cat, 'downgrade');
                
                $xlsxRow = [
                    $row['waktu'] ?? '',
                    $row['order_no'] ?? '',
                    $row['lokasi'] ?? '',
                    $row['user'] ?? '',
                    $row['customer'] ?? '',
                    $row['whatsapp'] ?? '',
                    $row['category'] ?? '',
                    $row['product'] ?? '',
                    str_replace("'", "", $row['imei'] ?? '-'),
                    $row['qty'] ?? 1,
                    $row['price'] ?? '',
                    $row['total'] ?? '',
                    $row['distributor'] ?? '',
                    $row['payment'] ?? '',
                ];

                if (isset($row['payment_details'])) {
                    foreach ($row['payment_details'] as $amt) {
                        $xlsxRow[] = $amt;
                    }
                }

                $xlsxRow = array_merge($xlsxRow, [
                    $row['status'] ?? '',
                    ($isExchange && $row['price_out'] != 0) ? $row['price_out'] : '',
                    ($isExchange && $row['price_in'] != 0) ? $row['price_in'] : '',
                    ($isExchange && $row['balance'] != 0) ? $row['balance'] : ''
                ]);

                $xlsxData[] = $xlsxRow;
            }

            // Log Export
            \App\Models\ExportLog::create([
                'user_id' => $user->id,
                'report_name' => 'Laporan Penjualan (Excel)',
                'filename' => $filename,
                'params' => [
                    'branch_id' => $branchId,
                    'online_shop_id' => $onlineShopId,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]);

            return response((string)\App\Utils\SimpleXLSXGen::fromArray($xlsxData), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Export Sales Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['message' => 'Export failed: ' . $e->getMessage()], 500);
        }
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

        $query = StockOut::with(['items.product.brandRelation', 'nonHpItems.product.brandRelation', 'user', 'inventoryUser', 'auditAnswers', 'destination'])
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
                'brand_names' => collect()->concat($trx->items->map(fn($i) => $i->product->brand ?? $i->product->brandRelation->name ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->brand ?? $i->product->brandRelation->name ?? '-'))->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-',
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

    public function audit(Request $request)
    {
        $salesResponse = $this->sales($request);
        $data = $salesResponse->getData(true)['report_summary'] ?? null;

        if (!$data || isset($data['error'])) {
            return response()->json(['report' => "Terjadi kesalahan saat memproses data: " . ($data['error'] ?? 'Data tidak ditemukan')]);
        }

        $branchName = 'CABANG TRIAL [UPDATED]';
        if ($request->branch_id) {
            $branch = \App\Models\Branch::find($request->branch_id);
            if ($branch)
                $branchName = $branch->name . ' [UPDATED]';
        }

        $date = $request->start_date ? date('d F Y', strtotime($request->start_date)) : date('d F Y');
        $payments = $data['payments'] ?? [];
        $pTotal = $data['payment_total'] ?? 0;
        $dMap = $data['dist_map'] ?? [];
        $dMapRp = $data['dist_map_rp'] ?? [];
        $sReport = $data['stock_report'] ?? [];
        $sDetails = $data['stock_details'] ?? [];

        $report = "*LAPORAN PENJUALAN *\n" . strtoupper($branchName) . "\n$date\n============\n\nPENJUALAN ALL\n\n";

        if (empty($payments) || $pTotal == 0) {
            $report .= "Belum ada transaksi\n\n";
        } else {
            foreach ($payments as $method => $amt) {
                if ($amt != 0) {
                    $report .= strtoupper($method) . " : Rp " . number_format($amt, 0, ',', '.') . "\n";
                }
            }
        }

        $report .= "\n*Total : Rp " . number_format($pTotal, 0, ',', '.') . "*\n";
        $report .= "__________________\n__________________\n\nRincian Penjualan berdasarkan distributor\n\n";

        $dispMap = [
            'hp' => ['label' => 'Penjualan HP', 'icon' => '🟦'],
            'apple_lux' => ['label' => 'Penjualan Apple Luxury', 'icon' => '🟦'],
            'apply' => ['label' => 'Penjualan apply', 'icon' => '⬜️'],
            'arcis' => ['label' => 'Penjualan arcis', 'icon' => '⬜️'],
            'perdana' => ['label' => 'Penjualan perdana', 'icon' => '⬜️'],
            'jaringan' => ['label' => '4G / LTE', 'icon' => '⬜️'],
            'laptop' => ['label' => 'Penjualan laptop', 'icon' => '⬜️'],
            'tv' => ['label' => 'Penjualan tv', 'icon' => '⬜️'],
            'jasa' => ['label' => 'Penjualan Jasa', 'icon' => '⬜️'],
        ];

        foreach ($dispMap as $key => $conf) {
            if (isset($dMapRp[$key]) && $dMapRp[$key] > 0) {
                $report .= $conf['icon'] . " " . $conf['label'] . " : Rp " . number_format($dMapRp[$key], 0, ',', '.') . "\n";
            }
        }

        $report .= "__________________\n__________________\nunit HP keluar\n\n";
        $report .= "Iphone           : " . ($dMap['iphone'] ?? 0) . "\n";
        $report .= "Apple Luxury     : " . ($dMap['apple_lux'] ?? 0) . "\n";
        $report .= "Android          : " . ($dMap['android'] ?? 0) . "\n";
        $report .= "Total HP         : " . (($dMap['iphone'] ?? 0) + ($dMap['android'] ?? 0) + ($dMap['apple_lux'] ?? 0)) . "\n\n";

        $acts = $data['activities'] ?? [];
        $report .= "Tukar unit       : " . ($acts['tukar_unit'] ?? 0) . "\n";
        $report .= "Tukar tambah     : " . ($acts['tukar_tambah'] ?? 0) . "\n";
        $report .= "Downgrade        : " . ($acts['downgrade'] ?? 0) . "\n";
        $report .= "Refund           : " . ($acts['refund'] ?? 0) . "\n";
        $report .= "Angkat barang    : " . ($acts['angkat_barang'] ?? 0) . "\n";

        $report .= "\nLaptop           : " . ($dMap['laptop'] ?? 0) . "\nTv               : " . ($dMap['tv'] ?? 0) . "\npengunjung       : .........\n";
        $report .= "__________________\n__________________\n\n*Laporan keuangan*\n\n🔶 total cash ready\n………………\n………………\n\n🔶 RICIAN PENGELUARAN\n………………\n………………\nTotal     :\n\n🔶 RINCIAN DEPOSIT TOKO\n………………\n………………\nTotal     :\n\nAWAL   :\nIN          :\nSISA     :\n__________________\n__________________\n\nRincian Unit & Stok\n\n";

        $stkMap = [
            'apple_lux' => 'stok Apple Luxury',
            'accessories' => 'stok accessories',
            'apply' => 'stok apply',
            'debs' => 'stok debs',
            'arcis' => 'stok arcis',
            'laptop' => 'stok laptop',
            'tv' => 'stok tv',
            'perdana' => 'stok Sim Card',
            'jaringan' => 'stok 4G / LTE'
        ];

        foreach ($stkMap as $key => $label) {
            $report .= "🔷 $label\n";
            $items = $sDetails[$key] ?? [];
            if (empty($items)) {
                $report .= "- (kosong)\n\n";
            } else {
                foreach ($items as $name => $qty) {
                    $report .= "- $name : $qty unit\n";
                }
                $report .= "\n";
            }
        }

        $details = $acts['details'] ?? [];
        if (!empty($details['refund']) || !empty($details['angkat_barang'])) {
            $report .= "__________________\n__________________\n\n*Rincian Unit*\n";
            if (!empty($details['refund'])) {
                $report .= "\n*Rincian Refund:*\n";
                foreach ($details['refund'] as $d) {
                    $report .= "• " . ($d['name'] ?? '-') . "\n  IMEI: " . ($d['imei'] ?? '-') . "\n";
                }
            }
            if (!empty($details['angkat_barang'])) {
                $report .= "\n*Rincian Angkat Barang:*\n";
                foreach ($details['angkat_barang'] as $d) {
                    $report .= "• " . ($d['name'] ?? '-') . "\n  IMEI: " . ($d['imei'] ?? '-') . "\n";
                }
            }
        }

        return response()->json(['report' => $report, 'data' => $data]);
    }
}
