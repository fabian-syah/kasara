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
            $hpQuery = ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
                ->where('products.brand_id', $brand->id)
                ->where('product_details.status', 'available');

            if ($isOnlineShop && $user->online_shop_id) {
                // Assuming ProductDetail needs placement filtering for Online Shop?
                // Actually ProductDetail usually tracks specific items.
                // If the item is in "Online Shop" placement.
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
                ->where('products.brand_id', $brand->id);

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
        $types = ProductType::with(['brand'])->orderBy('name')->get();

        $report = $types->map(function ($type) use ($user, $isOnlineShop) {
            $query = ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
                ->where('products.product_type_id', $type->id)
                ->where('product_details.status', 'available');

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

            return [
                'id' => $type->id,
                'name' => $type->name,
                'brand_name' => $type->brand->name ?? '-',
                'new' => $new,
                'second' => $second,
                'total' => $new + $second,
            ];
        })->filter()->values();

        return response()->json($report);
    }
}
