<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;
use App\Models\ProductType;
use App\Models\ProductDetail;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function getBrandReport(Request $request)
    {
        $user = $request->user();
        $isOnlineShop = $user->hasRole('online_shop') || $user->hasRole('toko_online') || $user->online_shop_id;
        $filterType = $request->query('type', 'all'); // all, hp, non-hp

        // 1. Get all brands
        $brands = Brand::orderBy('name')->get();

        $report = $brands->map(function ($brand) use ($user, $isOnlineShop, $filterType) {
            $hpNew = 0;
            $hpSecond = 0;
            $nonHpNew = 0;

            // HP Items (ProductDetail) - Only if filter is 'all' or 'hp'
            if ($filterType === 'all' || $filterType === 'hp') {
                $hpQuery = ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
                    ->where('products.brand', $brand->name)
                    ->where('product_details.status', 'available')
                    ->whereNull('products.deleted_at');

                if ($isOnlineShop && $user->online_shop_id) {
                    $hpQuery->where('product_details.placement_type', 'online_shop')
                        ->where('product_details.placement_id', $user->online_shop_id);
                }

                $hpStats = $hpQuery->select('product_details.condition', DB::raw('count(*) as count'))
                    ->groupBy('product_details.condition')
                    ->pluck('count', 'condition');

                $hpNew = $hpStats['new'] ?? 0;
                $hpSecond = $hpStats['second'] ?? 0;
            }

            // Non-HP Items (Inventory) - Only if filter is 'all' or 'non-hp'
            if ($filterType === 'all' || $filterType === 'non-hp') {
                $nonHpQuery = Inventory::join('products', 'inventories.product_id', '=', 'products.id')
                    ->where('products.brand', $brand->name)
                    ->whereNull('products.deleted_at');

                if ($isOnlineShop && $user->online_shop_id) {
                    $nonHpQuery->where('inventories.placement_type', 'online_shop')
                        ->where('inventories.placement_id', $user->online_shop_id);
                }

                $nonHpCount = $nonHpQuery->sum('inventories.quantity');
                $nonHpNew = $nonHpCount;
            }

            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'new' => $hpNew + $nonHpNew,
                'second' => $hpSecond,
                'total' => $hpNew + $hpSecond + $nonHpNew,
            ];
        })->filter(function ($item) {
            return $item['total'] > 0;
        })->values();

        return response()->json($report);
    }

    public function getTypeReport(Request $request)
    {
        $user = $request->user();
        $isOnlineShop = $user->hasRole('online_shop') || $user->hasRole('toko_online') || $user->online_shop_id;
        $filterType = $request->query('type', 'hp'); // Default to hp for Type Report usually, but allow 'all' or 'non-hp'

        // Base Product Query
        $productQuery = Product::query()
            ->select('name', 'brand', 'type')
            ->distinct()
            ->orderBy('brand') // Order by Brand first
            ->orderBy('name');

        if ($filterType === 'hp') {
            $productQuery->where('type', 'hp');
        } elseif ($filterType === 'non-hp') {
            $productQuery->where('type', 'non-hp');
        }

        $products = $productQuery->get();

        $report = $products->map(function ($product) use ($user, $isOnlineShop) {
            $new = 0;
            $second = 0;

            if ($product->type === 'hp') {
                // Count from ProductDetail
                $query = ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
                    ->where('products.name', $product->name)
                    ->where('product_details.status', 'available')
                    ->whereNull('products.deleted_at');

                if ($isOnlineShop && $user->online_shop_id) {
                    $query->where('product_details.placement_type', 'online_shop')
                        ->where('product_details.placement_id', $user->online_shop_id);
                }

                $stats = $query->select('product_details.condition', DB::raw('count(*) as count'))
                    ->groupBy('product_details.condition')
                    ->pluck('count', 'condition');

                $new = $stats['new'] ?? 0;
                $second = $stats['second'] ?? 0;

            } else {
                // Count from Inventory
                $query = Inventory::join('products', 'inventories.product_id', '=', 'products.id')
                    ->where('products.name', $product->name)
                    ->whereNull('products.deleted_at');

                if ($isOnlineShop && $user->online_shop_id) {
                    $query->where('inventories.placement_type', 'online_shop')
                        ->where('inventories.placement_id', $user->online_shop_id);
                }

                $new = $query->sum('inventories.quantity'); // Assume Non-HP is new
                $second = 0;
            }

            if ($new == 0 && $second == 0)
                return null;

            return [
                'id' => md5($product->name),
                'name' => $product->name,
                'brand_name' => $product->brand ?? '-',
                'type' => $product->type, // 'hp' or 'non-hp'
                'new' => $new,
                'second' => $second,
                'total' => $new + $second,
            ];
        })->filter()->values();

        return response()->json($report);
    }
}
