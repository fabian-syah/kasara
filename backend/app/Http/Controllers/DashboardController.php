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
            ->latest()
            ->take(10) // Updated to 10
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

        // 4. Brand Sales (Today)
        // Aggregate by Product Name for today's sales
        $brandSalesMap = [];

        foreach ($todaySales as $sale) {
            // HP Items
            foreach ($sale->items as $item) {
                // Eager loading product name from relationship is ideal, but todaySales might not have it loaded.
                // Re-fetch or rely on relationship if loaded. StockOut model has items() relation which is ProductDetail.
                // ProductDetail belongsTo Product.
                // We need to load it. todaySales was fetched with simple get().
                // Let's reload or fetch fresh for aggregation if performance is ok.
                // Or better: fetch todaySales WITH relations.
            }
        }

        // Refetch todaySales with relationships for aggregation
        $todaySalesWithRelations = \App\Models\StockOut::with(['items.product', 'user']) // Load necessary relations
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

        foreach ($todaySalesWithRelations as $sale) {
            // HP Items
            foreach ($sale->items as $item) {
                $name = $item->product->name ?? 'Unknown';
                if (!isset($brandSalesMap[$name])) {
                    $brandSalesMap[$name] = 0;
                }
                $brandSalesMap[$name]++;
            }

            // Non-HP Items
            if ($sale->non_hp_items) {
                foreach ($sale->non_hp_items as $item) {
                    // Need product name. We can fetch using the IDs gathered earlier or do a quick lookup.
                    // Since we might have many distinct products, let's collect IDs first or use the $products map if covered.
                    // The $products map only covers recent transactions.
                    // Let's assume we need to fetch names for aggregation.
                    $pid = $item['product_id'] ?? null;
                    if ($pid) {
                        // We'll fetch all needed product names in one go below to avoid N+1 inside loop
                    }
                }
            }
        }

        // Optimized Brand Sales Aggregation
        $brandSales = [];
        // First pass: collect Non-HP Product IDs
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

        $nonHpProductNames = [];
        if (!empty($nonHpProductIds)) {
            $nonHpProductNames = \App\Models\Product::whereIn('id', array_unique($nonHpProductIds))->pluck('name', 'id');
        }

        foreach ($todaySalesWithRelations as $sale) {
            // HP
            foreach ($sale->items as $item) {
                $name = $item->product->name ?? 'Unknown';
                if (!isset($brandSales[$name])) {
                    $brandSales[$name] = 0;
                }
                $brandSales[$name]++;
            }
            // Non-HP
            if ($sale->non_hp_items) {
                foreach ($sale->non_hp_items as $item) {
                    $pid = $item['product_id'] ?? null;
                    if ($pid) {
                        $name = $nonHpProductNames[$pid] ?? 'Unknown Non-HP';
                        if (!isset($brandSales[$name])) {
                            $brandSales[$name] = 0;
                        }
                        $brandSales[$name] += ($item['quantity'] ?? 1);
                    }
                }
            }
        }

        // Convert to array of objects
        $brandSalesData = [];
        foreach ($brandSales as $name => $count) {
            $brandSalesData[] = ['name' => $name, 'count' => $count];
        }
        // Sort by count desc
        usort($brandSalesData, fn($a, $b) => $b['count'] <=> $a['count']);


        // 5. CS Performance (Today)
        $csPerformance = [];
        foreach ($todaySalesWithRelations as $sale) {
            $csName = $sale->user->name ?? 'Unknown'; // Or full_name
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
                // Assume HP or spread? Let's just add to sales.
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
            'brandSales' => $brandSalesData,
            'csPerformance' => $csPerformanceData
        ]);
    }
}
