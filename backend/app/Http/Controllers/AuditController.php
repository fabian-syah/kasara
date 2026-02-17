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

        $scopeToAccess = function ($query) use ($branchIds, $onlineShopIds) {
            $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds) {
                $q->where(function ($sub) use ($branchIds, $onlineShopIds) {
                    if (!empty($branchIds)) {
                        $sub->orWhereIn('branch_id', $branchIds);
                    }
                    if (!empty($onlineShopIds)) {
                        // Similar logic to UserController: Match online shop if branch is null OR just match online shop?
                        // For sales, we want to capture ALL sales relevant to the placement.
                        // If a user has branch_id but sells for online_shop, we should capture it if we audit online_shop.
                        $sub->orWhereIn('online_shop_id', $onlineShopIds);
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
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? '-',
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
        // Placeholder for Branch Analysis
        return response()->json([
            'message' => 'Analysis data not implemented yet'
        ]);
    }
}
