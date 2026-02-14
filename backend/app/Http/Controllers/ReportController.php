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
        // 1. Get all brands
        $brands = Brand::orderBy('name')->get();

        $report = $brands->map(function ($brand) {
            // HP Items (ProductDetail)
            // Join with products to filter by brand
            $hpStats = ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
                ->where('products.brand_id', $brand->id)
                ->where('product_details.status', 'available')
                ->select('product_details.condition', DB::raw('count(*) as count'))
                ->groupBy('product_details.condition')
                ->pluck('count', 'condition'); // e.g., ['new' => 10, 'second' => 5]

            $hpNew = $hpStats['new'] ?? 0;
            $hpSecond = $hpStats['second'] ?? 0;

            // Non-HP Items (Inventory)
            // Inventory -> Product -> Brand
            $nonHpCount = Inventory::join('products', 'inventories.product_id', '=', 'products.id')
                ->where('products.brand_id', $brand->id)
                ->sum('inventories.quantity');

            // Assume Non-HP is 'new' for now, or we could add specific logic if needed
            // User example: "arcis new 80 debs new 20" implying they are new.
            $nonHpNew = $nonHpCount;

            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'new' => $hpNew + $nonHpNew,
                'second' => $hpSecond,
                'total' => $hpNew + $hpSecond + $nonHpNew,
            ];
        });

        // Filter out brands with 0 stock if needed, or keep them. 
        // User asked for "laporan brand", showing all is safer, or at least those with products.
        // Let's filter to show only active brands or those with stock? 
        // For now return all, frontend can filter.
        $report = $report->filter(function ($item) {
            return $item['total'] > 0;
        })->values();

        return response()->json($report);
    }

    public function getTypeReport(Request $request)
    {
        // Type Report - Focus on HP (Product Types)
        // User example: "iphone 17 pro max 512 new 1 unit"

        $types = ProductType::with(['brand'])->orderBy('name')->get();

        $report = $types->map(function ($type) {
            $stats = ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
                ->where('products.product_type_id', $type->id)
                ->where('product_details.status', 'available')
                ->select('product_details.condition', DB::raw('count(*) as count'))
                ->groupBy('product_details.condition')
                ->pluck('count', 'condition');

            $new = $stats['new'] ?? 0;
            $second = $stats['second'] ?? 0;

            if ($new == 0 && $second == 0)
                return null;

            return [
                'id' => $type->id,
                'name' => $type->name, // e.g. "iPhone 15 Pro Max"
                'brand_name' => $type->brand->name ?? '-',
                'new' => $new,
                'second' => $second,
                'total' => $new + $second,
            ];
        })->filter()->values();

        return response()->json($report);
    }
}
