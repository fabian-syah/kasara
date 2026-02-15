<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;
use App\Models\ProductType;
use App\Models\ProductPrice;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Check for specific role logic
        if ($user->hasRole('admin_produk') || $user->hasRole('Admin Produk') || $user->hasRole('ADMIN PRODUK')) {
            return $this->getAdminProdukStats();
        }

        if ($user->online_shop_id || $user->hasRole('online_shop') || $user->hasRole('toko_online')) {
            return $this->getOnlineShopStats($user);
        }

        if ($user->branch_id || $user->hasRole('toko_offline') || $user->hasRole('offline_shop') || $user->hasRole('staff_cabang')) {
            return $this->getTokoOfflineStats($user);
        }

        // Default stats
        return response()->json([
            'role' => 'general',
            'message' => 'Dashboard standart (static attributes)'
        ]);
    }

    private function getAdminProdukStats()
    {
        // 1. Counts
        $brandCount = Brand::count();
        $typeCount = ProductType::count();
        $priceCount = ProductPrice::count();
        $categoryCount = Category::count();

        // 2. Recent Updates
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
        $categories = ['penjualan_offline'];
        $todaySalesQuery = \App\Models\StockOut::whereIn('category', $categories)
            ->whereDate('created_at', now());

        if ($user->branch_id) {
            $todaySalesQuery->whereHas('user', function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id);
            });
        } else {
            $todaySalesQuery->where('user_id', $user->id);
        }

        $todaySales = $todaySalesQuery->get();
        $totalRevenue = 0;
        $productsSold = 0;

        foreach ($todaySales as $sale) {
            foreach ($sale->items as $item) {
                $totalRevenue += $item->selling_price;
                $productsSold++;
            }
            if ($sale->non_hp_items) {
                foreach ($sale->non_hp_items as $item) {
                    $totalRevenue += ($item['selling_price'] ?? 0) * ($item['quantity'] ?? 1);
                    $productsSold += ($item['quantity'] ?? 1);
                }
            }
        }

        $hpStock = \App\Models\ProductDetail::where('placement_type', 'branch')
            ->where('placement_id', $user->branch_id)
            ->where('status', 'available')
            ->count();

        $nonHpStock = \App\Models\Inventory::where('placement_type', 'branch')
            ->where('placement_id', $user->branch_id)
            ->sum('quantity');

        $recentTransactions = \App\Models\StockOut::with(['items.product', 'user'])
            ->whereIn('category', $categories)
            ->whereHas('user', function ($q) use ($user) {
                if ($user->branch_id) {
                    $q->where('branch_id', $user->branch_id);
                } else {
                    $q->where('id', $user->id);
                }
            })
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($trx) {
                return [
                    'id' => $trx->receipt_id,
                    'customer' => $trx->customer_name ?? 'Guest',
                    'total' => $trx->items->sum('selling_price') + (collect($trx->non_hp_items)->sum(fn($i) => ($i['selling_price'] ?? 0) * ($i['quantity'] ?? 1))),
                    'time' => $trx->created_at->diffForHumans(),
                    'datetime' => $trx->created_at->format('d M H:i'),
                    'status' => 'success'
                ];
            });

        $rankingData = $this->getRankingData($user, $categories);

        return response()->json([
            'role' => 'toko_offline',
            'stats' => [
                ['id' => 'revenue', 'label' => 'Pendapatan Hari Ini', 'value' => $totalRevenue, 'isCurrency' => true, 'icon' => 'DollarSign', 'color' => 'emerald'],
                ['id' => 'transactions', 'label' => 'Total Transaksi', 'value' => $todaySales->count(), 'icon' => 'ShoppingCart', 'color' => 'blue'],
                ['id' => 'sold', 'label' => 'Produk Terjual', 'value' => $productsSold, 'icon' => 'Package', 'color' => 'violet'],
                ['id' => 'stock', 'label' => 'Total Stok Fisik', 'value' => $hpStock + $nonHpStock, 'sub' => "HP: $hpStock | Acc: $nonHpStock", 'icon' => 'Box', 'color' => 'amber'],
            ],
            'recentTransactions' => $recentTransactions,
            'ranking' => $rankingData
        ]);
    }

    private function getRankingData($user, $categories)
    {
        $todayRanking = \DB::table('stock_outs')
            ->whereIn('category', $categories)
            ->whereDate('created_at', now())
            ->whereNotNull('inventory_user_id')
            ->select('inventory_user_id', \DB::raw('count(*) as total_units'))
            ->groupBy('inventory_user_id')
            ->orderByDesc('total_units')
            ->get();

        $globalRanking = [];
        $rank = 1;
        foreach ($todayRanking as $row) {
            $globalRanking[$row->inventory_user_id] = $rank++;
        }

        $leaderboardQuery = \App\Models\User::role('inventory')
            ->select('id', 'name', 'photo_inventory');

        if ($user->online_shop_id) {
            $leaderboardQuery->where('online_shop_id', $user->online_shop_id);
        } elseif ($user->branch_id) {
            $leaderboardQuery->where('branch_id', $user->branch_id);
        }

        $leaderboardUsers = $leaderboardQuery->get();

        $leaderboard = $leaderboardUsers->map(function ($u) use ($globalRanking, $categories) {
            $units = \App\Models\StockOut::where('inventory_user_id', $u->id)
                ->whereIn('category', $categories)
                ->whereDate('created_at', now())
                ->count();

            return [
                'id' => $u->id,
                'name' => $u->name,
                'photo' => $u->photo_inventory ? asset('storage/' . $u->photo_inventory) : null,
                'units' => $units,
                'rank' => $globalRanking[$u->id] ?? '-'
            ];
        })->sortByDesc('units')->values();

        return [
            'my_rank' => '-',
            'leaderboard' => $leaderboard
        ];
    }

    private function getOnlineShopStats($user)
    {
        $categories = ['shopee', 'orderan_online'];
        $todaySalesQuery = \App\Models\StockOut::whereIn('category', $categories)
            ->whereDate('created_at', now());

        if ($user->online_shop_id) {
            $todaySalesQuery->whereHas('user', function ($q) use ($user) {
                $q->where('online_shop_id', $user->online_shop_id);
            });
        } else {
            $todaySalesQuery->where('user_id', $user->id);
        }

        $todaySales = $todaySalesQuery->get();
        $totalRevenue = 0;
        $productsSold = 0;

        foreach ($todaySales as $sale) {
            foreach ($sale->items as $item) {
                $totalRevenue += $item->selling_price;
                $productsSold++;
            }
            if ($sale->non_hp_items) {
                foreach ($sale->non_hp_items as $item) {
                    $totalRevenue += ($item['selling_price'] ?? 0) * ($item['quantity'] ?? 1);
                    $productsSold += ($item['quantity'] ?? 1);
                }
            }
        }

        $hpStock = \App\Models\ProductDetail::where('placement_type', 'online_shop')
            ->where('placement_id', $user->online_shop_id)
            ->where('status', 'available')
            ->count();

        $nonHpStock = \App\Models\Inventory::where('placement_type', 'online_shop')
            ->where('placement_id', $user->online_shop_id)
            ->sum('quantity');

        $recentTransactions = \App\Models\StockOut::with(['items.product', 'user'])
            ->whereIn('category', $categories)
            ->whereHas('user', function ($q) use ($user) {
                if ($user->online_shop_id) {
                    $q->where('online_shop_id', $user->online_shop_id);
                } else {
                    $q->where('id', $user->id);
                }
            })
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($trx) {
                return [
                    'id' => $trx->receipt_id,
                    'customer' => $trx->shopee_receiver ?? $trx->receiver_name ?? 'Guest',
                    'total' => $trx->items->sum('selling_price') + (collect($trx->non_hp_items)->sum(fn($i) => ($i['selling_price'] ?? 0) * ($i['quantity'] ?? 1))),
                    'time' => $trx->created_at->diffForHumans(),
                    'datetime' => $trx->created_at->format('d M H:i'),
                    'status' => 'success'
                ];
            });

        $rankingData = $this->getRankingData($user, $categories);

        return response()->json([
            'role' => 'online_shop',
            'stats' => [
                ['id' => 'revenue', 'label' => 'Pendapatan Hari Ini', 'value' => $totalRevenue, 'isCurrency' => true, 'icon' => 'DollarSign', 'color' => 'emerald'],
                ['id' => 'transactions', 'label' => 'Total Transaksi', 'value' => $todaySales->count(), 'icon' => 'ShoppingCart', 'color' => 'blue'],
                ['id' => 'sold', 'label' => 'Produk Terjual', 'value' => $productsSold, 'icon' => 'Package', 'color' => 'violet'],
                ['id' => 'stock', 'label' => 'Total Stok Fisik', 'value' => $hpStock + $nonHpStock, 'sub' => "HP: $hpStock | Acc: $nonHpStock", 'icon' => 'Box', 'color' => 'amber'],
            ],
            'recentTransactions' => $recentTransactions,
            'ranking' => $rankingData
        ]);
    }
}
