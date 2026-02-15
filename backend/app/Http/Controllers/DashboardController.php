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

        // Check for specific role logic (Case insensitive check)
        // Debugging
        \Illuminate\Support\Facades\Log::info('Dashboard Access: User ' . $user->name . ' Roles: ' . $user->getRoleNames());

        if ($user->hasRole('admin_produk') || $user->hasRole('Admin Produk') || $user->hasRole('ADMIN PRODUK')) {
            return $this->getAdminProdukStats();
        }

        if ($user->online_shop_id || $user->hasRole('online_shop') || $user->hasRole('toko_online')) {
            return $this->getOnlineShopStats($user);
        }

        // Default stats (empty or minimal for now to not break frontend expecting structure)
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
        // Recent Product Types
        $recentTypes = ProductType::with(['brand'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'brand_name' => $type->brand->name ?? '-',
                    'category_name' => $type->category ?? '-', // Category is a string column, not relationship
                    'created_at' => $type->created_at->format('d M Y H:i'),
                ];
            });

        // Recent Price Updates
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
                [
                    'id' => 'brands',
                    'label' => 'Total Brand',
                    'value' => $brandCount,
                    'icon' => 'Database',
                    'color' => 'blue',
                    'link' => '/brands'
                ],
                [
                    'id' => 'types',
                    'label' => 'Total Tipe Produk',
                    'value' => $typeCount,
                    'icon' => 'Tags',
                    'color' => 'emerald',
                    'link' => '/types'
                ],
                [
                    'id' => 'prices',
                    'label' => 'Data Harga',
                    'value' => $priceCount,
                    'icon' => 'DollarSign',
                    'color' => 'amber',
                    'link' => '/prices'
                ],
                [
                    'id' => 'categories',
                    'label' => 'Kategori',
                    'value' => $categoryCount,
                    'icon' => 'Box',
                    'color' => 'violet',
                    'link' => '/categories'
                ]
            ],
            'recent_types' => $recentTypes,
            'recent_prices' => $recentPrices
        ]);
    }

    private function getOnlineShopStats($user)
    {
        // 1. Sales Report (Today)
        // Category: shopee AND orderan_online
        // Filter by Online Shop ID to ensure data ownership
        $todaySalesQuery = \App\Models\StockOut::whereIn('category', ['shopee', 'orderan_online'])
            ->whereDate('created_at', now());

        if ($user->online_shop_id) {
            $todaySalesQuery->whereHas('user', function ($q) use ($user) {
                $q->where('online_shop_id', $user->online_shop_id);
            });
        } else {
            // Fallback: created_by user
            $todaySalesQuery->where('user_id', $user->id);
        }

        $todaySales = $todaySalesQuery->get();

        $totalRevenue = 0;
        $totalTransactions = $todaySales->count();
        $productsSold = 0;

        foreach ($todaySales as $sale) {
            // Calculate revenue based on items
            // HP Items
            foreach ($sale->items as $item) {
                $totalRevenue += $item->selling_price;
                $productsSold++;
            }
            // Non-HP Items
            if ($sale->non_hp_items) {
                foreach ($sale->non_hp_items as $item) {
                    $totalRevenue += ($item['selling_price'] ?? 0) * ($item['quantity'] ?? 1);
                    $productsSold += ($item['quantity'] ?? 1);
                }
            }
            // Fallback if no items but global price (legacy)
            if ($sale->items->isEmpty() && !$sale->non_hp_items) {
                $totalRevenue += $sale->selling_price;
            }
        }

        // 2. Real-time Stock Report
        // HP Items
        $hpStock = \App\Models\ProductDetail::where('placement_type', 'online_shop')
            ->where('placement_id', $user->online_shop_id)
            ->where('status', 'available')
            ->count();

        // Non-HP Items
        $nonHpStock = \App\Models\Inventory::where('placement_type', 'online_shop')
            ->where('placement_id', $user->online_shop_id)
            ->sum('quantity');

        // Total Asset Value (Optional: roughly based on base price / average) -- Skip for now, simpler

        // 3. Recent Transactions
        $recentTransactions = \App\Models\StockOut::with(['items.product', 'user'])
            ->whereIn('category', ['shopee', 'orderan_online'])
            ->whereHas('user', function ($q) use ($user) {
                if ($user->online_shop_id) {
                    $q->where('online_shop_id', $user->online_shop_id);
                } else {
                    $q->where('id', $user->id);
                }
            })
            ->whereDate('created_at', now()) // Filter for Today only as requested
            ->latest()
            ->take(10)
            ->get();

        // Enrich Non-HP Product Names
        $productIds = [];
        foreach ($recentTransactions as $trx) {
            if ($trx->non_hp_items) {
                foreach ($trx->non_hp_items as $item) {
                    if (isset($item['product_id'])) {
                        $productIds[] = $item['product_id'];
                    }
                }
            }
        }

        $products = [];
        if (!empty($productIds)) {
            $products = \App\Models\Product::whereIn('id', array_unique($productIds))->pluck('name', 'id');
        }

        $recentTransactions = $recentTransactions->map(function ($trx) use ($products) {
            $items = [];
            foreach ($trx->items as $item) {
                $items[] = $item->product->name;
            }
            if ($trx->non_hp_items) {
                foreach ($trx->non_hp_items as $item) {
                    $name = $products[$item['product_id']] ?? 'Unknown Product';
                    $items[] = $name . ' (' . ($item['quantity'] ?? 1) . ')';
                }
            }

            return [
                'id' => $trx->receipt_id,
                'customer' => $trx->shopee_receiver ?? $trx->receiver_name ?? 'Guest',
                'total' => $trx->items->sum('selling_price') + (collect($trx->non_hp_items)->sum(fn($i) => ($i['selling_price'] ?? 0) * ($i['quantity'] ?? 1))),
                'items' => implode(', ', array_slice($items, 0, 2)) . (count($items) > 2 ? '...' : ''),
                'time' => $trx->created_at->diffForHumans(),
                'datetime' => $trx->created_at->format('d M H:i'), // Added datetime for daily context
                'status' => 'success'
            ];
        });

        // 4. Type Sales (Previously Brand Sales) - Aggregation by Product Name (Type)
        // Aggregate by Product Name for today's sales
        $typeSalesMap = [];

        // 5. Brand Condition Sales - Aggregation by Brand + Condition
        $brandConditionMap = [];

        foreach ($todaySales as $sale) {
            // HP Items
            foreach ($sale->items as $item) {
                // Type Stats
                $name = $item->product->name ?? 'Unknown';
                if (!isset($typeSalesMap[$name])) {
                    $typeSalesMap[$name] = 0;
                }
                $typeSalesMap[$name]++;

                // Brand Condition Stats
                $brand = $item->product->brand ?? 'Unknown Brand';
                $condition = ucfirst($item->condition ?? 'new'); // New/Used -> Baru/Bekas ideally
                if (strtolower($condition) == 'new')
                    $condition = 'Baru';
                if (strtolower($condition) == 'used' || strtolower($condition) == 'second')
                    $condition = 'Second';

                $key = "$brand $condition";
                if (!isset($brandConditionMap[$key])) {
                    $brandConditionMap[$key] = 0;
                }
                $brandConditionMap[$key]++;
            }
        }

        // Refetch todaySales with relations (optimized)
        // We actually need relationships for the above loops if we want to be safe,
        // but let's stick to the existing pattern of refetching or using what we have.
        // The previous code refetched $todaySalesWithRelations. Let's use that.

        $todaySalesWithRelations = \App\Models\StockOut::with(['items.product', 'user', 'inventoryUser'])
            ->whereIn('category', ['shopee', 'orderan_online'])
            ->whereDate('created_at', now());

        if ($user->online_shop_id) {
            $todaySalesWithRelations->whereHas('user', function ($q) use ($user) {
                $q->where('online_shop_id', $user->online_shop_id);
            });
        } else {
            $todaySalesWithRelations->where('user_id', $user->id);
        }
        $todaySalesWithRelations = $todaySalesWithRelations->get();

        // Re-init maps
        $typeSales = [];
        $brandConditionSales = [];

        // Pre-fetch Non-HP Product Names and Brands
        $nonHpProductIds = [];
        foreach ($todaySalesWithRelations as $sale) {
            if ($sale->non_hp_items) {
                foreach ($sale->non_hp_items as $item) {
                    if (isset($item['product_id'])) {
                        $nonHpProductIds[] = $item['product_id'];
                    }
                }
            }
        }

        $nonHpProducts = [];
        if (!empty($nonHpProductIds)) {
            $nonHpProducts = \App\Models\Product::whereIn('id', array_unique($nonHpProductIds))->get()->keyBy('id');
        }

        foreach ($todaySalesWithRelations as $sale) {
            // HP Items
            foreach ($sale->items as $item) {
                // Type Stats
                $name = $item->product->name ?? 'Unknown';
                if (!isset($typeSales[$name])) {
                    $typeSales[$name] = 0;
                }
                $typeSales[$name]++;

                // Brand Condition Stats
                $brand = $item->product->brand ?? 'Unknown';
                $conditionRaw = $item->condition ?? 'new';
                $condition = ($conditionRaw === 'new') ? 'New' : 'Second'; // User requested "iPhone New", "iPhone Second"

                $key = "$brand $condition";
                if (!isset($brandConditionSales[$key])) {
                    $brandConditionSales[$key] = 0;
                }
                $brandConditionSales[$key]++;
            }
            // Non-HP Items
            if ($sale->non_hp_items) {
                foreach ($sale->non_hp_items as $item) {
                    $pid = $item['product_id'] ?? null;
                    if ($pid && isset($nonHpProducts[$pid])) {
                        $product = $nonHpProducts[$pid];
                        $qty = $item['quantity'] ?? 1;

                        // Type Stats
                        $name = $product->name;
                        if (!isset($typeSales[$name])) {
                            $typeSales[$name] = 0;
                        }
                        $typeSales[$name] += $qty;

                        // Brand Condition Stats (Assume Non-HP is New)
                        $brand = $product->brand ?? 'Unknown';
                        $condition = 'New';

                        $key = "$brand $condition";
                        if (!isset($brandConditionSales[$key])) {
                            $brandConditionSales[$key] = 0;
                        }
                        $brandConditionSales[$key] += $qty;
                    }
                }
            }
        }

        // 6. Top 5 Products All-Time (Per Shop/Branch)
        $allTimeSalesQuery = \App\Models\StockOut::whereIn('category', ['shopee', 'orderan_online']);

        if ($user->online_shop_id) {
            $allTimeSalesQuery->whereHas('user', function ($q) use ($user) {
                $q->where('online_shop_id', $user->online_shop_id);
            });
        } else {
            $allTimeSalesQuery->where('user_id', $user->id);
        }

        $allTimeSalesIds = $allTimeSalesQuery->pluck('id');

        // Aggregrate HP
        $hpAgg = \DB::table('stock_out_items')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->whereIn('stock_out_items.stock_out_id', $allTimeSalesIds)
            ->select('products.name', \DB::raw('count(*) as total'))
            ->groupBy('products.name')
            ->get();

        // Aggregate Non-HP
        $nonHpAgg = \DB::table('stock_out_non_hp_items')
            ->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')
            ->whereIn('stock_out_non_hp_items.stock_out_id', $allTimeSalesIds)
            ->select('products.name', \DB::raw('sum(quantity) as total'))
            ->groupBy('products.name')
            ->get();

        $combinedSales = [];
        foreach ($hpAgg as $item) {
            if (!isset($combinedSales[$item->name]))
                $combinedSales[$item->name] = 0;
            $combinedSales[$item->name] += $item->total;
        }
        foreach ($nonHpAgg as $item) {
            if (!isset($combinedSales[$item->name]))
                $combinedSales[$item->name] = 0;
            $combinedSales[$item->name] += $item->total;
        }

        $typeSalesData = [];
        foreach ($combinedSales as $name => $count) {
            $typeSalesData[] = ['name' => $name, 'count' => $count];
        }
        usort($typeSalesData, fn($a, $b) => $b['count'] <=> $a['count']);
        $typeSalesData = array_slice($typeSalesData, 0, 5);

        // Format Brand Condition Sales
        $brandConditionData = [];
        foreach ($brandConditionSales as $name => $count) {
            $brandConditionData[] = ['name' => $name, 'count' => $count];
        }
        usort($brandConditionData, fn($a, $b) => $b['count'] <=> $a['count']);


        // 5. CS Performance (Today)
        $csPerformance = [];
        foreach ($todaySalesWithRelations as $sale) {
            // Use inventory_user relationship if available, otherwise fallback to user (creator)
            // inventory_user_id is the one selected in the form
            $csName = $sale->inventoryUser->name ?? $sale->user->name ?? 'Unknown';

            if (!isset($csPerformance[$csName])) {
                $csPerformance[$csName] = [
                    'name' => $csName,
                    'hp_count' => 0,
                    'non_hp_count' => 0,
                    'total_sales' => 0
                ];
            }

            // Calculations
            $saleTotal = 0;
            // HP
            foreach ($sale->items as $item) {
                $csPerformance[$csName]['hp_count']++;
                $saleTotal += $item->selling_price;
            }
            // Non-HP
            if ($sale->non_hp_items) {
                foreach ($sale->non_hp_items as $item) {
                    $csPerformance[$csName]['non_hp_count'] += ($item['quantity'] ?? 1);
                    $saleTotal += ($item['selling_price'] ?? 0) * ($item['quantity'] ?? 1);
                }
            }
            // Legacy/Fallback selling_price if no items detected but price exists
            if ($sale->items->isEmpty() && !$sale->non_hp_items && $sale->selling_price > 0) {
                $saleTotal += $sale->selling_price;
            }

            $csPerformance[$csName]['total_sales'] += $saleTotal;
        }

        // Convert to array
        $csPerformanceData = array_values($csPerformance);
        usort($csPerformanceData, fn($a, $b) => $b['total_sales'] <=> $a['total_sales']);

        return response()->json([
            'role' => 'online_shop',
            'stats' => [
                [
                    'id' => 'revenue',
                    'label' => 'Pendapatan Hari Ini',
                    'value' => $totalRevenue,
                    'isCurrency' => true,
                    'icon' => 'DollarSign',
                    'color' => 'emerald',
                ],
                [
                    'id' => 'transactions',
                    'label' => 'Total Transaksi',
                    'value' => $totalTransactions,
                    'icon' => 'ShoppingCart',
                    'color' => 'blue',
                ],
                [
                    'id' => 'sold',
                    'label' => 'Produk Terjual',
                    'value' => $productsSold,
                    'icon' => 'Package',
                    'color' => 'violet',
                ],
                [
                    'id' => 'stock',
                    'label' => 'Total Stok Fisik',
                    'value' => $hpStock + $nonHpStock,
                    'sub' => "HP: $hpStock | Acc: $nonHpStock",
                    'icon' => 'Box',
                    'color' => 'amber',
                ],
            ],
            'recentTransactions' => $recentTransactions,
            'typeSales' => $typeSalesData, // Renamed from brandSales
            'brandSales' => $brandConditionData, // New Brand+Condition stats (User requested name 'Total Brand Terjual', so we map to brandSales key for frontend compatibility or new key)
            // Wait, user said "Total Brand Terjual (Hari Ini) jadi Total Type Terjual". 
            // So the OLD 'brandSales' (which was actually types) should be 'typeSales' or displayed as Type.
            // And the NEW one should be "Total Brand Terjual".
            // So in frontend: 
            // - Old section -> uses 'typeSales'
            // - New section -> uses 'brandSales' (Brand+Condition)
            'csPerformance' => $csPerformanceData
        ]);
    }
}
