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
}
