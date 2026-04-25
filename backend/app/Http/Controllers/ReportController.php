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
                ->orderBy('products.brand')
                ->orderBy('products.name')
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

        $items = $report->toArray();
        usort($items, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return response()->json($items);
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

            $defaultRow = [
                'initial' => 0, 
                'in_total' => 0, 'in_manual' => 0, 'in_tt' => 0, 'in_tu' => 0, 'in_dw' => 0, 'in_rf' => 0, 'in_ab' => 0,
                'out_total' => 0, 'out_sold' => 0, 'out_tt' => 0, 'out_tu' => 0, 'out_dw' => 0, 'out_pindah' => 0, 'out_kesalahan' => 0, 'out_keluar' => 0, 'out_hilang' => 0, 'out_retur' => 0,
                'final' => 0
            ];

            foreach($currentStock->get() as $s) {
                $key = "{$s->product_id}:{$s->storage}:{$s->condition}";
                $results[$key] = array_merge($defaultRow, [
                    'name' => "{$s->brand} {$s->product_name} " . ($s->storage ? "({$s->storage}) " : "") . "(" . ($s->condition === 'new' ? 'Baru' : ($s->condition === 'ex_ibox' ? 'Ex iBox' : 'Bekas')) . ")",
                    'type' => $s->type ?? ($s->has_imei ? 'hp' : 'non-hp'),
                    'has_imei' => $s->has_imei,
                    'initial' => $s->qty, 
                    'final' => $s->qty
                ]);
            }

            // 3. Get Mutations for the selected date window (Reset 05:00 AM)
            $dayLogs = \App\Models\InventoryLog::where('created_at', '>=', $resetTime)
                ->where('created_at', '<', $endTime)
                ->where('type', 'in');
            if (!empty($filterBranchIds)) $dayLogs->whereIn('branch_id', $filterBranchIds);
            elseif (!empty($filterOnlineShopIds)) $dayLogs->where('online_shop_id', $filterOnlineShopIds[0]); // Explicitly use first if single
            elseif (!empty($filterWarehouseIds)) $dayLogs->where('warehouse_id', $filterWarehouseIds[0]);

            foreach($dayLogs->get() as $log) {
                // Skip logs that are already accounted for in StockOut transactions (to avoid double counting)
                // Transfers and Audits usually have a mention of the receipt_id or "Pindah Cabang" in the description
                if ($log->description && (
                    str_contains($log->description, 'Pindah Cabang') || 
                    str_contains($log->description, 'Resi:') ||
                    str_contains($log->description, 'Nota:')
                )) {
                    continue;
                }

                // Try finding the specific product detail
                $pd = null;
                if (is_numeric($log->reference_id)) {
                    $pd = ProductDetail::find($log->reference_id);
                }
                
                // If not found by ID (e.g. description has imei in parens), try finding by imei
                if (!$pd && $log->description && preg_match('/\((.*?)\)/', $log->description, $matches)) {
                    $imei = trim($matches[1]);
                    $pd = ProductDetail::where('imei', $imei)->first();
                }

                if (!$pd) continue;
                $key = "{$pd->product_id}:{$pd->storage}:{$pd->condition}";
                if (!isset($results[$key])) {
                    $results[$key] = array_merge($defaultRow, [
                        'name' => ($pd->product->brand ?? '') . ' ' . ($pd->product->name ?? '') . " " . ($pd->storage ? "({$pd->storage}) " : "") . "(" . ($pd->condition === 'new' ? 'Baru' : ($pd->condition === 'ex_ibox' ? 'Ex iBox' : 'Bekas')) . ")",
                        'type' => $pd->product->type ?? ($pd->product->has_imei ? 'hp' : 'non-hp'),
                        'has_imei' => $pd->product->has_imei,
                    ]);
                }
                
                $qty = ($log->quantity ?? 1);
                $results[$key]['in_total'] += $qty;
                $desc = strtoupper($log->description ?? '');
                
                if (str_contains($desc, 'TUKAR TAMBAH') || str_contains($desc, ' TT') || str_contains($desc, 'TRADE-IN') || str_contains($desc, 'TRADE IN')) $results[$key]['in_tt'] += $qty;
                elseif (str_contains($desc, 'TUKAR UNIT') || str_contains($desc, ' TU') || str_contains($desc, 'UNIT EXCHANGE') || str_contains($desc, 'EXCHANGE') || str_contains($desc, ' UE')) $results[$key]['in_tu'] += $qty;
                elseif (str_contains($desc, 'DOWNGRADE') || str_contains($desc, ' DW') || str_contains($desc, ' DG')) $results[$key]['in_dw'] += $qty;
                elseif (str_contains($desc, 'REFUND') || str_contains($desc, ' RF')) $results[$key]['in_rf'] += $qty;
                elseif (str_contains($desc, 'ANGKAT BARANG') || str_contains($desc, ' AB') || str_contains($desc, 'AUDIT')) $results[$key]['in_ab'] += $qty;
                else $results[$key]['in_manual'] += $qty;
            }

            // Outgoing and Audit Incoming during day
            $soldCategories = ['penjualan_offline', 'shopee', 'orderan_online', 'penjualan_store', 'bundling'];
            $keluarCategories = ['giveaway_customer', 'hadiah', 'brand_ambassador', 'event_sponsorship', 'promo', 'inventaris'];
            $incomingAuditCategories = ['barang_masuk', 'pembelian', 'cancel_penjualan', 'retur_customer'];

            $dayOuts = StockOut::with(['items.product', 'nonHpItems.product'])
                ->where('created_at', '>=', $resetTime)
                ->where('created_at', '<', $endTime)
                ->where('status', '!=', 'cancelled');
            
            if (!empty($filterBranchIds)) {
                $dayOuts->where(function($q) use ($filterBranchIds) {
                    $q->whereIn('branch_id', $filterBranchIds)->orWhere('destination_id', $filterBranchIds);
                });
            } elseif (!empty($filterOnlineShopIds)) {
                $dayOuts->whereIn('online_shop_id', $filterOnlineShopIds);
            } elseif (!empty($filterWarehouseIds)) {
                $dayOuts->whereIn('warehouse_id', $filterWarehouseIds);
            }

            foreach($dayOuts->get() as $out) {
                // An item is incoming if its category is in incoming list
                // OR if it's a transfer where the CURRENT branch is the DESTINATION
                $isIncoming = in_array($out->category, $incomingAuditCategories);
                
                if (!$isIncoming && $out->category === 'pindah_cabang') {
                    if (!empty($filterBranchIds) && in_array($out->destination_id, $filterBranchIds)) {
                        $isIncoming = true;
                    } elseif (!empty($filterOnlineShopIds) && $out->destination_type === 'online_shop' && in_array($out->destination_id, $filterOnlineShopIds)) {
                        $isIncoming = true;
                    } elseif (!empty($filterWarehouseIds) && $out->destination_type === 'warehouse' && in_array($out->destination_id, $filterWarehouseIds)) {
                        $isIncoming = true;
                    }
                }

                foreach($out->items as $pd) {
                    // Filter out non-physical products (Services, Revenue Categories)
                    $pName = strtolower($pd->product->name ?? '');
                    if (str_contains($pName, 'omset') || str_contains($pName, 'jasa') || str_contains($pName, 'virtual')) continue;

                    $key = "{$pd->product_id}:{$pd->storage}:{$pd->condition}";
                    if (!isset($results[$key])) {
                        $results[$key] = array_merge($defaultRow, [
                            'name' => ($pd->product->brand ?? '') . ' ' . ($pd->product->name ?? '') . " " . ($pd->storage ? "({$pd->storage}) " : "") . "(" . ($pd->condition === 'new' ? 'Baru' : ($pd->condition === 'ex_ibox' ? 'Ex iBox' : 'Bekas')) . ")",
                            'type' => $pd->product->type ?? ($pd->product->has_imei ? 'hp' : 'non-hp'),
                            'has_imei' => $pd->product->has_imei,
                        ]);
                    }
                    
                    $cat = $out->category;

                    if ($isIncoming) {
                        $results[$key]['in_total']++;
                        $results[$key]['in_manual']++;
                    } else {
                        $results[$key]['out_total']++;
                        if (in_array($cat, $soldCategories)) $results[$key]['out_sold']++;
                        elseif ($cat === 'tukar_tambah') $results[$key]['out_tt']++;
                        elseif ($cat === 'tukar_unit') $results[$key]['out_tu']++;
                        elseif ($cat === 'downgrade') $results[$key]['out_dw']++;
                        elseif ($cat === 'pindah_cabang') $results[$key]['out_pindah']++;
                        elseif ($cat === 'kesalahan_input') $results[$key]['out_kesalahan']++;
                        elseif ($cat === 'hilang') $results[$key]['out_hilang']++;
                        elseif (in_array($cat, ['retur', 'refund'])) $results[$key]['out_retur']++;
                        elseif (in_array($cat, $keluarCategories) || $cat === 'keluar' || $cat === 'angkat_barang') $results[$key]['out_keluar']++;
                        else $results[$key]['out_keluar']++;
                    }
                }

                // Handle Non-HP
                foreach($out->nonHpItems as $nhi) {
                    $pName = strtolower($nhi->product->name ?? '');
                    if (str_contains($pName, 'omset') || str_contains($pName, 'jasa') || str_contains($pName, 'virtual')) continue;

                    $key = "{$nhi->product_id}::";
                    if (!isset($results[$key])) {
                        $results[$key] = array_merge($defaultRow, [
                            'name' => ($nhi->product->brand ?? '') . ' ' . ($nhi->product->name ?? ''),
                            'type' => $nhi->product->type ?? 'non-hp',
                            'has_imei' => false,
                        ]);
                    }
                    $qty = $nhi->quantity;
                    if ($isIncoming) {
                        $results[$key]['in_total'] += $qty;
                        $results[$key]['in_manual'] += $qty;
                    } else {
                        $results[$key]['out_total'] += $qty;
                        if (in_array($out->category, $soldCategories)) $results[$key]['out_sold'] += $qty;
                        elseif ($out->category === 'pindah_cabang') $results[$key]['out_pindah'] += $qty;
                        else $results[$key]['out_keluar'] += $qty;
                    }
                }
            }

            // 4. Calculate Final and Initial Balances based on Mutations
            // Goal: Awal + Masuk - Keluar = Akhir
            
            // To be accurate, we need to know the state at ResetTime (Start of selected range)
            // Balance(Reset) = CurrentStock - (All Ins from Reset until NOW) + (All Outs from Reset until NOW)
            
            // 4a. Get Current Realtime Stock for all products in results
            $warehouseId = $request->query('warehouse_id');
            $pIds = array_unique(array_map(fn($k) => (int)explode(':', $k)[0], array_keys($results)));
            $realtimeStocks = ProductDetail::whereIn('product_id', $pIds)
                ->where('status', 'available')
                ->where(function ($q) use ($branchId, $onlineShopId, $warehouseId) {
                    if ($branchId) $q->where('placement_id', $branchId)->where('placement_type', 'branch');
                    elseif ($onlineShopId) $q->where('placement_id', $onlineShopId)->where('placement_type', 'online_shop');
                    elseif ($warehouseId) $q->where('placement_id', $warehouseId)->where('placement_type', 'warehouse');
                })
                ->selectRaw('product_id, storage, condition, count(*) as qty')
                ->groupBy('product_id', 'storage', 'condition')
                ->get()
                ->keyBy(fn($s) => "{$s->product_id}:{$s->storage}:{$s->condition}");

            // 4b. Get All Mutations from ResetTime until NOW (to calculate Initial Balance)
            $allLogsSinceReset = \App\Models\InventoryLog::where('created_at', '>=', $resetTime);
            if (!empty($filterBranchIds)) $allLogsSinceReset->whereIn('branch_id', $filterBranchIds);
            elseif (!empty($filterOnlineShopIds)) $allLogsSinceReset->whereIn('online_shop_id', $filterOnlineShopIds);
            elseif (!empty($filterWarehouseIds)) $allLogsSinceReset->where('warehouse_id', $filterWarehouseIds[0]);
            
            $netMutationsSinceReset = []; // key -> net change
            foreach ($allLogsSinceReset->get() as $log) {
                $pd = null;
                if (is_numeric($log->reference_id)) {
                    $pd = ProductDetail::find($log->reference_id);
                }
                if (!$pd && $log->description && preg_match('/\((.*?)\)/', $log->description, $matches)) {
                    $pd = ProductDetail::where('imei', trim($matches[1]))->first();
                }
                if (!$pd) continue;
                
                $k = "{$pd->product_id}:{$pd->storage}:{$pd->condition}";
                if (!isset($netMutationsSinceReset[$k])) $netMutationsSinceReset[$k] = 0;
                
                // In adds to current stock, so to get initial we subtract it
                // Out removes from current stock, so to get initial we add it back
                if ($log->type === 'in') $netMutationsSinceReset[$k]++;
                else $netMutationsSinceReset[$k]--;
            }

            // 4c. Fetch Total Cumulative In (All Time) for each product/location
            $cumulativeIn = [];
            
            // From Inventory Logs
            $allTimeInLogs = \App\Models\InventoryLog::where('type', 'in');
            if (!empty($filterBranchIds)) $allTimeInLogs->whereIn('branch_id', $filterBranchIds);
            elseif (!empty($filterOnlineShopIds)) $allTimeInLogs->whereIn('online_shop_id', $filterOnlineShopIds);
            elseif (!empty($filterWarehouseIds)) $allTimeInLogs->whereIn('warehouse_id', $filterWarehouseIds);
            
            foreach ($allTimeInLogs->get() as $log) {
                // Skip logs that are already accounted for in StockOut transactions (to avoid double counting)
                if ($log->description && (
                    str_contains($log->description, 'Pindah Cabang') || 
                    str_contains($log->description, 'Resi:') ||
                    str_contains($log->description, 'Nota:')
                )) {
                    continue;
                }

                $pd = is_numeric($log->reference_id) ? ProductDetail::find($log->reference_id) : null;
                if (!$pd) continue;
                $k = "{$pd->product_id}:{$pd->storage}:{$pd->condition}";
                $cumulativeIn[$k] = ($cumulativeIn[$k] ?? 0) + ($log->quantity ?? 1);
            }
            
            // From All Incoming StockOuts (pembelian, barang_masuk, pindah_cabang destination)
            $allTimeInStockOuts = StockOut::with('items')
                ->where(function($q) use ($filterBranchIds, $filterOnlineShopIds, $filterWarehouseIds, $incomingAuditCategories) {
                    $q->where(function($sq) use ($filterBranchIds, $filterOnlineShopIds, $filterWarehouseIds, $incomingAuditCategories) {
                        // Regular Incoming (Audit/Purchase)
                        $sq->whereIn('category', $incomingAuditCategories);
                        if (!empty($filterBranchIds)) $sq->whereIn('branch_id', $filterBranchIds);
                        elseif (!empty($filterOnlineShopIds)) $sq->whereIn('online_shop_id', $filterOnlineShopIds);
                        elseif (!empty($filterWarehouseIds)) $sq->whereIn('warehouse_id', $filterWarehouseIds);
                    })
                    ->orWhere(function($sq) use ($filterBranchIds, $filterOnlineShopIds, $filterWarehouseIds) {
                        // Incoming Transfers
                        $sq->where('category', 'pindah_cabang');
                        if (!empty($filterBranchIds)) $sq->whereIn('destination_id', $filterBranchIds);
                        elseif (!empty($filterOnlineShopIds)) $sq->where('destination_type', 'online_shop')->whereIn('destination_id', $filterOnlineShopIds);
                        elseif (!empty($filterWarehouseIds)) $sq->where('destination_type', 'warehouse')->whereIn('destination_id', $filterWarehouseIds);
                    });
                })->get();

            foreach ($allTimeInStockOuts as $out) {
                foreach ($out->items as $pd) {
                    $k = "{$pd->product_id}:{$pd->storage}:{$pd->condition}";
                    $cumulativeIn[$k] = ($cumulativeIn[$k] ?? 0) + 1;
                }
            }

            foreach ($results as $key => &$row) {
                $currentQty = $realtimeStocks[$key]->qty ?? 0;
                $totalIn = $cumulativeIn[$key] ?? 0;
                
                // FINAL FIX per USER REQUEST:
                $row['initial'] = $totalIn;     // SEMUA YANG PERNAH MASUK
                $row['final'] = $currentQty;    // SISA SEKARANG
                
                $pName = strtolower($row['name']);
                if (str_contains($pName, 'omset') || str_contains($pName, 'virtual') || str_contains($pName, 'penjualan ')) {
                    unset($results[$key]);
                    continue;
                }
            }

            $hpData = [];
            $nonHpData = [];
            
            foreach ($results as $row) {
                $pName = strtolower($row['name']);
                
                // CRITICAL: Use has_imei flag if available, fallback to explicit type or name pattern
                $hasImei = $row['has_imei'] ?? false;
                $isHpType = ($row['type'] ?? '') === 'hp';
                $isHpPattern = str_contains($pName, 'baru)') || str_contains($pName, 'bekas)') || str_contains($pName, 'gb)');
                
                $isHp = $hasImei || $isHpType || $isHpPattern;

                // EXCEPTIONS: Services/Services/Specific brands that are never HP
                if (str_contains($pName, 'jasa') || str_contains($pName, 'service') || str_contains($pName, 'arcis')) {
                    $isHp = false;
                }

                if ($isHp) {
                    $hpData[] = $row;
                } else {
                    $nonHpData[] = $row;
                }
            }

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
            
            $hpItems = $data['data']['hp'] ?? [];
            $nonHpItems = $data['data']['non_hp'] ?? [];
            
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
            
            // Sort A-Z
            usort($items, function($a, $b) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            });
            
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
