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
use Carbon\Carbon;

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
            'ranking' => $this->getRankingData($user, $categories),
            'branch_ranking' => $this->getBranchRankingData($user)
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

    private function getBranchRankingData($user)
    {
        // Only for branch or online shop users
        if (!$user->branch_id && !$user->online_shop_id) return null;

        try {
            $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'];
            
            // Use reporting date logic
            $location = $user->branch ?: ($user->onlineShop ?: null);
            $todayDate = StockOut::calculateReportingDate('penjualan', $location);
            $today = Carbon::parse($todayDate);
            
            $yesterdayDate = $today->copy()->subDay()->format('Y-m-d');
            
            // Monthly Ranges
            $thisMonthStart = $today->copy()->startOfMonth()->format('Y-m-d');
            $thisMonthEnd = $today->format('Y-m-d'); // Until today
            
            $lastMonthStart = $today->copy()->subMonth()->startOfMonth()->format('Y-m-d');
            $lastMonthEnd = $today->copy()->subMonth()->endOfMonth()->format('Y-m-d');

            // Fetch names of all active units (Exclude Trial, Testing, Anu accounts)
            $branchesArr = DB::table('branches')->where('is_active', true)->get(['id', 'name']);
            $shopsArr = DB::table('online_shops')->where('is_active', true)->get(['id', 'name']);
            
            $excludeFilter = function($item) {
                $name = strtolower($item->name);
                return !str_contains($name, 'trial') && !str_contains($name, 'testing') && !str_contains($name, 'anu') && !str_contains($name, 'huft');
            };

            $branches = $branchesArr->filter($excludeFilter);
            $shops = $shopsArr->filter($excludeFilter);

            // Reusable ranking function
            $getRankingForRange = function($startDate, $endDate = null) use ($branches, $shops, $salesCategories) {
                $query = DB::table('stock_outs')
                    ->join('users', 'stock_outs.user_id', '=', 'users.id')
                    ->whereIn('stock_outs.category', $salesCategories)
                    ->whereNull('stock_outs.deleted_at');
                
                if ($endDate) {
                    $query->whereBetween('stock_outs.reporting_date', [$startDate, $endDate]);
                } else {
                    $query->where('stock_outs.reporting_date', $startDate);
                }

                $stats = $query->select(
                        'users.branch_id',
                        'users.online_shop_id',
                        DB::raw('SUM(COALESCE(stock_outs.selling_price, 0)) as total_omset')
                    )
                    ->groupBy('users.branch_id', 'users.online_shop_id')
                    ->get();

                $ranks = collect();
                
                foreach ($branches as $b) {
                    $omset = (float) ($stats->where('branch_id', $b->id)->first()->total_omset ?? 0);
                    $ranks->push(['id' => $b->id, 'name' => $b->name, 'type' => 'branch', 'omset' => $omset]);
                }
                
                foreach ($shops as $s) {
                    $omset = (float) ($stats->where('online_shop_id', $s->id)->first()->total_omset ?? 0);
                    $ranks->push(['id' => $s->id, 'name' => $s->name, 'type' => 'online_shop', 'omset' => $omset]);
                }

                return $ranks->sortByDesc('omset')->values()->map(function($item, $idx) {
                    $item['rank'] = $idx + 1;
                    return $item;
                });
            };

            // Calculate All Rankings
            $todayRanking = $getRankingForRange($todayDate);
            $yesterdayRanking = $getRankingForRange($yesterdayDate);
            $thisMonthRanking = $getRankingForRange($thisMonthStart, $thisMonthEnd);
            $lastMonthRanking = $getRankingForRange($lastMonthStart, $lastMonthEnd);

            $getUserRanking = function($start, $end = null) use ($user, $salesCategories) {
                if (!$user->branch_id && !$user->online_shop_id) return collect();

                $query = DB::table('stock_outs')
                    ->join('users', 'stock_outs.user_id', '=', 'users.id')
                    ->whereIn('stock_outs.category', $salesCategories)
                    ->whereNull('stock_outs.deleted_at');

                if ($user->branch_id) {
                    $query->where('users.branch_id', $user->branch_id);
                } else {
                    $query->where('users.online_shop_id', $user->online_shop_id);
                }

                if ($end) {
                    $query->whereBetween('stock_outs.reporting_date', [$start, $end]);
                } else {
                    $query->where('stock_outs.reporting_date', $start);
                }

                return $query->select(
                        'users.id',
                        'users.name',
                        DB::raw('COUNT(stock_outs.id) as units'),
                        DB::raw('SUM(COALESCE(stock_outs.selling_price, 0)) as omset')
                    )
                    ->groupBy('users.id', 'users.name')
                    ->orderByDesc('units')
                    ->orderByDesc('omset')
                    ->get();
            };

            $todayUserRanking = $getUserRanking($todayDate);
            $yesterdayUserRanking = $getUserRanking($yesterdayDate);
            $thisMonthUserRanking = $getUserRanking($thisMonthStart, $thisMonthEnd);
            $lastMonthUserRanking = $getUserRanking($lastMonthStart, $lastMonthEnd);

            // Helper to build podium data relative to current user
            $getPodiumData = function($currentRanking, $previousRanking, $unitType, $unitId) {
                $myIndex = $currentRanking->search(fn($i) => $i['type'] === $unitType && $i['id'] == $unitId);
                if ($myIndex === false) return null;

                $me = $currentRanking[$myIndex];
                
                $findPrevRank = function($item) use ($previousRanking) {
                    if (!$item || !$previousRanking) return '-';
                    $prevItem = $previousRanking->where('type', $item['type'])->where('id', $item['id'])->first();
                    return $prevItem ? $prevItem['rank'] : '-';
                };

                // Elements for podium: Prev, Me, Next
                $podiumWrapped = [
                    'me' => array_merge($me, ['prev_rank' => $findPrevRank($me)]),
                    'left' => $myIndex > 0 ? array_merge($currentRanking[$myIndex - 1], ['prev_rank' => $findPrevRank($currentRanking[$myIndex - 1])]) : null,
                    'right' => $myIndex < $currentRanking->count() - 1 ? array_merge($currentRanking[$myIndex + 1], ['prev_rank' => $findPrevRank($currentRanking[$myIndex + 1])]) : null
                ];

                // Assemble to [Left, Center, Right] - Center is ALWAYS 'me' in this layout or Top 1 if I'm Top 1
                $podium = [];
                if ($myIndex > 0) {
                    $podium[0] = $podiumWrapped['left']; // Actual Rank #X-1
                    $podium[1] = $podiumWrapped['me'];   // My Rank #X
                    $podium[2] = $podiumWrapped['right'];// Actual Rank #X+1
                } else {
                    $podium[0] = null; // No one above me
                    $podium[1] = $podiumWrapped['me'];
                    $podium[2] = $podiumWrapped['right'];
                }

                return [
                    'rank' => $myIndex + 1,
                    'omset' => $me['omset'],
                    'podium' => $podium,
                    'prev_rank' => $findPrevRank($me)
                ];
            };

            $myType = $user->branch_id ? 'branch' : 'online_shop';
            $myId = $user->branch_id ?: $user->online_shop_id;

            $findMyRank = function($ranking) use ($myType, $myId) {
                $idx = $ranking->search(fn($r) => $r['type'] === $myType && $r['id'] == $myId);
                return $idx !== false ? $idx + 1 : '-';
            };

            $findMyUserRankInBranch = function($userRanking, $userId) {
                $idx = $userRanking->search(fn($u) => $u->id == $userId);
                return $idx !== false ? $idx + 1 : '-';
            };

            return [
                'today' => $getPodiumData($todayRanking, $yesterdayRanking, $myType, $myId),
                'today_top3' => [
                   'podium' => $todayRanking->take(3)->values()
                ],
                'yesterday' => $getPodiumData($yesterdayRanking, null, $myType, $myId),
                'this_month' => $getPodiumData($thisMonthRanking, $lastMonthRanking, $myType, $myId),
                'this_month_top3' => [
                   'podium' => $thisMonthRanking->take(3)->values()
                ],
                'last_month' => $getPodiumData($lastMonthRanking, null, $myType, $myId),
                'summary' => [
                    'today_global' => $findMyRank($todayRanking),
                    'yesterday_global' => $findMyRank($yesterdayRanking),
                    'this_month_global' => $findMyRank($thisMonthRanking),
                    'last_month_global' => $findMyRank($lastMonthRanking),
                    'today_local' => $findMyUserRankInBranch($todayUserRanking, $user->id),
                    'yesterday_local' => $findMyUserRankInBranch($yesterdayUserRanking, $user->id),
                    'this_month_local' => $findMyUserRankInBranch($thisMonthUserRanking, $user->id),
                    'last_month_local' => $findMyUserRankInBranch($lastMonthUserRanking, $user->id),
                ],
                'total_competitors' => $todayRanking->count()
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Dashboard Ranking Error: " . $e->getMessage());
            return null;
        }
    }
}
