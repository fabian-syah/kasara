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
        $isBranch = $user->branch_id && !$user->hasRole('super_admin') && !$user->hasRole('analist');
        $filterType = $request->query('type', 'all'); // all, hp, non-hp

        // 1. Get all brands
        $brands = Brand::orderBy('name')->get();

        $report = $brands->map(function ($brand) use ($user, $isOnlineShop, $isBranch, $filterType) {
            $hpNew = 0;
            $hpSecond = 0;
            $hpExIbox = 0;
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
                } elseif ($isBranch) {
                    $hpQuery->where('product_details.placement_type', 'branch')
                        ->where('product_details.placement_id', $user->branch_id);
                }

                $hpStats = $hpQuery->select('product_details.condition', DB::raw('count(*) as count'))
                    ->groupBy('product_details.condition')
                    ->pluck('count', 'condition');

                $hpNew = $hpStats['new'] ?? 0;
                $hpSecond = $hpStats['second'] ?? 0;
                $hpExIbox = $hpStats['ex_ibox'] ?? 0;
            }

            // Non-HP Items (Inventory) - Only if filter is 'all' or 'non-hp'
            if ($filterType === 'all' || $filterType === 'non-hp') {
                $nonHpQuery = Inventory::join('products', 'inventories.product_id', '=', 'products.id')
                    ->where('products.brand', $brand->name)
                    ->whereNull('products.deleted_at');

                if ($isOnlineShop && $user->online_shop_id) {
                    $nonHpQuery->where('inventories.placement_type', 'online_shop')
                        ->where('inventories.placement_id', $user->online_shop_id);
                } elseif ($isBranch) {
                    $nonHpQuery->where('inventories.placement_type', 'branch')
                        ->where('inventories.placement_id', $user->branch_id);
                }

                $nonHpCount = $nonHpQuery->sum('inventories.quantity');
                $nonHpNew = $nonHpCount;
            }

            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'new' => $hpNew + $nonHpNew,
                'second' => $hpSecond,
                'ex_ibox' => $hpExIbox,
                'total' => $hpNew + $hpSecond + $hpExIbox + $nonHpNew,
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
        $isBranch = $user->branch_id && !$user->hasRole('super_admin') && !$user->hasRole('analist');
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
            } elseif ($isBranch) {
                $hpQuery->where('product_details.placement_type', 'branch')
                    ->where('product_details.placement_id', $user->branch_id);
            }

            $hpStats = $hpQuery->selectRaw("
                    products.name as product_name,
                    products.brand as brand_name,
                    product_details.ram,
                    product_details.storage,
                    COUNT(CASE WHEN product_details.condition = 'new' THEN 1 END) as new_count,
                    COUNT(CASE WHEN product_details.condition = 'second' THEN 1 END) as second_count,
                    COUNT(CASE WHEN product_details.condition = 'ex_ibox' THEN 1 END) as ex_ibox_count
                ")
                ->groupBy('products.name', 'products.brand', 'product_details.ram', 'product_details.storage')
                ->orderBy('products.brand')
                ->orderBy('products.name')
                ->get();

            $formattedHp = $hpStats->map(function ($item) {
                $specWithRam = $item->ram ? "{$item->ram}/{$item->storage}" : $item->storage;
                $displayName = $item->storage ? "{$item->product_name} ({$specWithRam})" : $item->product_name;

                return [
                    'id' => md5($displayName . $item->brand_name . 'hp'),
                    'name' => $displayName,
                    'brand_name' => $item->brand_name ?? '-',
                    'type' => 'hp',
                    'new' => (int) $item->new_count,
                    'second' => (int) $item->second_count,
                    'ex_ibox' => (int) $item->ex_ibox_count,
                    'total' => (int) $item->new_count + (int) $item->second_count + (int) $item->ex_ibox_count,
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
            } elseif ($isBranch) {
                $nonHpQuery->where('inventories.placement_type', 'branch')
                    ->where('inventories.placement_id', $user->branch_id);
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
                    'new' => (int) $item->total_qty,
                    'second' => 0,
                    'ex_ibox' => 0,
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
        $branchId = $request->query('branch_id');
        $onlineShopId = $request->query('online_shop_id');

        // Group CS stats by Petugas Stok (inventory_user_id) as requested
        $csUserIdField = 'inventory_user_id';

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        // Role-based scoping and strict isolation
        $user = $request->user();
        $isRestricted = !$user->hasRole('super_admin') && !$user->hasRole('analist');

        // Final filter values
        $filterBranchId = $branchId;
        $filterOnlineShopId = $onlineShopId;

        if ($isRestricted) {
            // Strict enforcement: if user has a branch, they can ONLY see that branch
            // If user has an online shop, they can ONLY see that shop
            $filterBranchId = $user->branch_id;
            $filterOnlineShopId = $user->online_shop_id;
        }

        // Helper to apply strict user-based filters to any query joined with users
        $applyIsolation = function ($query) use ($filterBranchId, $filterOnlineShopId) {
            if ($filterBranchId) {
                $query->where('users.branch_id', $filterBranchId);
            }
            if ($filterOnlineShopId) {
                $query->where('users.online_shop_id', $filterOnlineShopId);
            }
            return $query;
        };

        // 1. CS STATS (Aggregation by User)
        $csQuery = StockOut::whereIn('category', $salesCategories)
            ->join('users', "stock_outs.{$csUserIdField}", '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                DB::raw('SUM(stock_outs.selling_price) as omset')
            );

        if ($startDate)
            $csQuery->where('stock_outs.reporting_date', '>=', $startDate);
        if ($endDate)
            $csQuery->where('stock_outs.reporting_date', '<=', $endDate);
        $csQuery = $applyIsolation($csQuery);

        $csBase = $csQuery->groupBy('users.id', 'users.name')->get();

        // Get counts for HP per User
        $hpCountsQuery = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('users', "stock_outs.{$csUserIdField}", '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories);
    if ($startDate)
        $hpCountsQuery->where('stock_outs.reporting_date', '>=', $startDate);
    if ($endDate)
        $hpCountsQuery->where('stock_outs.reporting_date', '<=', $endDate);
        $hpCountsQuery = $applyIsolation($hpCountsQuery);

        $hpCountsPerUser = $hpCountsQuery->select("stock_outs.{$csUserIdField}", DB::raw('COUNT(*) as hp_count'))
            ->groupBy("stock_outs.{$csUserIdField}")
            ->pluck('hp_count', $csUserIdField);

        // Get counts for Non-HP per User
        $accCountsQuery = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->join('users', "stock_outs.{$csUserIdField}", '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories);
    if ($startDate)
        $accCountsQuery->where('stock_outs.reporting_date', '>=', $startDate);
    if ($endDate)
        $accCountsQuery->where('stock_outs.reporting_date', '<=', $endDate);
        $accCountsQuery = $applyIsolation($accCountsQuery);

        $accCountsPerUser = $accCountsQuery->select("stock_outs.{$csUserIdField}", DB::raw('SUM(quantity) as acc_count'))
            ->groupBy("stock_outs.{$csUserIdField}")
            ->pluck('acc_count', $csUserIdField);

        $csStats = $csBase->map(function ($user) use ($hpCountsPerUser, $accCountsPerUser) {
            return [
                'name' => $user->name,
                'hp_count' => (int) ($hpCountsPerUser[$user->id] ?? 0),
                'acc_count' => (int) ($accCountsPerUser[$user->id] ?? 0),
                'omset' => (float) $user->omset
            ];
        });

        // 2. BRAND STATS (Aggregated)
        // Join with users to filter by branch
        $hpBaseQuery = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories);
    if ($startDate)
        $hpBaseQuery->where('stock_outs.reporting_date', '>=', $startDate);
    if ($endDate)
        $hpBaseQuery->where('stock_outs.reporting_date', '<=', $endDate);
        $hpBaseQuery = $applyIsolation($hpBaseQuery);

        $hpBrandStats = (clone $hpBaseQuery)->select(
            'products.brand',
            'product_details.condition',
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('products.brand', 'product_details.condition')
            ->get();

        // Non-HP Brand Stats
        $nhpBaseQuery = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories);
    if ($startDate)
        $nhpBaseQuery->where('stock_outs.reporting_date', '>=', $startDate);
    if ($endDate)
        $nhpBaseQuery->where('stock_outs.reporting_date', '<=', $endDate);
        $nhpBaseQuery = $applyIsolation($nhpBaseQuery);

        $nhpBrandStats = (clone $nhpBaseQuery)->select(
            'products.brand',
            DB::raw('SUM(quantity) as count')
        )
            ->groupBy('products.brand')
            ->get();

        $brandStatsMap = [];
        foreach ($hpBrandStats as $s) {
            if (!isset($brandStatsMap[$s->brand])) {
                $brandStatsMap[$s->brand] = ['brand' => $s->brand, 'hp_new' => 0, 'hp_second' => 0, 'hp_ex_ibox' => 0, 'non_hp' => 0];
            }
            if ($s->condition === 'second')
                $brandStatsMap[$s->brand]['hp_second'] += $s->count;
            elseif ($s->condition === 'ex_ibox')
                $brandStatsMap[$s->brand]['hp_ex_ibox'] += $s->count;
            else
                $brandStatsMap[$s->brand]['hp_new'] += $s->count;
        }
        foreach ($nhpBrandStats as $s) {
            if (!isset($brandStatsMap[$s->brand])) {
                $brandStatsMap[$s->brand] = ['brand' => $s->brand, 'hp_new' => 0, 'hp_second' => 0, 'hp_ex_ibox' => 0, 'non_hp' => 0];
            }
            $brandStatsMap[$s->brand]['non_hp'] += $s->count;
        }

        // 3. PRODUCT STATS (Aggregated)
        // HP Product Stats
        $hpProductStatsData = (clone $hpBaseQuery)->select(
            'products.name',
            'products.brand',
            'product_details.ram',
            'product_details.storage',
            'product_details.condition',
            DB::raw('COUNT(*) as total')
        )
            ->groupBy('products.name', 'products.brand', 'product_details.ram', 'product_details.storage', 'product_details.condition')
            ->get()
            ->map(function ($p) {
                $specArr = [];
                if ($p->ram)
                    $specArr[] = $p->ram;
                if ($p->storage)
                    $specArr[] = $p->storage;
                return [
                    'name' => $p->name,
                    'brand' => $p->brand,
                    'specs' => implode('/', $specArr) ?: '-',
                    'condition' => $p->condition,
                    'total' => $p->total,
                    'is_hp' => true
                ];
            });

        // Non-HP Product Stats
        $nhpProductStatsData = (clone $nhpBaseQuery)->select(
            'products.name',
            'products.brand',
            DB::raw('SUM(quantity) as total')
        )
            ->groupBy('products.name', 'products.brand')
            ->get()
            ->map(function ($p) {
                return [
                    'name' => $p->name,
                    'brand' => $p->brand,
                    'specs' => '-',
                    'condition' => 'new',
                    'total' => $p->total,
                    'is_hp' => false
                ];
            });

        return response()->json([
            'brands' => array_values($brandStatsMap),
            'products' => $hpProductStatsData->concat($nhpProductStatsData),
            'cs' => array_values($csStats->toArray())
        ]);
    }

    public function getRankingReport(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'];

        // 1. Get Offline Branches Stats
        $branchStats = DB::table('branches')
            ->leftJoin('users', 'branches.id', '=', 'users.branch_id')
            ->leftJoin('stock_outs', function($join) use ($startDate, $endDate, $salesCategories) {
                $join->on('users.id', '=', 'stock_outs.user_id')
                    ->whereIn('stock_outs.category', $salesCategories)
                    ->whereNull('stock_outs.deleted_at');
                if ($startDate) $join->where('stock_outs.reporting_date', '>=', $startDate);
                if ($endDate) $join->where('stock_outs.reporting_date', '<=', $endDate);
            })
            ->select(
                'branches.id',
                'branches.name',
                DB::raw("'Offline' as type"),
                DB::raw('SUM(COALESCE(stock_outs.selling_price, 0)) as omset'),
                DB::raw('COUNT(stock_outs.id) as transaction_count')
            )
            ->groupBy('branches.id', 'branches.name')
            ->get();

        // 2. Get Online Shop Stats
        $onlineStats = DB::table('online_shops')
            ->leftJoin('users', 'online_shops.id', '=', 'users.online_shop_id')
            ->leftJoin('stock_outs', function($join) use ($startDate, $endDate, $salesCategories) {
                $join->on('users.id', '=', 'stock_outs.user_id')
                    ->whereIn('stock_outs.category', $salesCategories)
                    ->whereNull('stock_outs.deleted_at');
                if ($startDate) $join->where('stock_outs.reporting_date', '>=', $startDate);
                if ($endDate) $join->where('stock_outs.reporting_date', '<=', $endDate);
            })
            ->select(
                'online_shops.id',
                'online_shops.name',
                DB::raw("'Online' as type"),
                DB::raw('SUM(COALESCE(stock_outs.selling_price, 0)) as omset'),
                DB::raw('COUNT(stock_outs.id) as transaction_count')
            )
            ->groupBy('online_shops.id', 'online_shops.name')
            ->get();

        $report = $branchStats->concat($onlineStats)
            ->map(function($item) {
                $item->omset = (float) $item->omset;
                return $item;
            })
            ->sortByDesc('omset')
            ->values();

        return response()->json($report);
    }

    public function getReportFilters(Request $request)
    {
        $user = $request->user();
        $isRestricted = !$user->hasRole('super_admin') && !$user->hasRole('analist');

        if ($isRestricted) {
            return response()->json([
                'branches' => [],
                'online_shops' => []
            ]);
        }

        return response()->json([
            'branches' => \App\Models\Branch::orderBy('name')->get(['id', 'name']),
            'online_shops' => \App\Models\OnlineShop::orderBy('name')->get(['id', 'name'])
        ]);
    }
}
