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
use App\Models\InventoryLog;
use App\Models\ExportLog;
use App\Utils\SimpleXLSXGen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function getBrandReport(Request $request)
    {
        $user = $request->user();
        $isOnlineShop = $user->hasRole('online_shop') || $user->hasRole('toko_online') || $user->online_shop_id;
        $isBranch = ($user->branch_id || !empty($user->placements)) && !$user->hasRole('super_admin') && !$user->hasRole('analist') && !$user->hasRole('audit');
        $filterType = $request->query('type', 'all'); // all, hp, non-hp

        $accessibleBranchIds = $user->getAccessibleBranchIds();
        $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();
        $isRestricted = !$user->hasRole('super_admin');

        // 1. Get all brands
        $brands = Brand::orderBy('name')->get();

        $report = $brands->map(function ($brand) use ($user, $isOnlineShop, $isBranch, $filterType, $accessibleBranchIds, $accessibleOnlineShopIds, $isRestricted) {
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

                if ($isRestricted) {
                    if (!empty($accessibleOnlineShopIds) && !empty($accessibleBranchIds)) {
                        $hpQuery->where(function($q) use ($accessibleOnlineShopIds, $accessibleBranchIds) {
                            $q->where(fn($sub) => $sub->where('product_details.placement_type', 'online_shop')->whereIn('product_details.placement_id', $accessibleOnlineShopIds))
                              ->orWhere(fn($sub) => $sub->where('product_details.placement_type', 'branch')->whereIn('product_details.placement_id', $accessibleBranchIds));
                        });
                    } elseif (!empty($accessibleOnlineShopIds)) {
                        $hpQuery->where('product_details.placement_type', 'online_shop')
                            ->whereIn('product_details.placement_id', $accessibleOnlineShopIds);
                    } elseif (!empty($accessibleBranchIds)) {
                        $hpQuery->where('product_details.placement_type', 'branch')
                            ->whereIn('product_details.placement_id', $accessibleBranchIds);
                    } else {
                        $hpQuery->whereRaw('1=0');
                    }
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

                if ($isRestricted) {
                    if (!empty($accessibleOnlineShopIds) && !empty($accessibleBranchIds)) {
                        $nonHpQuery->where(function($q) use ($accessibleOnlineShopIds, $accessibleBranchIds) {
                            $q->where(fn($sub) => $sub->where('inventories.placement_type', 'online_shop')->whereIn('inventories.placement_id', $accessibleOnlineShopIds))
                              ->orWhere(fn($sub) => $sub->where('inventories.placement_type', 'branch')->whereIn('inventories.placement_id', $accessibleBranchIds));
                        });
                    } elseif (!empty($accessibleOnlineShopIds)) {
                        $nonHpQuery->where('inventories.placement_type', 'online_shop')
                            ->whereIn('inventories.placement_id', $accessibleOnlineShopIds);
                    } elseif (!empty($accessibleBranchIds)) {
                        $nonHpQuery->where('inventories.placement_type', 'branch')
                            ->whereIn('inventories.placement_id', $accessibleBranchIds);
                    } else {
                        $nonHpQuery->whereRaw('1=0');
                    }
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
                ->get();

            $formattedHp = $hpStats->map(function ($item) {
                $displayName = $item->storage ? "{$item->product_name} ({$item->storage})" : $item->product_name;

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
        }

        // Sort A-Z strictly alphabetical and naturally (G before I, 11 before 12, case-insensitive)
        $hpSorted = collect($formattedHp)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $nonHpSorted = collect($formattedNonHp)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return response()->json([
            'data' => [
                'hp' => $hpSorted,
                'non_hp' => $nonHpSorted,
            ]
        ]);
    }
    public function getSalesReport(Request $request)
    {
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Role-based Date Restriction
        $user = $request->user();
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist', 'analis'])) {
            $today = $logicalNow->toDateString();
            $sevenDaysAgo = $logicalNow->copy()->subDays(7)->toDateString();
            $startOfThisMonth = $logicalNow->copy()->startOfMonth()->toDateString();
            $startOfLastMonth = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();

            if ($startDate && $endDate && $startDate === $endDate) {
                if ($startDate < $sevenDaysAgo) {
                    $startDate = $today;
                    $endDate = $today;
                }
            } elseif ($startDate) {
                if ($startDate < $startOfLastMonth) {
                    $startDate = $startOfThisMonth;
                }
                if (date('Y', strtotime($startDate)) < $logicalNow->format('Y')) {
                    $startDate = $startOfThisMonth;
                }
            }
        }

        $branchId = $request->query('branch_id');
        $onlineShopId = $request->query('online_shop_id');

        // Group CS stats by Petugas Stok (inventory_user_id) as requested
        $csUserIdField = 'inventory_user_id';

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        // Role-based scoping and strict isolation
        $requestedBranchId = $branchId;
        $requestedOnlineShopId = $onlineShopId;

        $accessibleBranchIds = $user->getAccessibleBranchIds();
        $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();
        $isRestricted = !$user->hasRole(['super_admin', 'analist', 'audit']);

        if ($isRestricted) {
            // Apply restrictions to the filters if they are provided
            if ($requestedBranchId && !in_array($requestedBranchId, $accessibleBranchIds)) {
                $requestedBranchId = 'FORBIDDEN';
            }
            if ($requestedOnlineShopId && !in_array($requestedOnlineShopId, $accessibleOnlineShopIds)) {
                $requestedOnlineShopId = 'FORBIDDEN';
            }

            // If no specific filter requested, default to all accessible
            if (!$requestedBranchId && !$requestedOnlineShopId) {
                $filterBranchIds = $accessibleBranchIds;
                $filterOnlineShopIds = $accessibleOnlineShopIds;
            } else {
                $filterBranchIds = $requestedBranchId === 'FORBIDDEN' ? [] : ($requestedBranchId ? [$requestedBranchId] : []);
                $filterOnlineShopIds = $requestedOnlineShopId === 'FORBIDDEN' ? [] : ($requestedOnlineShopId ? [$requestedOnlineShopId] : []);
            }
        } else {
            $filterBranchIds = $requestedBranchId ? [$requestedBranchId] : [];
            $filterOnlineShopIds = $requestedOnlineShopId ? [$requestedOnlineShopId] : [];
        }

        // Helper to apply strict user-based filters to any query joined with users
        $applyIsolation = function ($query, $ownerJoinRequired = false) use ($filterBranchIds, $filterOnlineShopIds, $isRestricted) {
            if (!$isRestricted && empty($filterBranchIds) && empty($filterOnlineShopIds)) {
                return $query;
            }

            $tableName = 'users'; // default
            if ($ownerJoinRequired) {
                // If we need to join the transaction owner to check isolation independently of the CS
                $query->join('users as owners', 'stock_outs.user_id', '=', 'owners.id');
                $tableName = 'owners';
            }

            $query->where(function($q) use ($tableName, $filterBranchIds, $filterOnlineShopIds) {
                if (!empty($filterBranchIds)) {
                    $q->orWhereIn("{$tableName}.branch_id", $filterBranchIds);
                }
                if (!empty($filterOnlineShopIds)) {
                    $q->orWhereIn("{$tableName}.online_shop_id", $filterOnlineShopIds);
                }
                
                // If restricted but no access allowed at all
                if (empty($filterBranchIds) && empty($filterOnlineShopIds)) {
                    $q->whereRaw('1=0');
                }
            });

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
        
        // Pass true to join 'owners' because 'users' here is the Petugas Stok
        $csQuery = $applyIsolation($csQuery, true);

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
        $hpCountsQuery = $applyIsolation($hpCountsQuery, true);

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
        $accCountsQuery = $applyIsolation($accCountsQuery, true);

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
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Role-based Date Restriction
        $user = $request->user();
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist', 'analis'])) {
            $today = $logicalNow->toDateString();
            $sevenDaysAgo = $logicalNow->copy()->subDays(7)->toDateString();
            $startOfThisMonth = $logicalNow->copy()->startOfMonth()->toDateString();
            $startOfLastMonth = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();

            if ($startDate && $endDate && $startDate === $endDate) {
                if ($startDate < $sevenDaysAgo) {
                    $startDate = $today;
                    $endDate = $today;
                }
            } elseif ($startDate) {
                if ($startDate < $startOfLastMonth) {
                    $startDate = $startOfThisMonth;
                }
                if (date('Y', strtotime($startDate)) < $logicalNow->format('Y')) {
                    $startDate = $startOfThisMonth;
                }
            }
        }
        
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'];

        // 1. Get Base Stats (Omset & Transaction Count)
        $branchBase = DB::table('branches')
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
                DB::raw('SUM(COALESCE(stock_outs.selling_price, 0)) as omset'),
                DB::raw('COUNT(DISTINCT stock_outs.id) as transaction_count')
            )
            ->groupBy('branches.id')
            ->get()->keyBy('id');

        // 2. Get Item Counts (Iphone vs Android)
        $branchItemCounts = DB::table('branches')
            ->leftJoin('users', 'branches.id', '=', 'users.branch_id')
            ->join('stock_outs', 'users.id', '=', 'stock_outs.user_id')
            ->join('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereNull('stock_outs.deleted_at')
            ->where('products.type', 'hp')
            ->when($startDate, fn($q) => $q->where('stock_outs.reporting_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('stock_outs.reporting_date', '<=', $endDate))
            ->select(
                'branches.id',
                DB::raw("COUNT(CASE WHEN (LOWER(products.brand) LIKE '%iphone%' OR LOWER(products.brand) LIKE '%apple%') THEN 1 END) as iphone_count"),
                DB::raw("COUNT(CASE WHEN (LOWER(products.brand) NOT LIKE '%iphone%' AND LOWER(products.brand) NOT LIKE '%apple%') THEN 1 END) as android_count")
            )
            ->groupBy('branches.id')
            ->get()->keyBy('id');

        // 3. Get Top Android Models per branch
        $branchAndroidModels = DB::table('branches')
            ->leftJoin('users', 'branches.id', '=', 'users.branch_id')
            ->join('stock_outs', 'users.id', '=', 'stock_outs.user_id')
            ->join('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereNull('stock_outs.deleted_at')
            ->where('products.type', 'hp')
            ->where(function($q) {
                $q->where(DB::raw('LOWER(products.brand)'), 'NOT LIKE', '%iphone%')
                  ->where(DB::raw('LOWER(products.brand)'), 'NOT LIKE', '%apple%');
            })
            ->when($startDate, fn($q) => $q->where('stock_outs.reporting_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('stock_outs.reporting_date', '<=', $endDate))
            ->select(
                'branches.id as branch_id',
                'products.name as model_name',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('branches.id', 'products.name')
            ->orderBy('count', 'desc')
            ->get()->groupBy('branch_id');

        $branches = DB::table('branches')
            ->where('is_active', true)
            ->where('name', 'NOT ILIKE', '%TRIAL%')
            ->where('name', 'NOT ILIKE', '%ANU%')
            ->where('name', 'NOT ILIKE', '%TESTING%')
            ->where('name', 'NOT ILIKE', '%HUFT%')
            ->get();
        $branchStats = $branches->map(function($b) use ($branchBase, $branchItemCounts, $branchAndroidModels) {
            $base = $branchBase[$b->id] ?? null;
            $items = $branchItemCounts[$b->id] ?? null;
            $topModels = $branchAndroidModels->get($b->id)?->take(3)->pluck('model_name')->toArray() ?? [];
            
            return (object) [
                'id' => $b->id,
                'name' => $b->name,
                'type' => 'Offline',
                'omset' => $base ? (float) $base->omset : 0,
                'transaction_count' => $base ? (int) $base->transaction_count : 0,
                'iphone_count' => $items ? (int) $items->iphone_count : 0,
                'android_count' => $items ? (int) $items->android_count : 0,
                'top_android_models' => $topModels
            ];
        });

        // 4. Get Online Shop Stats
        $onlineBase = DB::table('online_shops')
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
                DB::raw('SUM(COALESCE(stock_outs.selling_price, 0)) as omset'),
                DB::raw('COUNT(DISTINCT stock_outs.id) as transaction_count')
            )
            ->groupBy('online_shops.id')
            ->get()->keyBy('id');

        $onlineItemCounts = DB::table('online_shops')
            ->leftJoin('users', 'online_shops.id', '=', 'users.online_shop_id')
            ->join('stock_outs', 'users.id', '=', 'stock_outs.user_id')
            ->join('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereNull('stock_outs.deleted_at')
            ->where('products.type', 'hp')
            ->when($startDate, fn($q) => $q->where('stock_outs.reporting_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('stock_outs.reporting_date', '<=', $endDate))
            ->select(
                'online_shops.id',
                DB::raw("COUNT(CASE WHEN (LOWER(products.brand) LIKE '%iphone%' OR LOWER(products.brand) LIKE '%apple%') THEN 1 END) as iphone_count"),
                DB::raw("COUNT(CASE WHEN (LOWER(products.brand) NOT LIKE '%iphone%' AND LOWER(products.brand) NOT LIKE '%apple%') THEN 1 END) as android_count")
            )
            ->groupBy('online_shops.id')
            ->get()->keyBy('id');

        $onlineAndroidModels = DB::table('online_shops')
            ->leftJoin('users', 'online_shops.id', '=', 'users.online_shop_id')
            ->join('stock_outs', 'users.id', '=', 'stock_outs.user_id')
            ->join('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereNull('stock_outs.deleted_at')
            ->where('products.type', 'hp')
            ->where(function($q) {
                $q->where(DB::raw('LOWER(products.brand)'), 'NOT LIKE', '%iphone%')
                  ->where(DB::raw('LOWER(products.brand)'), 'NOT LIKE', '%apple%');
            })
            ->when($startDate, fn($q) => $q->where('stock_outs.reporting_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('stock_outs.reporting_date', '<=', $endDate))
            ->select(
                'online_shops.id as shop_id',
                'products.name as model_name',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('online_shops.id', 'products.name')
            ->orderBy('count', 'desc')
            ->get()->groupBy('shop_id');

        $shops = DB::table('online_shops')
            ->where('is_active', true)
            ->where('name', 'NOT ILIKE', '%TRIAL%')
            ->where('name', 'NOT ILIKE', '%ANU%')
            ->where('name', 'NOT ILIKE', '%TESTING%')
            ->where('name', 'NOT ILIKE', '%HUFT%')
            ->get();
        $onlineStats = $shops->map(function($s) use ($onlineBase, $onlineItemCounts, $onlineAndroidModels) {
            $base = $onlineBase[$s->id] ?? null;
            $items = $onlineItemCounts[$s->id] ?? null;
            $topModels = $onlineAndroidModels->get($s->id)?->take(3)->pluck('model_name')->toArray() ?? [];
            
            return (object) [
                'id' => $s->id,
                'name' => $s->name,
                'type' => 'Online',
                'omset' => $base ? (float) $base->omset : 0,
                'transaction_count' => $base ? (int) $base->transaction_count : 0,
                'iphone_count' => $items ? (int) $items->iphone_count : 0,
                'android_count' => $items ? (int) $items->android_count : 0,
                'top_android_models' => $topModels
            ];
        });

        $accessibleBranchIds = $user->getAccessibleBranchIds();
        $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();
        $isRestricted = !$user->hasRole('super_admin') && !$user->hasRole('analist');

        $report = $branchStats->concat($onlineStats);
        
        // Apply scope to the final collection
        if ($isRestricted) {
            $report = $report->filter(function($item) use ($accessibleBranchIds, $accessibleOnlineShopIds) {
                if ($item->type === 'Offline') {
                    return in_array($item->id, $accessibleBranchIds);
                } else {
                    return in_array($item->id, $accessibleOnlineShopIds);
                }
            });
        }
        
        $includeZero = $request->boolean('include_zero', false);
        
        if (!$includeZero) {
            $report = $report->filter(fn($item) => $item->omset > 0);
        }

        $report = $report->sortByDesc('omset')->values();

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

    public function getStockHistory(Request $request)
    {
        try {
            $user = $request->user();
            $branchId = $request->query('branch_id');
            $onlineShopId = $request->query('online_shop_id');
            $warehouseId = $request->query('warehouse_id');
            $date = $request->query('date', now()->toDateString());
            $mode = $request->query('mode', 'daily');

            $targetDate = $date ? \Carbon\Carbon::parse($date) : now();
            
            // Limit access for regular staff (2 months audit window)
            $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner', 'analist', 'audit'];
            if ($targetDate->diffInDays(now()) > 60 && !$user->hasRole($unrestrictedRoles)) {
                return response()->json(['error' => 'Anda hanya bisa melihat history stok 60 hari terakhir.'], 403);
            }

            if ($mode === 'monthly') {
                $resetTime = $targetDate->copy()->startOfMonth()->setTime(5, 0, 0);
            } else {
                $resetTime = $targetDate->copy()->setTime(5, 0, 0);
            }
            
            $isRestricted = !$user->hasRole($unrestrictedRoles);
            $accessibleBranchIds = $user->getAccessibleBranchIds();
            $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();

            $filterBranchIds = $branchId ? [$branchId] : ($isRestricted ? $accessibleBranchIds : []);
            $filterOnlineShopIds = $onlineShopId ? [$onlineShopId] : ($isRestricted ? $accessibleOnlineShopIds : []);
            $filterWarehouseIds = $warehouseId ? [$warehouseId] : [];

            // 1. Determine Logical Shift Date (Cutoff 5 AM)
            $now = now();
            if (!$date) {
                // If it's before 5 AM, default to "Yesterday" shift
                $targetDate = $now->hour < 5 ? $now->copy()->subDay() : $now->copy();
            } else {
                $targetDate = \Carbon\Carbon::parse($date);
            }

            $mode = $request->query('mode', 'daily'); // daily or monthly

            if ($mode === 'monthly') {
                $resetTime = $targetDate->copy()->startOfMonth()->setTime(5, 0, 0);
                $endTime = $resetTime->copy()->addMonth();
            } else {
                $resetTime = $targetDate->copy()->setTime(5, 0, 0);
                $endTime = $resetTime->copy()->addDay();
            }

            $results = [];
            
            // 2. Get CURRENT REAL-TIME STOCK (as the reference "All Time" balance)
            $currentStock = \App\Models\ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
                ->select(
                    'products.id as product_id',
                    'products.brand',
                    'products.name as product_name',
                    'products.type',
                    'products.has_imei',
                    'product_details.storage',
                    'product_details.condition',
                    \DB::raw('count(*) as qty')
                )
                ->where('product_details.status', 'available');

            if (!empty($filterBranchIds)) $currentStock->whereIn('product_details.placement_id', $filterBranchIds)->where('product_details.placement_type', 'branch');
            elseif (!empty($filterOnlineShopIds)) $currentStock->whereIn('product_details.placement_id', $filterOnlineShopIds)->where('product_details.placement_type', 'online_shop');
            elseif (!empty($filterWarehouseIds)) $currentStock->whereIn('product_details.placement_id', $filterWarehouseIds)->where('product_details.placement_type', 'warehouse');

            $currentStock->groupBy('products.id', 'products.brand', 'products.name', 'products.type', 'products.has_imei', 'product_details.storage', 'product_details.condition');

            $soldCategories = ['penjualan_offline', 'shopee', 'orderan_online', 'penjualan_store', 'bundling'];
            $keluarCategories = ['giveaway_customer', 'hadiah', 'brand_ambassador', 'event_sponsorship', 'promo', 'inventaris', 'hilang'];
            $incomingAuditCategories = ['barang_masuk', 'pembelian', 'cancel_penjualan', 'retur_customer'];

            $defaultRow = [
                'initial' => 0, 
                'in_total' => 0, 'in_manual' => 0, 'in_tt' => 0, 'in_tu' => 0, 'in_dw' => 0, 'in_rf' => 0, 'in_ab' => 0,
                'out_total' => 0, 'out_sold' => 0, 'out_tt' => 0, 'out_tu' => 0, 'out_dw' => 0, 'out_pindah' => 0, 'out_kesalahan' => 0, 'out_keluar' => 0, 'out_hilang' => 0, 'out_retur' => 0,
                'final' => 0
            ];

            // Helper function for consistent naming and grouping
            $normalize = function($brand, $name, $storage, $condition) {
                $b = trim($brand ?? '');
                $n = trim($name ?? '');
                $s = trim($storage ?? '');
                $c = trim($condition ?? 'second');
                
                // Remove weird unicode spaces and standard spaces into a single space
                $b = trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $b));
                $n = trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $n));
                $s = trim(preg_replace('/[\xA0\s]+/', ' ', $s));
                
                $dispName = "{$b} {$n}";
                // Standardize multiple spaces and remove any ™ symbols that might differ
                $dispName = trim(preg_replace('/\s+/', ' ', str_replace('™', '', $dispName)));
                
                if ($s) $dispName .= " ({$s})";
                $dispName .= " (" . ($c === 'new' ? 'Baru' : ($c === 'ex_ibox' ? 'Ex iBox' : 'Bekas')) . ")";
                
                return [
                    'display' => $dispName,
                    // Bulletproof key: only letters and numbers
                    'key' => md5(preg_replace('/[^a-z0-9]/', '', strtolower($dispName)))
                ];
            };

            // 2. Initial Data from Current Stock
            foreach($currentStock->get() as $s) {
                $norm = $normalize($s->brand, $s->product_name, $s->storage, $s->condition);
                $groupKey = $norm['key'];

                if (!isset($results[$groupKey])) {
                    $results[$groupKey] = array_merge($defaultRow, [
                        'name' => $norm['display'],
                        'type' => $s->type ?? ($s->has_imei ? 'hp' : 'non-hp'),
                        'has_imei' => $s->has_imei,
                        'debug_key_1' => $groupKey
                    ]);
                }
                $results[$groupKey]['final'] += $s->qty;
            }

            // 3. Mutations (In - InventoryLog)
            $dayLogs = \App\Models\InventoryLog::with('productDetail.product')
                ->where('created_at', '>=', $resetTime)
                ->where('created_at', '<', $endTime)
                ->where('type', 'in');
            if (!empty($filterBranchIds)) $dayLogs->whereIn('branch_id', $filterBranchIds);
            elseif (!empty($filterOnlineShopIds)) $dayLogs->where('online_shop_id', $filterOnlineShopIds[0]);

            foreach($dayLogs->get() as $log) {
                if ($log->description && (str_contains($log->description, 'Pindah Cabang') || str_contains($log->description, 'Resi:'))) continue;
                $pd = $log->productDetail;
                if (!$pd) continue;

                $norm = $normalize($pd->product->brand ?? '', $pd->product->name ?? '', $pd->storage, $pd->condition);
                $groupKey = $norm['key'];

                if (!isset($results[$groupKey])) {
                    $results[$groupKey] = array_merge($defaultRow, [
                        'name' => $norm['display'],
                        'type' => $pd->product->type ?? ($pd->product->has_imei ? 'hp' : 'non-hp'),
                        'has_imei' => $pd->product->has_imei,
                        'debug_key_2' => $groupKey
                    ]);
                }
                
                $qty = ($log->quantity ?? 1);
                $results[$groupKey]['in_total'] += $qty;
                $desc = strtoupper($log->description ?? '');
                if (str_contains($desc, 'TUKAR TAMBAH') || str_contains($desc, ' TT')) $results[$groupKey]['in_tt'] += $qty;
                elseif (str_contains($desc, 'TUKAR UNIT') || str_contains($desc, ' TU')) $results[$groupKey]['in_tu'] += $qty;
                elseif (str_contains($desc, 'ANGKAT BARANG') || str_contains($desc, ' AB')) $results[$groupKey]['in_ab'] += $qty;
                else $results[$groupKey]['in_manual'] += $qty;
            }

            // 4. Mutations (Out and Incoming StockOuts)
            $dayOuts = StockOut::with(['items.product', 'nonHpItems.product'])
                ->where('created_at', '>=', $resetTime)
                ->where('created_at', '<', $endTime)
                ->where('status', '!=', 'cancelled');
            
            if (!empty($filterBranchIds)) {
                $dayOuts->where(function($q) use ($filterBranchIds) {
                    $q->whereIn('branch_id', $filterBranchIds)->orWhere('destination_id', $filterBranchIds);
                });
            }

            foreach($dayOuts->get() as $out) {
                $isAB = $out->category === 'angkat_barang';
                $isIncoming = in_array($out->category, $incomingAuditCategories) || $isAB;
                
                foreach($out->items as $pd) {
                    $norm = $normalize($pd->product->brand ?? '', $pd->product->name ?? '', $pd->storage, $pd->condition);
                    $groupKey = $norm['key'];

                    if (!isset($results[$groupKey])) {
                        $results[$groupKey] = array_merge($defaultRow, [
                            'name' => $norm['display'],
                            'type' => $pd->product->type ?? ($pd->product->has_imei ? 'hp' : 'non-hp'),
                            'has_imei' => $pd->product->has_imei,
                            'debug_key_3' => $groupKey
                        ]);
                    }
                    
                    if ($isIncoming) {
                        $results[$groupKey]['in_total']++;
                        if ($isAB) $results[$groupKey]['in_ab']++;
                        else $results[$groupKey]['in_manual']++;
                    } else {
                        $results[$groupKey]['out_total']++;
                        $cat = $out->category;
                        if (in_array($cat, $soldCategories)) $results[$groupKey]['out_sold']++;
                        elseif ($cat === 'retur' || $cat === 'refund') $results[$groupKey]['out_retur']++;
                        else $results[$groupKey]['out_keluar']++;
                    }
                }

                foreach($out->nonHpItems as $nhi) {
                    $norm = $normalize($nhi->product->brand ?? '', $nhi->product->name ?? '', null, null);
                    $groupKey = $norm['key'];

                    if (!isset($results[$groupKey])) {
                        $results[$groupKey] = array_merge($defaultRow, [
                            'name' => $norm['display'],
                            'type' => $nhi->product->type ?? 'non-hp',
                            'has_imei' => false,
                            'debug_key_4' => $groupKey
                        ]);
                    }
                    $qty = $nhi->quantity;
                    if ($isIncoming) {
                        $results[$groupKey]['in_total'] += $qty;
                        $results[$groupKey]['in_manual'] += $qty;
                    } else {
                        $results[$groupKey]['out_total'] += $qty;
                        if (in_array($out->category, $soldCategories)) $results[$groupKey]['out_sold'] += $qty;
                        else $results[$groupKey]['out_keluar'] += $qty;
                    }
                }
            }

            // 5. Final Calculation: Initial = Final - In + Out
            foreach ($results as &$row) {
                $row['initial'] = $row['final'] - $row['in_total'] + $row['out_total'];
            }

            $hpData = [];
            $nonHpData = [];
            
            foreach ($results as $row) {
                if ($row['initial'] == 0 && $row['in_total'] == 0 && $row['out_total'] == 0 && $row['final'] == 0) continue;

                $pNameLower = strtolower($row['name']);
                $isHp = ($row['has_imei'] ?? false) || ($row['type'] ?? '') === 'hp' || str_contains($pNameLower, 'baru)') || str_contains($pNameLower, 'bekas)') || str_contains($pNameLower, 'gb)');
                
                if (str_contains($pNameLower, 'jasa') || str_contains($pNameLower, 'service')) $isHp = false;

                if ($isHp) $hpData[] = $row;
                else $nonHpData[] = $row;
            }

            // Sort by name
            usort($hpData, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            usort($nonHpData, fn($a, $b) => strcasecmp($a['name'], $b['name']));

            return response()->json([
                'status' => 'success',
                'reset_time_label' => $resetTime->format('H:i d/m/Y'),
                'data' => [
                    'hp' => $hpData,
                    'non_hp' => $nonHpData
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Stock History Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function exportStockMovement(Request $request)
    {
        try {
            $user = $request->user();
            $response = $this->getStockHistory($request);
            $data = json_decode($response->getContent(), true);
            
            if (!$data || !isset($data['data'])) {
                 return response()->json(['error' => 'Data tidak ditemukan atau error di server.'], 500);
            }

            $hpItems = data_get($data, 'data.hp', []);
            $nonHpItems = data_get($data, 'data.non_hp', []);
            
            // Filter by type if requested
            $type = $request->query('type');
            if ($type === 'hp') {
                $items = $hpItems;
                $titleSuffix = 'UNIT HP (IMEI)';
            } elseif ($type === 'non_hp') {
                $items = $nonHpItems;
                $titleSuffix = 'NON-HP (AKSESORIS)';
            } else {
                $items = array_merge($hpItems, $nonHpItems);
                $titleSuffix = 'SEMUA BARANG';
            }
            
            // Sort A-Z naturally (11 before 12, case-insensitive)
            $items = collect($items)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->toArray();
            
            $filename = 'LAPORAN_MUTASI_STOK_' . now()->format('d-m-Y_H-i') . '.xlsx';

            // Log export
            ExportLog::create([
                'user_id' => $user->id,
                'report_name' => 'Laporan Barang Keluar Masuk',
                'filename' => $filename,
                'params' => [
                    'branch_id' => $request->query('branch_id'),
                    'online_shop_id' => $request->query('online_shop_id'),
                    'date' => $request->query('date'),
                    'mode' => $request->query('mode'),
                    'type' => $type
                ]
            ]);

            $xlsxData = [
                ['LAPORAN MUTASI STOK ' . $titleSuffix . ' (' . ($request->query('mode') === 'monthly' ? 'Bulanan' : 'Harian') . ')'],
                [
                    'Nama Produk', 'Awal (All-Time)', 'Masuk (Total)', 'Manual', 'TT (In)', 'TU (In)', 'DW (In)', 'RF (In)', 'AB (In)', 
                    'Keluar (Total)', 'Terjual', 'TT (Out)', 'TU (Out)', 'DW (Out)', 'Lainnya', 'Retur', 'Sisa (All-Time)'
                ]
            ];

            foreach ($items as $row) {
                $lainnya = ($row['out_pindah'] ?? 0) + ($row['out_kesalahan'] ?? 0) + ($row['out_keluar'] ?? 0) + ($row['out_hilang'] ?? 0);
                $xlsxData[] = [
                    $row['name'] ?? '-', 
                    $row['initial'] ?? 0, 
                    $row['in_total'] ?? 0, 
                    $row['in_manual'] ?? 0, 
                    $row['in_tt'] ?? 0, 
                    $row['in_tu'] ?? 0, 
                    $row['in_dw'] ?? 0, 
                    $row['in_rf'] ?? 0, 
                    $row['in_ab'] ?? 0,
                    $row['out_total'] ?? 0,
                    $row['out_sold'] ?? 0,
                    $row['out_tt'] ?? 0,
                    $row['out_tu'] ?? 0,
                    $row['out_dw'] ?? 0,
                    $lainnya,
                    $row['out_retur'] ?? 0,
                    $row['final'] ?? 0
                ];
            }

            return response((string)SimpleXLSXGen::fromArray($xlsxData), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            Log::error('Export Stock Error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    public function getDownloadHistory(Request $request)
    {
        $history = ExportLog::with('user')->latest()->take(50)->get();
        return response()->json($history);
    }
}
