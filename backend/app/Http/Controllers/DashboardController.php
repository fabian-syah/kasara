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
            ->take(5)
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
                'status' => 'success'
            ];
        });


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
            'recentTransactions' => $recentTransactions
        ]);
    }
}
