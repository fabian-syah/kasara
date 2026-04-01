<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;
use App\Models\ProductType;
use App\Models\ProductPrice;
use App\Models\Category;
use App\Models\StockOut;
use App\Models\ProductDetail;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->hasRole('admin_produk') || $user->hasRole('Admin Produk') || $user->hasRole('ADMIN PRODUK')) {
            return $this->getAdminProdukStats();
        }

        if ($user->online_shop_id || $user->hasRole('online_shop') || $user->hasRole('toko_online')) {
            return $this->getOnlineShopStats($user);
        }

        if ($user->branch_id || $user->hasRole('toko_offline') || $user->hasRole('offline_shop') || $user->hasRole('staff_cabang')) {
            return $this->getTokoOfflineStats($user);
        }

        return response()->json([
            'role' => 'general',
            'message' => 'Dashboard standart (static attributes)'
        ]);
    }

    private function getAdminProdukStats()
    {
        $brandCount = Brand::count();
        $typeCount = ProductType::count();
        $priceCount = ProductPrice::count();
        $categoryCount = Category::count();

        $recentTypes = ProductType::with(['brand'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'brand_name' => $type->brand->name ?? '-',
                    'category_name' => $type->category ?? '-',
                    'created_at' => $type->created_at->format('d M Y H:i'),
                ];
            });

        $recentPrices = ProductPrice::with(['productType.brand'])
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(function ($price) {
                return [
                    'id' => $price->id,
                    'name' => ($price->productType->brand->name ?? '') . ' ' . ($price->productType->name ?? ''),
                    'price' => $price->selling_price,
                    'condition' => $price->condition,
                    'updated_at' => $price->updated_at->format('d M Y H:i'),
                ];
            });

        return response()->json([
            'role' => 'admin_produk',
            'stats' => [
                ['id' => 'brands', 'label' => 'Total Brand', 'value' => $brandCount, 'icon' => 'Database', 'color' => 'blue', 'link' => '/brands'],
                ['id' => 'types', 'label' => 'Total Tipe Produk', 'value' => $typeCount, 'icon' => 'Tags', 'color' => 'emerald', 'link' => '/types'],
                ['id' => 'prices', 'label' => 'Data Harga', 'value' => $priceCount, 'icon' => 'DollarSign', 'color' => 'amber', 'link' => '/prices'],
                ['id' => 'categories', 'label' => 'Kategori', 'value' => $categoryCount, 'icon' => 'Box', 'color' => 'violet', 'link' => '/categories']
            ],
            'recent_types' => $recentTypes,
            'recent_prices' => $recentPrices
        ]);
    }

    private function getTokoOfflineStats($user)
    {
        $categories = ['penjualan', 'penjualan_offline', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'];
        return $this->getAggregatedStats($user, $categories, 'toko_offline');
    }

    private function getOnlineShopStats($user)
    {
        $categories = ['shopee', 'orderan_online', 'giveaway'];
        return $this->getAggregatedStats($user, $categories, 'online_shop');
    }

    private function getAggregatedStats($user, $categories, $role)
    {
        $currentReportingDate = StockOut::calculateReportingDate($categories[0] ?? 'penjualan', $user->branch ?: ($user->onlineShop ?: null));

        $todaySalesQuery = StockOut::with(['items.product', 'user', 'inventoryUser'])
            ->whereIn('category', $categories)
            ->where('reporting_date', $currentReportingDate);

        if ($role === 'online_shop' && $user->online_shop_id) {
            $todaySalesQuery->whereHas('user', function ($q) use ($user) {
                $q->where('online_shop_id', $user->online_shop_id);
            });
        } elseif ($role === 'toko_offline' && $user->branch_id) {
            $todaySalesQuery->whereHas('user', function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id);
            });
        } else {
            $todaySalesQuery->where('user_id', $user->id);
        }

        $todaySales = $todaySalesQuery->get();

        $totalRevenue = 0;
        $productsSold = 0;
        $typeSales = [];
        $brandConditionSales = [];
        $csPerformance = [];

        // Pre-fetch Non-HP Product Info
        $nonHpProductIds = [];
        foreach ($todaySales as $sale) {
            if ($sale->non_hp_items) {
                foreach ($sale->non_hp_items as $item) {
                    if (isset($item['product_id']))
                        $nonHpProductIds[] = $item['product_id'];
                }
            }
        }
        $nonHpProducts = empty($nonHpProductIds) ? collect() : Product::whereIn('id', array_unique($nonHpProductIds))->get()->keyBy('id');

        foreach ($todaySales as $sale) {
            $csName = $sale->inventoryUser->name ?? $sale->user->name ?? 'Unknown';
            if (!isset($csPerformance[$csName])) {
                $csPerformance[$csName] = ['name' => $csName, 'hp_count' => 0, 'non_hp_count' => 0, 'total_sales' => 0];
            }

            // HP Items
            foreach ($sale->items as $item) {
                $price = $item->selling_price;
                $totalRevenue += $price;
                $productsSold++;
                $csPerformance[$csName]['hp_count']++;
                $csPerformance[$csName]['total_sales'] += $price;

                // Type Stats
                $typeName = $item->product->name ?? 'Unknown';
                $typeSales[$typeName] = ($typeSales[$typeName] ?? 0) + 1;

                // Brand Condition Stats
                $brand = $item->product->brand ?? 'Unknown';
                $cond = ($item->condition === 'new') ? 'New' : (($item->condition === 'ex_ibox') ? 'Ex iBox' : 'Second');
                $key = "$brand $cond";
                $brandConditionSales[$key] = ($brandConditionSales[$key] ?? 0) + 1;
            }

            // Non-HP Items
            if ($sale->non_hp_items) {
                foreach ($sale->non_hp_items as $item) {
                    $pid = $item['product_id'] ?? null;
                    if ($pid && isset($nonHpProducts[$pid])) {
                        $product = $nonHpProducts[$pid];
                        $qty = (int) ($item['quantity'] ?? 1);
                        $price = (float) ($item['selling_price'] ?? 0) * $qty;

                        $totalRevenue += $price;
                        $productsSold += $qty;
                        $csPerformance[$csName]['non_hp_count'] += $qty;
                        $csPerformance[$csName]['total_sales'] += $price;

                        $typeSales[$product->name] = ($typeSales[$product->name] ?? 0) + $qty;
                        $key = ($product->brand ?? 'Unknown') . " New";
                        $brandConditionSales[$key] = ($brandConditionSales[$key] ?? 0) + $qty;
                    }
                }
            }
        }

        // Stock
        $placementType = ($role === 'online_shop') ? 'online_shop' : 'branch';
        $placementId = ($role === 'online_shop') ? $user->online_shop_id : $user->branch_id;

        $hpStock = ProductDetail::where('placement_type', $placementType)->where('placement_id', $placementId)->where('status', 'available')->count();
        $nonHpStock = Inventory::where('placement_type', $placementType)->where('placement_id', $placementId)->sum('quantity');

        // Recent Trx
        $recentTransactions = $todaySales->take(10)->map(function ($trx) {
            return [
                'id' => $trx->receipt_id,
                'customer' => $trx->shopee_receiver ?? $trx->receiver_name ?? $trx->customer_name ?? 'Guest',
                'total' => $trx->items->sum('selling_price') + (collect($trx->non_hp_items)->sum(fn($i) => ($i['selling_price'] ?? 0) * ($i['quantity'] ?? 1))),
                'time' => $trx->created_at->diffForHumans(),
                'datetime' => $trx->created_at->format('d M H:i'),
                'status' => 'success'
            ];
        });

        // Format and Sort
        $typeSalesData = collect($typeSales)->map(fn($v, $k) => ['name' => $k, 'count' => $v])->sortByDesc('count')->values()->take(5);
        $brandSalesData = collect($brandConditionSales)->map(fn($v, $k) => ['name' => $k, 'count' => $v])->sortByDesc('count')->values();
        $csPerformanceData = collect($csPerformance)->sortByDesc('total_sales')->values();

        return response()->json([
            'role' => $role,
            'stats' => [
                ['id' => 'revenue', 'label' => 'Pendapatan Hari Ini', 'value' => $totalRevenue, 'isCurrency' => true, 'icon' => 'DollarSign', 'color' => 'emerald'],
                ['id' => 'transactions', 'label' => 'Total Transaksi (Hari Ini)', 'value' => $todaySales->count(), 'icon' => 'ShoppingCart', 'color' => 'blue'],
                ['id' => 'sold', 'label' => 'Produk Terjual (Hari Ini)', 'value' => $productsSold, 'icon' => 'Package', 'color' => 'violet'],
                ['id' => 'stock', 'label' => 'Total Stok Fisik', 'value' => $hpStock + $nonHpStock, 'sub' => "HP: $hpStock | Acc: $nonHpStock", 'icon' => 'Box', 'color' => 'amber'],
            ],
            'recentTransactions' => $recentTransactions,
            'typeSales' => $typeSalesData,
            'brandSales' => $brandSalesData,
            'csPerformance' => $csPerformanceData,
            'ranking' => $this->getRankingData($user, $categories)
        ]);
    }

    private function getRankingData($user, $categories)
    {
        // Count units based on user_id (who made the sale) as well for sales leaderboard
        $currentReportingDate = StockOut::calculateReportingDate($categories[0] ?? 'penjualan', $user->branch ?: ($user->onlineShop ?: null));

        $todayRanking = DB::table('stock_outs')
            ->whereIn('category', $categories)
            ->where('reporting_date', $currentReportingDate)
            ->select('user_id', DB::raw('count(*) as total_units'))
            ->groupBy('user_id')
            ->orderByDesc('total_units')
            ->get();

        $globalRanking = [];
        $rank = 1;
        foreach ($todayRanking as $row) {
            $globalRanking[$row->user_id] = $rank++;
        }

        // Include both inventory and sales roles in the leaderboard
        $leaderboardQuery = User::role(['inventory', 'toko_offline'])->select('id', 'name', 'photo_inventory');

        if ($user->online_shop_id) {
            $leaderboardQuery->where('online_shop_id', $user->online_shop_id);
        } elseif ($user->branch_id) {
            $leaderboardQuery->where('branch_id', $user->branch_id);
        }

        $leaderboard = $leaderboardQuery->get()->map(function ($u) use ($globalRanking, $categories, $user) {
            // Count units sold by this user
            $currentReportingDate = StockOut::calculateReportingDate($categories[0] ?? 'penjualan', $user->branch ?: ($user->onlineShop ?: null));
            $units = StockOut::where('user_id', $u->id)->whereIn('category', $categories)->where('reporting_date', $currentReportingDate)->count();
            return [
                'id' => $u->id,
                'name' => $u->name,
                'photo' => $u->photo_inventory ? asset('storage/' . $u->photo_inventory) : null,
                'units' => $units,
                'rank' => $globalRanking[$u->id] ?? '-'
            ];
        })->sortByDesc('units')->values();

        return [
            'my_rank' => $globalRanking[$user->id] ?? '-',
            'leaderboard' => $leaderboard
        ];
    }
}
