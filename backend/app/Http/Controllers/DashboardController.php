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
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user && ($user->hasRole('admin_produk') || $user->hasRole('Admin Produk') || $user->hasRole('ADMIN PRODUK'))) {
            return $this->getAdminProdukStats();
        }

        if ($user->online_shop_id || $user->hasRole(['online_shop', 'toko_online', 'audit', 'analist']) && $user->getAccessibleOnlineShopIds()) {
            return $this->getOnlineShopStats($user);
        }

        if ($user->branch_id || $user->hasRole(['toko_offline', 'offline_shop', 'staff_cabang', 'audit', 'analist']) && $user->getAccessibleBranchIds()) {
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
        $dashCat = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'angkat_barang', 'refund', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'cancel_penjualan', 'pelunasan_dp', 'dp'];
        return $this->getAggregatedStats($user, $dashCat, 'toko_offline');
    }

    private function getOnlineShopStats($user)
    {
        $categories = ['shopee', 'orderan_online', 'giveaway', 'cancel_penjualan'];
        return $this->getAggregatedStats($user, $categories, 'online_shop');
    }

    private function getAggregatedStats($user, $categories, $role)
    {
        $currentReportingDate = StockOut::calculateReportingDate($categories[0] ?? 'penjualan_store', $user->branch ?: ($user->onlineShop ?: null));
        $normalizedCats = array_unique(array_map(fn($c) => strtolower(str_replace(' ', '_', $c)), $categories));

        $now = Carbon::now();
        $isBeforeCutoff = $now->format('H:i') < '05:00';
        $startTS = ($isBeforeCutoff ? Carbon::yesterday() : Carbon::today())->format('Y-m-d') . ' 05:00:00';
        $endTS = ($isBeforeCutoff ? Carbon::today() : Carbon::tomorrow())->format('Y-m-d') . ' 04:59:59';

        $todaySalesQuery = StockOut::with(['items.product', 'user', 'inventoryUser'])
            ->whereIn(DB::raw("LOWER(REPLACE(category, ' ', '_'))"), $normalizedCats)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($currentReportingDate, $startTS, $endTS) {
                $q->where('reporting_date', $currentReportingDate)
                  ->orWhereBetween('created_at', [$startTS, $endTS]);
            });

        $accessibleBranchIds = $user->getAccessibleBranchIds();
        $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();
        $isRestricted = !$user->hasRole('super_admin') && !$user->hasRole('analist');

        if ($isRestricted) {
            $todaySalesQuery->where(function($q) use ($accessibleBranchIds, $accessibleOnlineShopIds) {
                $q->whereHas('user', function ($qu) use ($accessibleBranchIds, $accessibleOnlineShopIds) {
                    $qu->where(function($sub) use ($accessibleBranchIds, $accessibleOnlineShopIds) {
                        if (!empty($accessibleBranchIds)) $sub->orWhereIn('branch_id', $accessibleBranchIds);
                        if (!empty($accessibleOnlineShopIds)) $sub->orWhereIn('online_shop_id', $accessibleOnlineShopIds);
                    });
                });

                if (!empty($accessibleBranchIds)) $q->orWhereIn('branch_id', $accessibleBranchIds);
                if (!empty($accessibleOnlineShopIds)) $q->orWhereIn('online_shop_id', $accessibleOnlineShopIds);

                if (empty($accessibleBranchIds) && empty($accessibleOnlineShopIds)) $q->whereRaw('1=0');
            });
        }

        $todaySales = $todaySalesQuery->get();

        $receiptIds = $todaySales->pluck('receipt_id')->filter()->unique()->toArray();
        $ttMap = empty($receiptIds) ? collect() : DB::table('tukar_tambahs')
            ->whereIn('receipt_id', $receiptIds)
            ->select('receipt_id', DB::raw('SUM(outgoing_price) as outgoing_price'), DB::raw('SUM(incoming_cost_price) as incoming_cost_price'))
            ->groupBy('receipt_id')
            ->get()
            ->keyBy('receipt_id');

        $dgMap = empty($receiptIds) ? collect() : DB::table('downgrades')
            ->whereIn('receipt_id', $receiptIds)
            ->select('receipt_id', DB::raw('SUM(outgoing_price) as outgoing_price'), DB::raw('SUM(incoming_cost_price) as incoming_cost_price'))
            ->groupBy('receipt_id')
            ->get()
            ->keyBy('receipt_id');

        $totalRevenue = 0;
        $totalNetRevenue = 0;
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
                $csPerformance[$csName] = ['name' => $csName, 'hp_count' => 0, 'non_hp_count' => 0, 'total_sales' => 0, 'net_sales' => 0];
            }

            $notes = strtolower($sale->notes ?? '');
            $sa = strtolower($sale->sales_account ?? '');
            $cat = strtolower(str_replace(' ', '_', $sale->category ?? ''));
            $price = ($cat === 'pelunasan_dp' || $cat === 'dp') ? abs((float)($sale->paid_amount ?? 0)) : abs((float)($sale->selling_price ?? 0));

            $saleType = 'ignored';
            if ($cat === 'tukar_tambah' || str_contains($notes, 'tukar tambah') || str_contains($notes, 'tukar_tambah') || str_contains($sa, 'tukar tambah') || str_contains($sa, 'tukar_tambah')) {
                $saleType = 'tukar_tambah';
            } elseif (in_array($cat, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pelunasan_dp', 'dp'])) {
                $saleType = 'base_sale';
            } elseif (str_contains($notes, 'barang angkat') || str_contains($notes, 'angkat barang') || str_contains($notes, 'angkat_barang') || str_contains($sa, 'barang angkat') || str_contains($sa, 'angkat barang') || str_contains($sa, 'angkat_barang') || $cat === 'angkat_barang') {
                $saleType = 'angkat_barang';
            } elseif (str_contains($notes, 'refund') || str_contains($sa, 'refund') || $cat === 'refund') {
                $saleType = 'refund';
            } elseif (str_contains($notes, 'downgrade') || str_contains($sa, 'downgrade') || $cat === 'downgrade') {
                $saleType = 'downgrade';
            }

            $omsetContribution = 0;
            $netContribution = 0;

            if ($saleType === 'tukar_tambah') {
                $ttRec = $ttMap->get($sale->receipt_id);
                $outVal = $ttRec ? floatval($ttRec->outgoing_price) : 0;
                if ($outVal <= 0) {
                    $outVal = $price;
                }
                $inVal = $ttRec ? floatval($ttRec->incoming_cost_price) : 0;
                
                $omsetContribution = $outVal;
                $netContribution = $outVal - $inVal;
            } elseif ($saleType === 'base_sale') {
                $omsetContribution = $price;
                $netContribution = $price;
            } elseif ($saleType === 'angkat_barang') {
                $omsetContribution = 0;
                $netContribution = -$price;
            } elseif ($saleType === 'refund') {
                $omsetContribution = 0;
                $netContribution = -$price;
            } elseif ($saleType === 'downgrade') {
                $dgRec = $dgMap->get($sale->receipt_id);
                $outDg = $dgRec ? floatval($dgRec->outgoing_price) : 0;
                $inDg = $dgRec ? floatval($dgRec->incoming_cost_price) : 0;
                
                if ($outDg > 0 || $inDg > 0) {
                    $omsetContribution = $outDg;
                    $netContribution = $outDg - $inDg;
                } else {
                    $omsetContribution = 0;
                    $netContribution = -$price;
                }
            }

            $totalRevenue += $omsetContribution;
            $totalNetRevenue += $netContribution;
            $csPerformance[$csName]['total_sales'] += $omsetContribution;
            $csPerformance[$csName]['net_sales'] += $netContribution;

            // HP Items
            foreach ($sale->items as $item) {
                $productsSold++;
                $csPerformance[$csName]['hp_count']++;

                // Type Stats
                $typeName = $item->product?->name ?? 'Unknown';
                $typeSales[$typeName] = ($typeSales[$typeName] ?? 0) + 1;

                // Brand Condition Stats
                $brand = $item->product?->brand ?? 'Unknown';
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
                        if (strtolower($product->category) === 'non_hp') {
                            $qty = (int) ($item['quantity'] ?? 1);
                            $productsSold += $qty;
                            $csPerformance[$csName]['non_hp_count'] += $qty;

                            $typeSales[$product->name] = ($typeSales[$product->name] ?? 0) + $qty;
                            $key = ($product->brand ?? 'Unknown') . " New";
                            $brandConditionSales[$key] = ($brandConditionSales[$key] ?? 0) + $qty;
                        }
                    }
                }
            }
        }

        // Stock
        $placementType = ($role === 'online_shop') ? 'online_shop' : 'branch';
        $placementId = ($role === 'online_shop') ? $user->online_shop_id : $user->branch_id;

        $hpStock = ProductDetail::where('placement_type', $placementType)->where('placement_id', $placementId)->where('status', 'available')->count();
        $nonHpStock = Inventory::where('placement_type', $placementType)->where('placement_id', $placementId)->sum('quantity');

        $recentTransactions = $todaySales->take(10)->map(function ($trx) {
            return [
                'id' => $trx->receipt_id,
                'customer' => $trx->shopee_receiver ?? $trx->receiver_name ?? $trx->customer_name ?? 'Guest',
                'total' => ($trx->category === 'pelunasan_dp' || $trx->category === 'dp') ? abs((float)($trx->paid_amount ?? 0)) : abs((float)($trx->selling_price ?? 0)),
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
                ['id' => 'revenue', 'label' => 'Total Omset Hari Ini', 'value' => $totalRevenue, 'isCurrency' => true, 'icon' => 'DollarSign', 'color' => 'emerald'],
                ['id' => 'net_revenue', 'label' => 'Omset Bersih Hari Ini', 'value' => $totalNetRevenue, 'isCurrency' => true, 'icon' => 'TrendingUp', 'color' => 'teal'],
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
        $currentReportingDate = StockOut::calculateReportingDate($categories[0] ?? 'penjualan_store', $user->branch ?: ($user->onlineShop ?: null));

        $now = Carbon::now();
        $isBeforeCutoff = $now->format('H:i') < '05:00';
        $startTS = ($isBeforeCutoff ? Carbon::yesterday() : Carbon::today())->format('Y-m-d') . ' 05:00:00';
        $endTS = ($isBeforeCutoff ? Carbon::today() : Carbon::tomorrow())->format('Y-m-d') . ' 04:59:59';

        $rankReceiptIds = DB::table('stock_outs')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($currentReportingDate, $startTS, $endTS) {
                $q->where('reporting_date', $currentReportingDate)
                  ->orWhereBetween('created_at', [$startTS, $endTS]);
            })
            ->pluck('receipt_id')->filter()->toArray();
            
        $rankTTMap = empty($rankReceiptIds) ? collect() : \App\Models\TukarTambah::whereIn('receipt_id', $rankReceiptIds)
            ->select('receipt_id', DB::raw('SUM(outgoing_price) as outgoing_price'), DB::raw('SUM(incoming_cost_price) as incoming_cost_price'))
            ->groupBy('receipt_id')
            ->get()
            ->keyBy('receipt_id');

        $todayRankingQuery = DB::table('stock_outs')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($currentReportingDate, $startTS, $endTS) {
                $q->where('reporting_date', $currentReportingDate)
                  ->orWhereBetween('created_at', [$startTS, $endTS]);
            })
            ->select('user_id', DB::raw("SUM(
                CASE 
                    WHEN LOWER(REPLACE(category, ' ', '_')) IN ('shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'tukar_tambah', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'pelunasan_dp', 'dp')
                    THEN 1
                    ELSE 0
                END
                -
                CASE 
                    WHEN LOWER(REPLACE(category, ' ', '_')) IN ('refund', 'angkat_barang', 'downgrade') 
                         OR LOWER(notes) LIKE '%refund%' OR LOWER(sales_account) LIKE '%refund%'
                         OR LOWER(notes) LIKE '%barang angkat%' OR LOWER(notes) LIKE '%angkat barang%' OR LOWER(notes) LIKE '%angkat_barang%' OR LOWER(sales_account) LIKE '%barang angkat%' OR LOWER(sales_account) LIKE '%angkat barang%' OR LOWER(sales_account) LIKE '%angkat_barang%'
                         OR LOWER(notes) LIKE '%downgrade%' OR LOWER(sales_account) LIKE '%downgrade%'
                    THEN 1
                    ELSE 0
                END
            ) as total_units"))
            ->groupBy('user_id')
            ->orderByDesc('total_units')
            ->get();

        $globalRanking = [];
        $rank = 1;
        foreach ($todayRankingQuery as $row) {
            $globalRanking[$row->user_id] = $rank++;
        }

        // Include both inventory and sales roles in the leaderboard
        $leaderboardQuery = User::role(['inventory', 'toko_offline'])->select('id', 'name', 'photo_inventory');

        $accessibleBranchIds = $user->getAccessibleBranchIds();
        $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();
        $isRestricted = !$user->hasRole('super_admin') && !$user->hasRole('analist');

        if ($isRestricted) {
            $leaderboardQuery->where(function($q) use ($accessibleBranchIds, $accessibleOnlineShopIds) {
                if (!empty($accessibleBranchIds)) $q->orWhereIn('branch_id', $accessibleBranchIds);
                if (!empty($accessibleOnlineShopIds)) $q->orWhereIn('online_shop_id', $accessibleOnlineShopIds);
                if (empty($accessibleBranchIds) && empty($accessibleOnlineShopIds)) $q->whereRaw('1=0');
            });
        } elseif ($user->hasRole('analist') && !$user->hasRole('super_admin')) {
            $excludedKeywords = config('kasara.excluded_keywords');
            if (!is_array($excludedKeywords)) $excludedKeywords = [];
            if (!empty($excludedKeywords)) {
                $leaderboardQuery->where(function($q) use ($excludedKeywords) {
                    $q->whereDoesntHave('branch', function($bq) use ($excludedKeywords) {
                        $bq->where(function($nq) use ($excludedKeywords) {
                            foreach ($excludedKeywords as $kw) $nq->orWhere('name', 'ilike', "%$kw%");
                        });
                    })->whereDoesntHave('onlineShop', function($sq) use ($excludedKeywords) {
                        $sq->where(function($nq) use ($excludedKeywords) {
                            foreach ($excludedKeywords as $kw) $nq->orWhere('name', 'ilike', "%$kw%");
                        });
                    })->whereDoesntHave('warehouse', function($wq) use ($excludedKeywords) {
                        $wq->where(function($nq) use ($excludedKeywords) {
                            foreach ($excludedKeywords as $kw) $nq->orWhere('name', 'ilike', "%$kw%");
                        });
                    });
                });
            }
        }

        $leaderboard = $leaderboardQuery->get()->map(function ($u) use ($globalRanking, $categories, $user, $rankTTMap, $currentReportingDate, $startTS, $endTS) {
            // Count units sold by this user
            $units = StockOut::where('user_id', $u->id)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($currentReportingDate, $startTS, $endTS) {
                    $q->where('reporting_date', $currentReportingDate)
                      ->orWhereBetween('created_at', [$startTS, $endTS]);
                })
                 ->select(DB::raw("SUM(
                    CASE 
                        WHEN LOWER(REPLACE(category, ' ', '_')) IN ('shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'tukar_tambah', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'pelunasan_dp', 'dp')
                        THEN 1
                        ELSE 0
                    END
                    -
                    CASE 
                        WHEN LOWER(REPLACE(category, ' ', '_')) IN ('refund', 'angkat_barang', 'downgrade') 
                             OR LOWER(notes) LIKE '%refund%' OR LOWER(sales_account) LIKE '%refund%'
                             OR LOWER(notes) LIKE '%barang angkat%' OR LOWER(notes) LIKE '%angkat barang%' OR LOWER(notes) LIKE '%angkat_barang%' OR LOWER(sales_account) LIKE '%barang angkat%' OR LOWER(sales_account) LIKE '%angkat barang%' OR LOWER(sales_account) LIKE '%angkat_barang%'
                             OR LOWER(notes) LIKE '%downgrade%' OR LOWER(sales_account) LIKE '%downgrade%'
                        THEN 1
                        ELSE 0
                    END
                ) as net_units"))
                ->first()->net_units ?? 0;
            // Calculate omset and omset_bersih for each user
            $sales = StockOut::where('user_id', $u->id)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($currentReportingDate, $startTS, $endTS) {
                    $q->where('reporting_date', $currentReportingDate)
                      ->orWhereBetween('created_at', [$startTS, $endTS]);
                })
                ->get();

            $omset = 0;
            $omsetBersih = 0;

            foreach ($sales as $sale) {
                $origCat = strtolower($sale->category ?? '');
                $notes = strtolower($sale->notes ?? '');
                $sa = strtolower($sale->sales_account ?? '');
                $cat = strtolower($sale->category ?? '');

                if (in_array($origCat, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'tukar_tambah', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pelunasan_dp', 'dp'])) {
                    if (str_contains($notes, 'tukar tambah') || str_contains($notes, 'tukar_tambah') || str_contains($sa, 'tukar tambah') || str_contains($sa, 'tukar_tambah')) {
                        $cat = 'tukar_tambah';
                    }
                } else {
                    if (str_contains($notes, 'barang angkat') || str_contains($notes, 'angkat barang') || str_contains($notes, 'angkat_barang') || str_contains($sa, 'barang angkat') || str_contains($sa, 'angkat barang') || str_contains($sa, 'angkat_barang')) {
                        $cat = 'angkat_barang';
                    } elseif (str_contains($notes, 'refund') || str_contains($sa, 'refund')) {
                        $cat = 'refund';
                    } elseif (str_contains($notes, 'downgrade') || str_contains($sa, 'downgrade')) {
                        $cat = 'downgrade';
                    } elseif (str_contains($notes, 'tukar tambah') || str_contains($notes, 'tukar_tambah') || str_contains($sa, 'tukar tambah') || str_contains($sa, 'tukar_tambah')) {
                        $cat = 'tukar_tambah';
                    }
                }

                $price = ($cat === 'pelunasan_dp' || $cat === 'dp') ? abs((float)($sale->paid_amount ?? 0)) : abs((float)($sale->selling_price ?? 0));

                $isBaseSale = in_array($cat, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pelunasan_dp', 'dp']);
                $isTradeIn = ($cat === 'tukar_tambah');
                $isDeduction = in_array($cat, ['refund', 'angkat_barang', 'downgrade']);

                if ($isBaseSale) {
                    $omset += $price;
                    $omsetBersih += $price;
                } elseif ($isTradeIn) {
                    $ttRec = $rankTTMap->get($sale->receipt_id);
                    $outVal = $ttRec ? (float)$ttRec->outgoing_price : 0;
                    if ($outVal <= 0) {
                        $outVal = $price;
                    }
                    $inVal = $ttRec ? (float)$ttRec->incoming_cost_price : 0;
                    
                    $omset += $outVal;
                    $omsetBersih += ($outVal - $inVal);
                } elseif ($isDeduction) {
                    if ($cat === 'downgrade') {
                        $dgRec = DB::table('downgrades')->where('receipt_id', $sale->receipt_id)->first();
                        $outDg = $dgRec ? (float)$dgRec->outgoing_price : 0;
                        $inDg = $dgRec ? (float)$dgRec->incoming_cost_price : 0;
                        
                        if ($outDg > 0 || $inDg > 0) {
                            $omset += $outDg;
                            $omsetBersih += ($outDg - $inDg);
                        } else {
                            $omsetBersih -= $price;
                        }
                    } else {
                        $omsetBersih -= $price;
                    }
                }
            }

            return [
                'id' => $u->id,
                'name' => $u->name,
                'photo' => $u->photo_inventory ? asset('storage/' . $u->photo_inventory) : null,
                'units' => $units,
                'omset' => $omset,
                'omset_bersih' => $omsetBersih,
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
        if (!$user->branch_id && !$user->online_shop_id)
            return null;

        try {
            $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_tambah', 'downgrade', 'refund', 'angkat_barang', 'sale', 'pos', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'cancel_penjualan', 'pelunasan_dp', 'dp'];

            // Use reporting date logic
            $location = $user->branch ?: ($user->onlineShop ?: null);
            $todayDate = StockOut::calculateReportingDate('penjualan_store', $location);
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

            $excludeFilter = function ($item) {
                $name = strtolower($item->name);
                $excludedKeywords = config('kasara.excluded_keywords') ?: [];
                foreach ($excludedKeywords as $kw) {
                    if (str_contains($name, $kw)) return false;
                }
                return true;
            };

            $branches = $branchesArr->filter($excludeFilter);
            $shops = $shopsArr->filter($excludeFilter);

            $normalizedSalesCategories = array_unique(array_map(fn($c) => strtolower(str_replace(' ', '_', $c)), $salesCategories));

            // Reusable ranking function
            $getRankingForRange = function ($startDate, $endDate = null) use ($branches, $shops, $normalizedSalesCategories) {
                $query = DB::table('stock_outs')
                    ->join('users', 'stock_outs.user_id', '=', 'users.id')
                    ->leftJoin('users as inventory_users', 'stock_outs.inventory_user_id', '=', 'inventory_users.id')
                    ->whereIn(DB::raw("LOWER(REPLACE(stock_outs.category, ' ', '_'))"), $normalizedSalesCategories)
                    ->whereNull('stock_outs.deleted_at');

                $actualEndDate = $endDate ?: $startDate;
                
                // Replicate inclusive date matching from AuditController
                $startTS = $startDate . ' 05:00:00';
                $endTS = date('Y-m-d', strtotime($actualEndDate . ' +1 day')) . ' 04:59:59';
                
                $query->where(function ($q) use ($startDate, $actualEndDate, $startTS, $endTS) {
                    $q->whereBetween('stock_outs.reporting_date', [$startDate, $actualEndDate])
                      ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
                });

                $stats = $query->select(
                    DB::raw('COALESCE(stock_outs.branch_id, users.branch_id) as branch_id'),
                    DB::raw('COALESCE(stock_outs.online_shop_id, users.online_shop_id) as online_shop_id'),
                    DB::raw("SUM(
                        CASE 
                            WHEN (LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'tukar_tambah' OR LOWER(stock_outs.notes) LIKE '%tukar tambah%' OR LOWER(stock_outs.notes) LIKE '%tukar_tambah%' OR LOWER(stock_outs.sales_account) LIKE '%tukar tambah%' OR LOWER(stock_outs.sales_account) LIKE '%tukar_tambah%')
                            THEN GREATEST(0, COALESCE((SELECT SUM(tt.outgoing_price) FROM tukar_tambahs tt WHERE tt.receipt_id = stock_outs.receipt_id), ABS(COALESCE(stock_outs.selling_price, 0))))
                            WHEN LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pelunasan_dp', 'dp')
                            THEN GREATEST(0, CASE WHEN LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('pelunasan_dp', 'dp') THEN ABS(COALESCE(stock_outs.paid_amount, 0)) ELSE ABS(COALESCE(stock_outs.selling_price, 0)) END)
                            WHEN (LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'downgrade' OR LOWER(stock_outs.notes) LIKE '%downgrade%' OR LOWER(stock_outs.sales_account) LIKE '%downgrade%')
                            THEN COALESCE((SELECT SUM(dg.outgoing_price) FROM downgrades dg WHERE dg.receipt_id = stock_outs.receipt_id), 0)
                            ELSE 0
                        END
                    ) as total_omset"),
                    DB::raw("SUM(
                        CASE 
                            WHEN (LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'tukar_tambah' OR LOWER(stock_outs.notes) LIKE '%tukar tambah%' OR LOWER(stock_outs.notes) LIKE '%tukar_tambah%' OR LOWER(stock_outs.sales_account) LIKE '%tukar tambah%' OR LOWER(stock_outs.sales_account) LIKE '%tukar_tambah%')
                            THEN COALESCE(
                                (SELECT SUM(COALESCE(tt.outgoing_price, 0) - COALESCE(tt.incoming_cost_price, 0)) FROM tukar_tambahs tt WHERE tt.receipt_id = stock_outs.receipt_id), 
                                ABS(COALESCE(stock_outs.selling_price, 0))
                            )
                            WHEN LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pelunasan_dp', 'dp')
                            THEN GREATEST(0, CASE WHEN LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('pelunasan_dp', 'dp') THEN ABS(COALESCE(stock_outs.paid_amount, 0)) ELSE ABS(COALESCE(stock_outs.selling_price, 0)) END)
                            WHEN (LOWER(stock_outs.notes) LIKE '%barang angkat%' OR LOWER(stock_outs.notes) LIKE '%angkat barang%' OR LOWER(stock_outs.notes) LIKE '%angkat_barang%' OR LOWER(stock_outs.sales_account) LIKE '%barang angkat%' OR LOWER(stock_outs.sales_account) LIKE '%angkat barang%' OR LOWER(stock_outs.sales_account) LIKE '%angkat_barang%' OR LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'angkat_barang')
                            THEN -ABS(COALESCE(stock_outs.selling_price, 0))
                            WHEN (LOWER(stock_outs.notes) LIKE '%refund%' OR LOWER(stock_outs.sales_account) LIKE '%refund%' OR LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'refund')
                            THEN -ABS(COALESCE(stock_outs.selling_price, 0))
                            WHEN (LOWER(stock_outs.notes) LIKE '%downgrade%' OR LOWER(stock_outs.sales_account) LIKE '%downgrade%' OR LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'downgrade')
                            THEN COALESCE((SELECT SUM(COALESCE(dg.outgoing_price, 0) - COALESCE(dg.incoming_cost_price, 0)) FROM downgrades dg WHERE dg.receipt_id = stock_outs.receipt_id), -ABS(COALESCE(stock_outs.selling_price, 0)))
                            ELSE 0
                        END
                    ) as omset_bersih")
                )
                    ->groupBy(DB::raw('COALESCE(stock_outs.branch_id, users.branch_id)'), DB::raw('COALESCE(stock_outs.online_shop_id, users.online_shop_id)'))
                    ->get();

                $ranks = collect();

                foreach ($branches as $b) {
                    $omset = (float) ($stats->where('branch_id', $b->id)->sum('total_omset') ?? 0);
                    $omsetBersih = (float) ($stats->where('branch_id', $b->id)->sum('omset_bersih') ?? 0);
                    $ranks->push(['id' => $b->id, 'name' => $b->name, 'type' => 'branch', 'omset' => $omset, 'omset_bersih' => $omsetBersih]);
                }

                foreach ($shops as $s) {
                    $omset = (float) ($stats->where('online_shop_id', $s->id)->sum('total_omset') ?? 0);
                    $omsetBersih = (float) ($stats->where('online_shop_id', $s->id)->sum('omset_bersih') ?? 0);
                    $ranks->push(['id' => $s->id, 'name' => $s->name, 'type' => 'online_shop', 'omset' => $omset, 'omset_bersih' => $omsetBersih]);
                }

                return $ranks->sortByDesc('omset')->values()->map(function ($item, $idx) {
                    $item['rank'] = $idx + 1;
                    return $item;
                });
            };

            // Calculate All Rankings
            $todayRanking = $getRankingForRange($todayDate);
            $yesterdayRanking = $getRankingForRange($yesterdayDate);
            $thisMonthRanking = $getRankingForRange($thisMonthStart, $thisMonthEnd);
            $lastMonthRanking = $getRankingForRange($lastMonthStart, $lastMonthEnd);

            $getUserRanking = function ($start, $end = null) use ($user, $normalizedSalesCategories) {
                if (!$user->branch_id && !$user->online_shop_id)
                    return collect();

                $query = DB::table('stock_outs')
                    ->join('users', 'stock_outs.user_id', '=', 'users.id')
                    ->whereIn(DB::raw("LOWER(REPLACE(stock_outs.category, ' ', '_'))"), $normalizedSalesCategories)
                    ->whereNull('stock_outs.deleted_at');

                $query->where(function ($q) use ($user) {
                    if ($user->branch_id) {
                        $q->where('stock_outs.branch_id', $user->branch_id)
                          ->orWhere('users.branch_id', $user->branch_id);
                    } elseif ($user->online_shop_id) {
                        $q->where('stock_outs.online_shop_id', $user->online_shop_id)
                          ->orWhere('users.online_shop_id', $user->online_shop_id);
                    }
                });

                $actualEnd = $end ?: $start;
                
                // Replicate inclusive date matching from AuditController
                $startTS = $start . ' 05:00:00';
                $endTS = date('Y-m-d', strtotime($actualEnd . ' + 1 day')) . ' 04:59:59';
                
                $query->where(function ($q) use ($start, $actualEnd, $startTS, $endTS) {
                    $q->whereBetween('stock_outs.reporting_date', [$start, $actualEnd])
                      ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
                });

                return $query->select(
                    'users.id',
                    'users.name',
                    DB::raw("COUNT(CASE WHEN LOWER(REPLACE(stock_outs.category, ' ', '_')) NOT IN ('refund', 'angkat_barang', 'downgrade') THEN stock_outs.id END) as units"),
                    DB::raw("SUM(
                        CASE 
                            WHEN (LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'tukar_tambah' OR LOWER(stock_outs.notes) LIKE '%tukar tambah%' OR LOWER(stock_outs.notes) LIKE '%tukar_tambah%' OR LOWER(stock_outs.sales_account) LIKE '%tukar tambah%' OR LOWER(stock_outs.sales_account) LIKE '%tukar_tambah%')
                            THEN GREATEST(0, COALESCE((SELECT SUM(tt.outgoing_price) FROM tukar_tambahs tt WHERE tt.receipt_id = stock_outs.receipt_id), ABS(COALESCE(stock_outs.selling_price, 0))))
                            WHEN LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pelunasan_dp', 'dp')
                            THEN GREATEST(0, CASE WHEN LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('pelunasan_dp', 'dp') THEN ABS(COALESCE(stock_outs.paid_amount, 0)) ELSE ABS(COALESCE(stock_outs.selling_price, 0)) END)
                            WHEN (LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'downgrade' OR LOWER(stock_outs.notes) LIKE '%downgrade%' OR LOWER(stock_outs.sales_account) LIKE '%downgrade%')
                            THEN COALESCE((SELECT SUM(dg.outgoing_price) FROM downgrades dg WHERE dg.receipt_id = stock_outs.receipt_id), 0)
                            ELSE 0
                        END
                    ) as omset"),
                    DB::raw("SUM(
                        CASE 
                            WHEN (LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'tukar_tambah' OR LOWER(stock_outs.notes) LIKE '%tukar tambah%' OR LOWER(stock_outs.notes) LIKE '%tukar_tambah%' OR LOWER(stock_outs.sales_account) LIKE '%tukar tambah%' OR LOWER(stock_outs.sales_account) LIKE '%tukar_tambah%')
                            THEN COALESCE((SELECT SUM(COALESCE(tt.outgoing_price, 0) - COALESCE(tt.incoming_cost_price, 0)) FROM tukar_tambahs tt WHERE tt.receipt_id = stock_outs.receipt_id), ABS(COALESCE(stock_outs.selling_price, 0)))
                            WHEN LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pelunasan_dp', 'dp')
                            THEN GREATEST(0, CASE WHEN LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('pelunasan_dp', 'dp') THEN ABS(COALESCE(stock_outs.paid_amount, 0)) ELSE ABS(COALESCE(stock_outs.selling_price, 0)) END)
                            WHEN (LOWER(stock_outs.notes) LIKE '%barang angkat%' OR LOWER(stock_outs.notes) LIKE '%angkat barang%' OR LOWER(stock_outs.notes) LIKE '%angkat_barang%' OR LOWER(stock_outs.sales_account) LIKE '%barang angkat%' OR LOWER(stock_outs.sales_account) LIKE '%angkat barang%' OR LOWER(stock_outs.sales_account) LIKE '%angkat_barang%' OR LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'angkat_barang')
                            THEN -ABS(COALESCE(stock_outs.selling_price, 0))
                            WHEN (LOWER(stock_outs.notes) LIKE '%refund%' OR LOWER(stock_outs.sales_account) LIKE '%refund%' OR LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'refund')
                            THEN -ABS(COALESCE(stock_outs.selling_price, 0))
                            WHEN (LOWER(stock_outs.notes) LIKE '%downgrade%' OR LOWER(stock_outs.sales_account) LIKE '%downgrade%' OR LOWER(REPLACE(stock_outs.category, ' ', '_')) = 'downgrade')
                            THEN COALESCE((SELECT SUM(COALESCE(dg.outgoing_price, 0) - COALESCE(dg.incoming_cost_price, 0)) FROM downgrades dg WHERE dg.receipt_id = stock_outs.receipt_id), -ABS(COALESCE(stock_outs.selling_price, 0)))
                            ELSE 0
                        END
                    ) as omset_bersih")
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
            $getPodiumData = function ($currentRanking, $previousRanking, $unitType, $unitId) {
                if (!$currentRanking || $currentRanking->isEmpty()) return null;

                $myIndex = $currentRanking->search(fn($i) => $i['type'] === $unitType && $i['id'] == $unitId);
                
                // Centering logic
                if ($myIndex === false) {
                    $slice = $currentRanking->take(3);
                } else {
                    $start = $myIndex - 1;
                    
                    // Adjust if at the beginning
                    if ($start < 0) $start = 0;
                    
                    // NEW: Adjust if at the end to always capture 3 items
                    if ($myIndex == $currentRanking->count() - 1 && $currentRanking->count() >= 3) {
                        $start = $myIndex - 2;
                    }
                    
                    $slice = $currentRanking->slice($start, 3);
                }

                $items = $slice->values()->map(function ($item) use ($unitType, $unitId, $previousRanking) {
                    $item['is_me'] = $item['type'] === $unitType && $item['id'] == $unitId;
                    $prevItem = $previousRanking ? $previousRanking->where('type', $item['type'])->where('id', $item['id'])->first() : null;
                    $item['prev_rank'] = $prevItem ? $prevItem['rank'] : '-';
                    return $item;
                });

                // Arrange to [Left (Idx 0), Center (Idx 1), Right (Idx 2)]
                $podium = [null, null, null];
                
                // Find 'me' object in the slice
                $meObjInSlice = $items->first(fn($it) => $it['is_me']);
                $meRank = $meObjInSlice ? $meObjInSlice['rank'] : null;

                if ($meRank === 1) {
                    // Winner: [Rank 2, Rank 1, Rank 3]
                    $podium[1] = $items->first(fn($it) => $it['rank'] == 1);
                    $podium[0] = $items->first(fn($it) => $it['rank'] == 2);
                    $podium[2] = $items->first(fn($it) => $it['rank'] == 3);
                } else if ($meRank !== null && $meRank !== '-') {
                    // Normal or Bottom: [Rank-1, Rank, Rank+1]
                    $podium[1] = $items->first(fn($it) => $it['rank'] == $meRank);
                    $podium[0] = $items->first(fn($it) => $it['rank'] == $meRank - 1);
                    $podium[2] = $items->first(fn($it) => $it['rank'] == $meRank + 1);
                } else {
                    // Fallback to absolute Top 3
                    $podium[1] = $items->get(0);
                    $podium[0] = $items->get(1);
                    $podium[2] = $items->get(2);
                }

                // Final cleanup: ensure no nulls break frontend
                $podium[0] = $podium[0] ?? ['name' => '-', 'omset' => 0, 'rank' => '-', 'is_me' => false];
                $podium[1] = $podium[1] ?? ['name' => '-', 'omset' => 0, 'rank' => '-', 'is_me' => false];
                $podium[2] = $podium[2] ?? ['name' => '-', 'omset' => 0, 'rank' => '-', 'is_me' => false];

                $findPrevRank = function ($item) use ($previousRanking) {
                    if (!$item || !$previousRanking) return '-';
                    $prevItem = $previousRanking->where('type', $item['type'])->where('id', $item['id'])->first();
                    return $prevItem ? $prevItem['rank'] : '-';
                };

                return [
                    'rank' => $myIndex !== false ? $myIndex + 1 : '-',
                    'omset' => $myIndex !== false ? $currentRanking[$myIndex]['omset'] : 0,
                    'podium' => $podium,
                    'prev_rank' => $myIndex !== false ? $findPrevRank($currentRanking[$myIndex]) : '-'
                ];
            };

            $accessibleBranchIds = $user->getAccessibleBranchIds();
            $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();
            
            // Only show rankings for locations this user has access to, or all if unrestricted
            $restrictRanks = function($ranking) use ($user, $accessibleBranchIds, $accessibleOnlineShopIds) {
                if ($user->hasRole('super_admin') || $user->hasRole('analist')) return $ranking;
                return $ranking->filter(function($item) use ($accessibleBranchIds, $accessibleOnlineShopIds) {
                    if ($item['type'] === 'branch') return in_array($item['id'], $accessibleBranchIds);
                    return in_array($item['id'], $accessibleOnlineShopIds);
                })->values();
            };

            $myType = $user->branch_id ? 'branch' : 'online_shop';
            $myId = $user->branch_id ?: $user->online_shop_id;

            $findMyRank = function ($ranking) use ($myType, $myId) {
                $idx = $ranking->search(fn($r) => $r['type'] === $myType && $r['id'] == $myId);
                return $idx !== false ? $idx + 1 : '-';
            };

            $findMyUserRankInBranch = function ($userRanking, $userId) {
                $idx = $userRanking->search(fn($u) => $u->id == $userId);
                return $idx !== false ? $idx + 1 : '-';
            };

            return [
                'today' => $getPodiumData($todayRanking, $yesterdayRanking, $myType, $myId),
                'yesterday' => $getPodiumData($yesterdayRanking, $lastMonthRanking, $myType, $myId),
                'this_month' => $getPodiumData($thisMonthRanking, $lastMonthRanking, $myType, $myId),
                'last_month' => $getPodiumData($lastMonthRanking, null, $myType, $myId),
                'summary' => [
                    'today_global' => $findMyRank($todayRanking),
                    'today_local' => $findMyUserRankInBranch($todayUserRanking, $user->id),
                    'yesterday_global' => $findMyRank($yesterdayRanking),
                    'yesterday_local' => $findMyUserRankInBranch($yesterdayUserRanking, $user->id),
                    'this_month_global' => $findMyRank($thisMonthRanking),
                    'this_month_local' => $findMyUserRankInBranch($thisMonthUserRanking, $user->id),
                    'last_month_global' => $findMyRank($lastMonthRanking),
                    'last_month_local' => $findMyUserRankInBranch($lastMonthUserRanking, $user->id),
                ]
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Dashboard Ranking Error: " . $e->getMessage());
            return null;
        }
    }
}
