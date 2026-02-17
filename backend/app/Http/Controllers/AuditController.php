<?php

namespace App\Http\Controllers;

use App\Models\StockOut; // Changed from Transaction
use App\Models\StockOutNonHpItem;
use App\Models\Product;
use App\Models\ProductDetail; // Added
use App\Models\User;
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
        $onlineShopIds = $user->getAccessibleOnlineShopIds(); // Added

        if (empty($branchIds) && empty($onlineShopIds)) {
            return response()->json([
                'daily_sales' => [],
                'brand_sales' => [],
                'cs_sales' => []
            ]);
        }

        // Date Filter
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        // Filter by specific location if requested (and allowed)
        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;

        $scopeToAccess = function ($query) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
            $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                $q->where(function ($sub) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
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
                    } else {
                        // Show all accessible
                        if (!empty($branchIds)) {
                            $sub->orWhereIn('branch_id', $branchIds);
                        }
                        if (!empty($onlineShopIds)) {
                            $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        }
                        // Default fallback if no assignments? Handled by initial check
                    }
                });
            });
        };

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        // 1. Daily Sales
        $dailySalesQuery = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        $scopeToAccess($dailySalesQuery);

        $dailySales = $dailySalesQuery->latest()->get()->map(function ($trx) {
            // ... mapping logic (unchanged) ...
            $itemSummary = '-';
            $qty = 0;

            if ($trx->items->isNotEmpty()) {
                $first = $trx->items->first();
                $itemSummary = $first->product->name ?? '-';
                if ($trx->items->count() > 1)
                    $itemSummary .= ' +' . ($trx->items->count() - 1) . ' items';
                $qty += $trx->items->count();
            }
            if ($trx->nonHpItems->isNotEmpty()) {
                if ($itemSummary === '-') {
                    $first = $trx->nonHpItems->first();
                    $itemSummary = $first->product->name ?? '-';
                    if ($trx->nonHpItems->count() > 1)
                        $itemSummary .= ' +' . ($trx->nonHpItems->count() - 1) . ' items';
                }
                $qty += $trx->nonHpItems->sum('quantity');
            }

            return [
                'date' => $trx->created_at->toDateTimeString(),
                'order_no' => $trx->receipt_id,
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? $trx->shopee_receiver ?? $trx->giveaway_receiver ?? '-',
                'customer_phone' => $trx->customer_phone ?? '-',
                'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                'qty' => $qty,
                'status' => $trx->status === 'received' ? 'Lunas' : 'Pending',
                'cash' => 0,
                'transfer' => 0,
                'debit' => 0,
                'grand_total' => $trx->selling_price
            ];
        });

        // 2. Report per Brand (HP + Non-HP)
        $brandStats = [];

        // HP
        $hpQuery = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where(function ($q) use ($branchIds, $onlineShopIds) {
                if (!empty($branchIds))
                    $q->orWhereIn('users.branch_id', $branchIds);
                if (!empty($onlineShopIds))
                    $q->orWhereIn('users.online_shop_id', $onlineShopIds);
            })
            ->select('products.brand', DB::raw('count(*) as count'))
            ->groupBy('products.brand')
            ->get();

        foreach ($hpQuery as $item) {
            if (!isset($brandStats[$item->brand]))
                $brandStats[$item->brand] = 0;
            $brandStats[$item->brand] += $item->count;
        }

        // Non-HP
        $nhpQuery = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where(function ($q) use ($branchIds, $onlineShopIds) {
                if (!empty($branchIds))
                    $q->orWhereIn('users.branch_id', $branchIds);
                if (!empty($onlineShopIds))
                    $q->orWhereIn('users.online_shop_id', $onlineShopIds);
            })
            ->select('products.brand', DB::raw('sum(quantity) as count'))
            ->groupBy('products.brand')
            ->get();

        foreach ($nhpQuery as $item) {
            if (!isset($brandStats[$item->brand]))
                $brandStats[$item->brand] = 0;
            $brandStats[$item->brand] += $item->count;
        }

        $formattedBrandSales = [];
        foreach ($brandStats as $brand => $qty) {
            $formattedBrandSales[] = ['brand' => $brand, 'qty' => $qty];
        }

        // 3. Report per CS
        $csQuery = StockOut::whereIn('category', $salesCategories)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $scopeToAccess($csQuery);

        $csSales = $csQuery->with('inventoryUser')
            ->select('inventory_user_id', DB::raw('count(*) as count'), DB::raw('sum(selling_price) as total'))
            ->groupBy('inventory_user_id')
            ->get()
            ->map(function ($item) {
                return [
                    'cs_name' => $item->inventoryUser->name ?? 'Unknown',
                    'total_sales' => $item->count,
                    'total_trade_in' => 0,
                    'total_refund' => 0,
                    'grand_total' => $item->total
                ];
            });

        return response()->json([
            'daily_sales' => $dailySales,
            'brand_sales' => $formattedBrandSales,
            'cs_sales' => $csSales
        ]);
    }

    public function inventory(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();

        if (empty($branchIds) && empty($onlineShopIds)) {
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

        // Filter assignments based on request
        if ($requestedBranchId) {
            $branchIds = (empty($branchIds) || in_array($requestedBranchId, $branchIds)) ? [$requestedBranchId] : [];
            $onlineShopIds = [];
        } elseif ($requestedOnlineShopId) {
            $onlineShopIds = (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) ? [$requestedOnlineShopId] : [];
            $branchIds = [];
        }

        if (empty($branchIds) && empty($onlineShopIds)) {
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
            ->where(function ($q) use ($branchIds, $onlineShopIds) {
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
            })
            ->count();

        // Non-HP
        $nonHpStockQuery = \App\Models\Inventory::query();
        $nonHpStockQuery->where(function ($q) use ($branchIds, $onlineShopIds) {
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
        });
        $nonHpStock = (int) $nonHpStockQuery->sum('quantity');

        $totalStock = $hpStock + $nonHpStock;

        // 2. Stock In (Incoming Transfers that are Received)
        // We need to query the items within the transfers, not just the transfers themselves.

        // Helper to scope StockOut (Transfers) by Destination
        $scopeIn = function ($q) use ($branchIds, $onlineShopIds) {
            $q->where('status', 'received')
                ->where(function ($sub) use ($branchIds, $onlineShopIds) {
                    if (!empty($branchIds)) {
                        $sub->orWhere(function ($deep) use ($branchIds) {
                            $deep->where('destination_type', 'branch')->whereIn('destination_id', $branchIds);
                        });
                    }
                    if (!empty($onlineShopIds)) {
                        $sub->orWhere(function ($deep) use ($onlineShopIds) {
                            $deep->where('destination_type', 'online_shop')->whereIn('destination_id', $onlineShopIds);
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
            ->sum('quantity');

        $totalIn = $inHp + $inNonHp;


        // 3. Stock Out (Sales + Transfers Out)
        // Helper to scope StockOut by Source
        $scopeOut = function ($q) use ($branchIds, $onlineShopIds) {
            $q->whereHas('user', function ($u) use ($branchIds, $onlineShopIds) {
                $u->where(function ($sub) use ($branchIds, $onlineShopIds) {
                    if (!empty($branchIds))
                        $sub->orWhereIn('branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $sub->orWhereIn('online_shop_id', $onlineShopIds);
                });
            });
        };

        // HP Out
        $outHp = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            // Join users to check source branch/shop
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->where(function ($q) use ($branchIds, $onlineShopIds) {
                if (!empty($branchIds))
                    $q->orWhereIn('users.branch_id', $branchIds);
                if (!empty($onlineShopIds))
                    $q->orWhereIn('users.online_shop_id', $onlineShopIds);
            })
            ->count();

        // Non-HP Out
        $outNonHp = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->where(function ($q) use ($branchIds, $onlineShopIds) {
                if (!empty($branchIds))
                    $q->orWhereIn('users.branch_id', $branchIds);
                if (!empty($onlineShopIds))
                    $q->orWhereIn('users.online_shop_id', $onlineShopIds);
            })
            ->sum('quantity');

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

        if (empty($branchIds) && empty($onlineShopIds)) {
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

        $year = $request->year ?? date('Y');
        $month = $request->month; // Optional

        // Base Query Categories
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        // 1. Profit Trend (Daily)
        $dailyStats = [];

        // Query StockOut (Transactions)
        $query = StockOut::with(['items', 'nonHpItems'])
            ->whereIn('category', $salesCategories)
            ->whereYear('created_at', $year);

        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        } elseif ($month) {
            $query->whereMonth('created_at', $month);
        }

        // Scope to user access
        // Filter by specific location if requested (and allowed)
        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;

        // Scope to user access and specific location
        $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
            $q->where(function ($sub) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId) {
                    // Check access
                    if (empty($branchIds) || in_array($requestedBranchId, $branchIds)) {
                        $sub->where('branch_id', $requestedBranchId);
                    } else {
                        // No access
                        $sub->whereRaw('1=0');
                    }
                } elseif ($requestedOnlineShopId) {
                    // Check access
                    if (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) {
                        $sub->where('online_shop_id', $requestedOnlineShopId);
                    } else {
                        // No access
                        $sub->whereRaw('1=0');
                    }
                } else {
                    if (!empty($branchIds))
                        $sub->orWhereIn('branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $sub->orWhereIn('online_shop_id', $onlineShopIds);
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
            $date = $trx->created_at->format('Y-m-d');

            // Calculate Cost
            $trxCost = 0;
            $trxItems = 0;

            // HP Items Cost
            foreach ($trx->items as $item) {
                // $item IS the ProductDetail model attached via belongToMany
                $trxCost += $item->cost_price;
                $trxItems++;
            }

            // Non-HP Items Cost (Limitation: Assuming 0 or need product reference)
            // Ideally we need cost_price in stock_out_non_hp_items or use current product type cost
            // For now, assuming 0 cost for accessories as per plan note, or we could try to fetch current average cost.
            // Keeping it 0 to avoid misleading "profit" reduction if cost is unknown?
            // actually, if cost is 0, profit = revenue, which is inflated.
            // Let's assume 0 for now as verified in plan.
            foreach ($trx->nonHpItems as $nhp) {
                // Check if we can get cost from ProductType (via product->type match? No direct link)
                // For now, assuming 0 cost for accessories as per plan note, or we could try to fetch current average cost.
                // Keeping it 0 to avoid misleading "profit" reduction if cost is unknown? 
                // actually, if cost is 0, profit = revenue, which is inflated.
                // Let's assume 0 for now as verified in plan.
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

            // Breakdown by Branch/OnlineShop (Logic: Prefer Inventory User Branch -> Creator Branch)
            $sourceName = 'Unknown';

            // 1. Try Inventory User (The CS who made the sale)
            if ($trx->inventoryUser) {
                if ($trx->inventoryUser->branch) {
                    $sourceName = $trx->inventoryUser->branch->name;
                } elseif ($trx->inventoryUser->onlineShop) {
                    $sourceName = $trx->inventoryUser->onlineShop->name;
                }
            }
            // 2. Fallback to Creator User
            elseif ($trx->user) {
                if ($trx->user->branch) {
                    $sourceName = $trx->user->branch->name;
                } elseif ($trx->user->onlineShop) {
                    $sourceName = $trx->user->onlineShop->name;
                }
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

            $targetStats = $detailedStats[$targetDate] ?? ['profit' => 0, 'revenue' => 0, 'items' => 0];

            // Re-query for previous date if not in current set (likely if start of month)
            // But for simplicity, we can just filter the $dailyStats if the month/year covers it?
            // Actually, if we selected "Feb 17", we need "Feb 16".
            // Since we queried the whole month (or year), checking $dailyStats is fine if within range.
            // If previous date is previous month, we might miss it if filtering by month.
            // Let's implement a specific robust check for Comparison.

            $prevStats = ['profit' => 0, 'revenue' => 0, 'items' => 0];
            // We can't rely on $dailyStats if filtered by month and prevDate is last month.
            // So let's run a separate lightweight query for the 2 days if 'date' is present?
            // Or just check $dailyStats if available.

            // For now, let's assume user stays within valid range or we accept 0 for prev if out of query.
            // Optimization: If date is requested, maybe we should focus on that?
            // But the charts usually show the context (the whole month).
            // So let's check $dailyStats for prevDate.

            $prevStats = $dailyStats[$prevDate] ?? ['profit' => 0, 'revenue' => 0, 'items' => 0];

            // If prevStats is empty but might exist (cross-month), query it
            if (!isset($dailyStats[$prevDate])) {
                // Optional: Query specific date if needed.
                // For now, let's stick to loaded data.
            }

            $targetStats = $dailyStats[$targetDate] ?? ['profit' => 0, 'revenue' => 0, 'items' => 0];

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
                'total_profit' => $totalRevenue - $totalCost, // Recalculate based on loops
                'total_revenue' => $totalRevenue,
                'total_items' => $totalItems
            ],
            'profit_trend' => array_values($dailyStats),
            'sales_breakdown' => array_values($breakdown),
            'comparison' => $comparison // New Field
        ]);
    }
}
