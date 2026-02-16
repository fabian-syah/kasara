<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;
use App\Models\ProductType;
use App\Models\ProductDetail;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockOut;
use App\Models\StockOutNonHpItem;
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
        $filterType = $request->query('type', 'hp'); // Default to hp

        $report = collect();

        // 1. HP Items (Detail per RAM/Storage)
        if ($filterType === 'all' || $filterType === 'hp') {
            $hpQuery = ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
                ->where('products.type', 'hp')
                ->where('product_details.status', 'available')
                ->whereNull('products.deleted_at');

            // Filter Placement
            if ($isOnlineShop && $user->online_shop_id) {
                $hpQuery->where('product_details.placement_type', 'online_shop')
                    ->where('product_details.placement_id', $user->online_shop_id);
            }

            $hpStats = $hpQuery->selectRaw("
                    products.name as product_name,
                    products.brand as brand_name,
                    product_details.ram,
                    product_details.storage,
                    COUNT(CASE WHEN product_details.condition = 'new' THEN 1 END) as new_count,
                    COUNT(CASE WHEN product_details.condition = 'second' THEN 1 END) as second_count
                ")
                ->groupBy('products.name', 'products.brand', 'product_details.ram', 'product_details.storage')
                ->orderBy('products.brand')
                ->orderBy('products.name')
                ->get();

            $formattedHp = $hpStats->map(function ($item) {
                // Buat nama spesifik (Ex: iPhone 11 4GB/64GB)
                $specWithRam = $item->ram ? "{$item->ram}/{$item->storage}" : $item->storage;
                $displayName = $item->storage ? "{$item->product_name} ({$specWithRam})" : $item->product_name;

                return [
                    'id' => md5($displayName . $item->brand_name . 'hp'), // Unique ID
                    'name' => $displayName,
                    'brand_name' => $item->brand_name ?? '-',
                    'type' => 'hp',
                    'new' => (int) $item->new_count,
                    'second' => (int) $item->second_count,
                    'total' => (int) $item->new_count + (int) $item->second_count,
                    'ram' => $item->ram,
                    'storage' => $item->storage
                ];
            });

            $report = $report->concat($formattedHp);
        }

        // 2. Non-HP Items (Inventory - No Specs)
        if ($filterType === 'all' || $filterType === 'non-hp') {
            $nonHpQuery = Inventory::join('products', 'inventories.product_id', '=', 'products.id')
                ->where('products.type', 'non-hp')
                ->whereNull('products.deleted_at');

            if ($isOnlineShop && $user->online_shop_id) {
                $nonHpQuery->where('inventories.placement_type', 'online_shop')
                    ->where('inventories.placement_id', $user->online_shop_id);
            }

            $nonHpStats = $nonHpQuery->selectRaw('
                    products.name as product_name,
                    products.brand as brand_name,
                    SUM(inventories.quantity) as total_qty
                ')
                ->groupBy('products.name', 'products.brand')
                ->orderBy('products.brand')
                ->orderBy('products.name')
                ->get();

            $formattedNonHp = $nonHpStats->map(function ($item) {
                return [
                    'id' => md5($item->product_name . $item->brand_name . 'non-hp'),
                    'name' => $item->product_name,
                    'brand_name' => $item->brand_name ?? '-',
                    'type' => 'non-hp',
                    'new' => (int) $item->total_qty, // Asumsi barang baru semua untuk aksesoris
                    'second' => 0,
                    'total' => (int) $item->total_qty
                ];
            });

            $report = $report->concat($formattedNonHp);
        }

        return response()->json($report->values());
    }
    public function getSalesReport(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Categories considered as "Sales"
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        // Base query for StockOut
        $query = StockOut::whereIn('category', $salesCategories);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $stockOuts = $query->with(['items.product', 'nonHpItems.product', 'user'])->get();

        // 1. Brand Stats
        $brandStats = [];
        // 2. Product Stats
        $productStats = [];
        // 3. CS Stats
        $csStats = [];

        foreach ($stockOuts as $so) {
            // CS Stats initialization
            $userId = $so->user_id;
            if (!isset($csStats[$userId])) {
                $csStats[$userId] = [
                    'name' => $so->user->name ?? 'Unknown',
                    'hp_count' => 0,
                    'acc_count' => 0,
                    'omset' => 0
                ];
            }
            $csStats[$userId]['omset'] += $so->selling_price;

            // Process HP Items
            foreach ($so->items as $item) {
                $brandName = $item->product->brand ?? 'Unknown';
                $condition = $item->condition ?? 'new';

                // Brand Stats
                if (!isset($brandStats[$brandName])) {
                    $brandStats[$brandName] = ['brand' => $brandName, 'hp_new' => 0, 'hp_second' => 0, 'non_hp' => 0];
                }
                if ($condition === 'second') {
                    $brandStats[$brandName]['hp_second']++;
                } else {
                    $brandStats[$brandName]['hp_new']++;
                }

                // Product Stats
                $specArr = [];
                if ($item->ram)
                    $specArr[] = $item->ram;
                if ($item->storage)
                    $specArr[] = $item->storage;
                $specs = implode('/', $specArr);
                $productKey = $item->product->name . '|' . $specs . '|' . $condition;

                if (!isset($productStats[$productKey])) {
                    $productStats[$productKey] = [
                        'name' => $item->product->name,
                        'brand' => $brandName,
                        'specs' => $specs,
                        'condition' => $condition,
                        'total' => 0,
                        'is_hp' => true
                    ];
                }
                $productStats[$productKey]['total']++;

                // CS Stats - HP
                $csStats[$userId]['hp_count']++;
            }

            // Process Non-HP Items
            foreach ($so->nonHpItems as $nhp) {
                $brandName = $nhp->product->brand ?? 'Unknown';

                // Brand Stats
                if (!isset($brandStats[$brandName])) {
                    $brandStats[$brandName] = ['brand' => $brandName, 'hp_new' => 0, 'hp_second' => 0, 'non_hp' => 0];
                }
                $brandStats[$brandName]['non_hp'] += $nhp->quantity;

                // Product Stats
                $productKey = 'NHP|' . $nhp->product->name;
                if (!isset($productStats[$productKey])) {
                    $productStats[$productKey] = [
                        'name' => $nhp->product->name,
                        'brand' => $brandName,
                        'specs' => '-',
                        'condition' => 'new',
                        'total' => 0,
                        'is_hp' => false
                    ];
                }
                $productStats[$productKey]['total'] += $nhp->quantity;

                // CS Stats - Acc
                $csStats[$userId]['acc_count'] += $nhp->quantity;
            }
        }

        return response()->json([
            'brands' => array_values($brandStats),
            'products' => array_values($productStats),
            'cs' => array_values($csStats)
        ]);
    }
}
