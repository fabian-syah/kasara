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

        // 1. Get all brands
        $brands = Brand::orderBy('name')->get();

        $report = $brands->map(function ($brand) use ($user, $isOnlineShop) {
            // HP Items (ProductDetail)
            // Join with products to filter by brand NAME (string column)
            // We use 'products.brand' because the schema uses string, not brand_id
            $hpQuery = ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
                ->where('products.brand', $brand->name)
                ->where('product_details.status', 'available')
                ->whereNull('products.deleted_at');

            if ($isOnlineShop && $user->online_shop_id) {
                // Determine placement by actual column data
                // ProductDetail has placement_type and placement_id
                $hpQuery->where('product_details.placement_type', 'online_shop')
                    ->where('product_details.placement_id', $user->online_shop_id);
            }

            $hpStats = $hpQuery->select('product_details.condition', DB::raw('count(*) as count'))
                ->groupBy('product_details.condition')
                ->pluck('count', 'condition');

            $hpNew = $hpStats['new'] ?? 0;
            $hpSecond = $hpStats['second'] ?? 0;

            // Non-HP Items (Inventory)
            $nonHpQuery = Inventory::join('products', 'inventories.product_id', '=', 'products.id')
                ->where('products.brand', $brand->name)
                ->whereNull('products.deleted_at');

            if ($isOnlineShop && $user->online_shop_id) {
                $nonHpQuery->where('inventories.placement_type', 'online_shop')
                    ->where('inventories.placement_id', $user->online_shop_id);
            }

            $nonHpCount = $nonHpQuery->sum('inventories.quantity');

            // Assume Non-HP is 'new'
            $nonHpNew = $nonHpCount;

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

        // Type Report - Focus on HP (Product Types)
        // Since 'products' table doesn't have product_type_id, we'll iterate over Products themselves
        // or loop over ProductType and try to match match names?
        // Safer to loop over Products that are of type 'hp' and distinct names?
        // OR loop over ProductType and assume Product Name matches ProductType Name.

        // Let's try iterating over Products first as it's the source of truth for stock
        // Grouping by Name as "Type"

        $products = Product::where('type', 'hp')
            ->select('name', 'brand')
            ->distinct()
            ->orderBy('name')
            ->get();

        $report = $products->map(function ($product) use ($user, $isOnlineShop) {
            $query = ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
                ->where('products.name', $product->name) // Match by Name
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

            if ($new == 0 && $second == 0)
                return null;

            // Generate a ID for frontend key, maybe use name or hash
            return [
                'id' => md5($product->name),
                'name' => $product->name,
                'brand_name' => $product->brand ?? '-',
                'new' => $new,
                'second' => $second,
                'total' => $new + $second,
            ];
        })->filter()->values();

        return response()->json($report);
    }
}
