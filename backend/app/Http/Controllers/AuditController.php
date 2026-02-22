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

        // Filter by specific location
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
                        if (!empty($branchIds)) {
                            $sub->orWhereIn('branch_id', $branchIds);
                        }
                        if (!empty($onlineShopIds)) {
                            $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        }
                    }
                });
            });
        };

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        // 1. Daily Sales
        // Load nonHpItems relationship for Product details, but we will use JSON column for price
        $dailySalesQuery = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        $scopeToAccess($dailySalesQuery);

        $dailySales = $dailySalesQuery->latest()->get()->map(function ($trx) {
            $details = [];
            $calculatedTotal = 0;

            // 1. HP Items
            foreach ($trx->items as $item) {
                $price = ($item->selling_price > 0) ? $item->selling_price : ($item->product->price ?? 0);

                $details[] = [
                    'name' => $item->product->name ?? 'Unknown HP',
                    'qty' => 1,
                    'price' => $price,
                    'is_fixed' => true
                ];
                $calculatedTotal += $price;
            }

            // 2. Non-HP Items
            // Priority: Use the JSON column `non_hp_items` which contains the historical `selling_price`
            // Fallback: Use Relationship `nonHpItems` if JSON is empty (legacy data)

            $jsonItems = $trx->non_hp_items; // Accessor for JSON column
            $hasJsonData = is_array($jsonItems) && count($jsonItems) > 0;

            if ($hasJsonData) {
                // Map product IDs to Names using the eager-loaded relationship to avoid N+1 queries
                $productMap = $trx->nonHpItems->pluck('product', 'product_id');
                // Also fetch any prod not in relationship? Unlikely if integrity is kept.
                // But if relationship is missing (e.g. deleted), we might need to fetch. 
                // For now, rely on map.

                foreach ($jsonItems as $itemData) {
                    $pid = $itemData['product_id'] ?? null;
                    $product = $productMap[$pid] ?? null;
                    // Fallback name if product deleted
                    $name = $product ? $product->name : ($itemData['product_name'] ?? 'Item Non-HP');

                    $price = $itemData['selling_price'] ?? 0;
                    $qty = $itemData['quantity'] ?? 1;

                    $details[] = [
                        'name' => $name,
                        'qty' => $qty,
                        'price' => $price,
                        'is_fixed' => true
                    ];
                    $calculatedTotal += ($price * $qty);
                }
            } else {
                // FALLBACK: Use Relation + Base Price (Legacy)
                foreach ($trx->nonHpItems as $nhp) {
                    $basePrice = $nhp->product->price ?? 0;
                    $details[] = [
                        'name' => $nhp->product->name ?? 'Unknown Item',
                        'qty' => $nhp->quantity,
                        'price' => $basePrice,
                        'is_fixed' => true
                    ];
                    $calculatedTotal += ($basePrice * $nhp->quantity);
                }
            }

            // 3. Final Adjustment / Gap Handling
            $remainingBalance = $trx->selling_price - $calculatedTotal;

            if (abs($remainingBalance) > 1) {
                $details[] = [
                    'name' => $remainingBalance > 0 ? 'Biaya Admin / Tambahan' : 'Diskon / Penyesuaian',
                    'qty' => 1,
                    'price' => $remainingBalance
                ];
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

            // Audit score calculation
            $totalQuestions = Question::where('category', $trx->category)->count();
            $yesCount = $trx->auditAnswers->where('answer', true)->count();
            $auditScore = $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : null;

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'order_no' => $trx->receipt_id,
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? $trx->shopee_receiver ?? $trx->giveaway_receiver ?? '-',
                'customer_phone' => $trx->customer_phone ?? $trx->shopee_phone ?? $trx->giveaway_phone ?? '-',
                'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                'qty' => $trx->items->count() + ($trx->non_hp_items ? collect($trx->non_hp_items)->sum('quantity') : $trx->nonHpItems->sum('quantity')),
                'items' => $details,
                'status' => $trx->status === 'received' ? 'Lunas' : 'Pending',
                'payment_method' => $trx->category === 'penjualan_offline' ? 'Offline' : 'Online',
                'cash' => 0,
                'transfer' => 0,
                'debit' => 0,
                'grand_total' => $trx->selling_price,
                'outlet_name' => $outletName,
                'outlet_address' => $outletAddress,
                'audit_score' => $auditScore,
                'audit_answered' => $trx->auditAnswers->count(),
                'audit_total' => $totalQuestions,
                'audit_yes' => $yesCount,
            ];
        });

        // 2. Report per Brand (with scope)
        $brandStats = [];

        // HP
        $hpQuery = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
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
        // Helper to scope StockOut (Transfers) by Destination
        $scopeIn = function ($q) use ($branchIds, $onlineShopIds) {
            $q->where('stock_outs.status', 'received')
                ->whereMonth('stock_outs.created_at', now()->month)
                ->whereYear('stock_outs.created_at', now()->year)
                ->where(function ($sub) use ($branchIds, $onlineShopIds) {
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
        $scopeOut = function ($q) use ($branchIds, $onlineShopIds) {
            $q->whereMonth('stock_outs.created_at', now()->month)
                ->whereYear('stock_outs.created_at', now()->year)
                ->where(function ($sub) use ($branchIds, $onlineShopIds) {
                    if (!empty($branchIds)) {
                        $sub->whereIn('users.branch_id', $branchIds);
                    }
                    if (!empty($onlineShopIds)) {
                        $sub->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                });
        };

        // HP Out
        $outHp = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereMonth('stock_outs.created_at', now()->month)
            ->whereYear('stock_outs.created_at', now()->year)
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
            ->whereMonth('stock_outs.created_at', now()->month)
            ->whereYear('stock_outs.created_at', now()->year)
            ->where(function ($q) use ($branchIds, $onlineShopIds) {
                if (!empty($branchIds))
                    $q->orWhereIn('users.branch_id', $branchIds);
                if (!empty($onlineShopIds))
                    $q->orWhereIn('users.online_shop_id', $onlineShopIds);
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

        // Scope to user access & location filter
        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;

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

            // Breakdown by Branch/OnlineShop
            $sourceName = 'Unknown';

            if ($trx->inventoryUser) {
                if ($trx->inventoryUser->branch) {
                    $sourceName = $trx->inventoryUser->branch->name;
                } elseif ($trx->inventoryUser->onlineShop) {
                    $sourceName = $trx->inventoryUser->onlineShop->name;
                }
            } elseif ($trx->user) {
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

        // Get current questions for this category
        $currentQuestions = Question::where('category', $category)->orderBy('id')->get();
        $currentQuestionIds = $currentQuestions->pluck('id')->toArray();

        // Get existing answers only for questions in THIS category (not profit, etc.)
        // Include orphaned answers (question_id = null) only if they don't belong to other categories
        $allCategoryQuestionIds = Question::where('category', $category)->pluck('id')->toArray();
        $existingAnswers = AuditAnswer::where('stock_out_id', $stockOutId)
            ->where(function ($q) use ($allCategoryQuestionIds) {
                $q->whereIn('question_id', $allCategoryQuestionIds)
                    ->orWhere(function ($q2) use ($allCategoryQuestionIds) {
                        // Include orphaned answers only if question_id is null
                        $q2->whereNull('question_id');
                    });
            })->get();

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

        // 2. Add current questions that haven't been answered yet
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

        if (empty($branchIds) && empty($onlineShopIds)) {
            return response()->json([
                'daily_sales' => [],
                'brand_sales' => [],
                'cs_sales' => []
            ]);
        }

        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

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
                        if (!empty($branchIds)) {
                            $sub->orWhereIn('branch_id', $branchIds);
                        }
                        if (!empty($onlineShopIds)) {
                            $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        }
                    }
                });
            });
        };

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        $dailySalesQuery = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'auditProfit'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        $scopeToAccess($dailySalesQuery);

        $dailySales = $dailySalesQuery->latest()->get()->map(function ($trx) {
            $details = [];
            $calculatedTotal = 0;

            // HP Items
            foreach ($trx->items as $item) {
                $price = ($item->selling_price > 0) ? $item->selling_price : ($item->product->price ?? 0);
                $details[] = [
                    'name' => $item->product->name ?? 'Unknown HP',
                    'qty' => 1,
                    'price' => $price,
                    'is_fixed' => true
                ];
                $calculatedTotal += $price;
            }

            // Non-HP Items
            $jsonItems = $trx->non_hp_items;
            $hasJsonData = is_array($jsonItems) && count($jsonItems) > 0;

            if ($hasJsonData) {
                $productMap = $trx->nonHpItems->pluck('product', 'product_id');
                foreach ($jsonItems as $itemData) {
                    $pid = $itemData['product_id'] ?? null;
                    $product = $productMap[$pid] ?? null;
                    $name = $product ? $product->name : ($itemData['product_name'] ?? 'Item Non-HP');
                    $price = $itemData['selling_price'] ?? 0;
                    $qty = $itemData['quantity'] ?? 1;
                    $details[] = [
                        'name' => $name,
                        'qty' => $qty,
                        'price' => $price,
                        'is_fixed' => true
                    ];
                    $calculatedTotal += ($price * $qty);
                }
            } else {
                foreach ($trx->nonHpItems as $nhp) {
                    $basePrice = $nhp->product->price ?? 0;
                    $details[] = [
                        'name' => $nhp->product->name ?? 'Unknown Item',
                        'qty' => $nhp->quantity,
                        'price' => $basePrice,
                        'is_fixed' => true
                    ];
                    $calculatedTotal += ($basePrice * $nhp->quantity);
                }
            }

            // Gap handling
            $remainingBalance = $trx->selling_price - $calculatedTotal;
            if (abs($remainingBalance) > 1) {
                $details[] = [
                    'name' => $remainingBalance > 0 ? 'Biaya Admin / Tambahan' : 'Diskon / Penyesuaian',
                    'qty' => 1,
                    'price' => $remainingBalance
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

            // Profit calculation
            $hargaJual = (float) ($trx->selling_price ?? 0);
            $savedProfit = $trx->auditProfit;
            $hargaModal = $savedProfit ? (float) $savedProfit->harga_modal : null;
            $defaultHargaModal = $hargaJual > 0 ? round($hargaJual * 0.95) : 0;
            $effectiveHargaModal = $hargaModal ?? $defaultHargaModal;
            $profit = $hargaJual - $effectiveHargaModal;

            // Audit score using 'profit' category
            $totalQuestions = Question::where('category', 'profit')->count();
            $yesCount = $trx->auditAnswers->whereIn(
                'question_id',
                Question::where('category', 'profit')->pluck('id')
            )->where('answer', true)->count();
            $auditScore = $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : null;

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'order_no' => $trx->receipt_id,
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? $trx->shopee_receiver ?? $trx->giveaway_receiver ?? '-',
                'customer_phone' => $trx->customer_phone ?? $trx->shopee_phone ?? $trx->giveaway_phone ?? '-',
                'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
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
            ];
        });

        return response()->json([
            'daily_sales' => $dailySales,
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
            'harga_modal' => 'required|numeric|min:0',
        ]);

        $auditProfit = AuditProfit::updateOrCreate(
            ['stock_out_id' => $stockOutId],
            [
                'harga_modal' => $request->harga_modal,
                'auditor_id' => $user->id,
            ]
        );

        $profit = $stockOut->selling_price - $auditProfit->harga_modal;

        return response()->json([
            'message' => 'Harga modal berhasil disimpan',
            'harga_modal' => $auditProfit->harga_modal,
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

        $existingAnswers = AuditAnswer::where('stock_out_id', $stockOutId)
            ->where(function ($q) use ($currentQuestionIds) {
                $q->whereIn('question_id', $currentQuestionIds)
                    ->orWhereNull('question_id')
                    ->orWhereNotNull('question_content');
            })->get();

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
}

