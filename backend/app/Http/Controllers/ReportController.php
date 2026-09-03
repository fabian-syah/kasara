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
        $isRestricted = !$user->hasRole('super_admin') && !$user->hasRole('analist');
        $isAnalistOnly = $user->hasRole('analist') && !$user->hasRole('super_admin');
        $excludedKeywords = (array) config('kasara.excluded_keywords', []);

        // 1. Get all brands
        $brands = Brand::orderBy('name')->get();

        $report = $brands->map(function ($brand) use ($user, $isOnlineShop, $isBranch, $filterType, $accessibleBranchIds, $accessibleOnlineShopIds, $isRestricted, $isAnalistOnly, $excludedKeywords, $request) {
            $cleanBrand = trim(preg_replace('/[^\w\s]/u', '', $brand->name));
            if (empty($cleanBrand)) {
                $cleanBrand = $brand->name;
            }

            $hpNew = 0;
            $hpSecond = 0;
            $hpExIbox = 0;
            $nonHpNew = 0;

            // HP Items (ProductDetail) - Only if filter is 'all' or 'hp'
            if ($filterType === 'all' || $filterType === 'hp') {
                $hpQuery = ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
                    ->where('products.brand', 'ilike', '%' . $cleanBrand . '%')
                    ->whereIn('product_details.status', ['available', 'booking', 'returned', 'process', 'service'])
                    ->whereNull('products.deleted_at')
                    ->when($isAnalistOnly, function($q) use ($excludedKeywords) {
                        foreach ($excludedKeywords as $kw) {
                            $q->whereHasMorph('placement', [\App\Models\Branch::class, \App\Models\OnlineShop::class, \App\Models\Warehouse::class, \App\Models\Distributor::class], function($pq) use ($kw) {
                                $pq->where('name', 'not ilike', "%$kw%");
                            });
                        }
                    });

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

                if ($request->filled('branch_id')) {
                    $hpQuery->where('product_details.placement_type', 'branch')->where('product_details.placement_id', $request->branch_id);
                }
                if ($request->filled('online_shop_id')) {
                    $hpQuery->where('product_details.placement_type', 'online_shop')->where('product_details.placement_id', $request->online_shop_id);
                }
                if ($request->filled('warehouse_id')) {
                    $hpQuery->where('product_details.placement_type', 'warehouse')->where('product_details.placement_id', $request->warehouse_id);
                }
                if ($request->filled('placement_type')) {
                    $hpQuery->where('product_details.placement_type', $request->placement_type);
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
                    ->where('products.brand', 'ilike', '%' . $cleanBrand . '%')
                    ->whereNull('products.deleted_at')
                    ->when($isAnalistOnly, function($q) use ($excludedKeywords) {
                        foreach ($excludedKeywords as $kw) {
                            $q->whereHasMorph('placement', [\App\Models\Branch::class, \App\Models\OnlineShop::class, \App\Models\Warehouse::class, \App\Models\Distributor::class], function($pq) use ($kw) {
                                $pq->where('name', 'not ilike', "%$kw%");
                            });
                        }
                    });

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

                if ($request->filled('branch_id')) {
                    $nonHpQuery->where('inventories.placement_type', 'branch')->where('inventories.placement_id', $request->branch_id);
                }
                if ($request->filled('online_shop_id')) {
                    $nonHpQuery->where('inventories.placement_type', 'online_shop')->where('inventories.placement_id', $request->online_shop_id);
                }
                if ($request->filled('warehouse_id')) {
                    $nonHpQuery->where('inventories.placement_type', 'warehouse')->where('inventories.placement_id', $request->warehouse_id);
                }
                if ($request->filled('placement_type')) {
                    $nonHpQuery->where('inventories.placement_type', $request->placement_type);
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
                ->whereIn('product_details.status', ['available', 'booking', 'returned', 'process', 'service'])
                ->whereNull('products.deleted_at')
                ->when($user->hasRole('analist') && !$user->hasRole('super_admin'), function($q) {
                    $excludedKeywords = (array) config('kasara.excluded_keywords', []);
                    foreach ($excludedKeywords as $kw) {
                        $q->whereHasMorph('placement', [\App\Models\Branch::class, \App\Models\OnlineShop::class, \App\Models\Warehouse::class, \App\Models\Distributor::class], function($pq) use ($kw) {
                            $pq->where('name', 'not ilike', "%$kw%");
                        });
                    }
                });

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
                ->whereNull('products.deleted_at')
                ->when($user->hasRole('analist') && !$user->hasRole('super_admin'), function($q) {
                    $excludedKeywords = (array) config('kasara.excluded_keywords', []);
                    foreach ($excludedKeywords as $kw) {
                        $q->whereHasMorph('placement', [\App\Models\Branch::class, \App\Models\OnlineShop::class, \App\Models\Warehouse::class, \App\Models\Distributor::class], function($pq) use ($kw) {
                            $pq->where('name', 'not ilike', "%$kw%");
                        });
                    }
                });

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

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'angkat_barang', 'pelunasan_dp', 'dp'];
        $salesCategoriesExtended = array_merge($salesCategories, ['refund']);


        // Role-based scoping and strict isolation
        $requestedBranchId = $branchId;
        $requestedOnlineShopId = $onlineShopId;

        $accessibleBranchIds = $user->getAccessibleBranchIds();
        $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();
        $isRestricted = !$user->hasRole(['super_admin', 'analist']);

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
                    $q->orWhereIn("stock_outs.branch_id", $filterBranchIds);
                    $q->orWhereIn("{$tableName}.branch_id", $filterBranchIds);
                }
                if (!empty($filterOnlineShopIds)) {
                    $q->orWhereIn("stock_outs.online_shop_id", $filterOnlineShopIds);
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
        $csQuery = StockOut::whereIn('category', $salesCategoriesExtended)
            ->join('users', "stock_outs.{$csUserIdField}", '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                DB::raw("SUM(CASE 
                    WHEN stock_outs.category = 'tukar_tambah' THEN COALESCE((SELECT SUM(tt.outgoing_price) FROM tukar_tambahs tt WHERE tt.receipt_id = stock_outs.receipt_id), ABS(COALESCE(stock_outs.selling_price, 0)))
                    WHEN stock_outs.category = 'downgrade' THEN COALESCE((SELECT SUM(dg.outgoing_price) FROM downgrades dg WHERE dg.receipt_id = stock_outs.receipt_id), ABS(COALESCE(stock_outs.selling_price, 0)))
                    WHEN stock_outs.category NOT IN ('refund', 'angkat_barang') THEN CASE WHEN stock_outs.category = 'pelunasan_dp' THEN ABS(COALESCE(stock_outs.paid_amount, 0)) ELSE ABS(COALESCE(stock_outs.selling_price, 0)) END 
                    ELSE 0 
                END) as omset")
            );

        if ($startDate)
            $csQuery->where('stock_outs.reporting_date', '>=', $startDate);
        if ($endDate)
            $csQuery->where('stock_outs.reporting_date', '<=', $endDate);
        
        // Pass true to join 'owners' because 'users' here is the Petugas Stok
        $csQuery = $applyIsolation($csQuery, true);

        $csBase = $csQuery->groupBy('users.id', 'users.name')->get();

        // Get counts for HP per User (Separated by type)
        $hpCountsPerUser = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('users', "stock_outs.{$csUserIdField}", '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategoriesExtended)
            ->when($startDate, fn($q) => $q->where('stock_outs.reporting_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('stock_outs.reporting_date', '<=', $endDate))
            ->select(
                "stock_outs.{$csUserIdField}",
                DB::raw("COUNT(CASE WHEN stock_outs.category NOT IN ('refund', 'angkat_barang') THEN 1 END) as sold_count"),
                DB::raw("COUNT(CASE WHEN stock_outs.category = 'refund' THEN 1 END) as refund_count"),
                DB::raw("COUNT(CASE WHEN stock_outs.category = 'angkat_barang' THEN 1 END) as ab_count")
            )
            ->groupBy("stock_outs.{$csUserIdField}")
            ->get()
            ->keyBy($csUserIdField);

        // Get counts for Non-HP per User (Separated by type)
        $accCountsPerUser = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->join('users', "stock_outs.{$csUserIdField}", '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategoriesExtended)
            ->when($startDate, fn($q) => $q->where('stock_outs.reporting_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('stock_outs.reporting_date', '<=', $endDate))
            ->select(
                "stock_outs.{$csUserIdField}",
                DB::raw("SUM(CASE WHEN stock_outs.category NOT IN ('refund', 'angkat_barang') THEN quantity ELSE 0 END) as sold_count"),
                DB::raw("SUM(CASE WHEN stock_outs.category = 'refund' THEN quantity ELSE 0 END) as refund_count"),
                DB::raw("SUM(CASE WHEN stock_outs.category = 'angkat_barang' THEN quantity ELSE 0 END) as ab_count")
            )
            ->groupBy("stock_outs.{$csUserIdField}")
            ->get()
            ->keyBy($csUserIdField);

        $csStats = $csBase->map(function ($user) use ($hpCountsPerUser, $accCountsPerUser) {
            $hp = $hpCountsPerUser->get($user->id);
            $acc = $accCountsPerUser->get($user->id);

            return [
                'name' => $user->name,
                'hp_count' => (int) ($hp->sold_count ?? 0),
                'acc_count' => (int) ($acc->sold_count ?? 0),
                'refund_count' => (int) (($hp->refund_count ?? 0) + ($acc->refund_count ?? 0)),
                'ab_count' => (int) (($hp->ab_count ?? 0) + ($acc->ab_count ?? 0)),
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
            ->whereIn('stock_outs.category', $salesCategoriesExtended);
    if ($startDate)
        $hpBaseQuery->where('stock_outs.reporting_date', '>=', $startDate);
    if ($endDate)
        $hpBaseQuery->where('stock_outs.reporting_date', '<=', $endDate);
        $hpBaseQuery = $applyIsolation($hpBaseQuery);

        $hpBrandStats = (clone $hpBaseQuery)->select(
            'products.brand',
            'product_details.condition',
            'stock_outs.category',
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('products.brand', 'product_details.condition', 'stock_outs.category')
            ->get();

        // Non-HP Brand Stats
        $nhpBaseQuery = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategoriesExtended);
    if ($startDate)
        $nhpBaseQuery->where('stock_outs.reporting_date', '>=', $startDate);
    if ($endDate)
        $nhpBaseQuery->where('stock_outs.reporting_date', '<=', $endDate);
        $nhpBaseQuery = $applyIsolation($nhpBaseQuery);

        $nhpBrandStats = (clone $nhpBaseQuery)->select(
            'products.brand',
            'stock_outs.category',
            DB::raw('SUM(quantity) as count')
        )
            ->groupBy('products.brand', 'stock_outs.category')
            ->get();

        $brandStatsMap = [];
        foreach ($hpBrandStats as $s) {
            if (!isset($brandStatsMap[$s->brand])) {
                $brandStatsMap[$s->brand] = ['brand' => $s->brand, 'hp_new' => 0, 'hp_second' => 0, 'hp_ex_ibox' => 0, 'non_hp' => 0, 'angkat_barang' => 0, 'refund' => 0];
            }
            
            if ($s->category === 'refund') {
                $brandStatsMap[$s->brand]['refund'] += $s->count;
            } elseif ($s->category === 'angkat_barang') {
                $brandStatsMap[$s->brand]['angkat_barang'] += $s->count;
            } else {
                if ($s->condition === 'second')
                    $brandStatsMap[$s->brand]['hp_second'] += $s->count;
                elseif ($s->condition === 'ex_ibox')
                    $brandStatsMap[$s->brand]['hp_ex_ibox'] += $s->count;
                else
                    $brandStatsMap[$s->brand]['hp_new'] += $s->count;
            }
        }
        foreach ($nhpBrandStats as $s) {
            if (!isset($brandStatsMap[$s->brand])) {
                $brandStatsMap[$s->brand] = ['brand' => $s->brand, 'hp_new' => 0, 'hp_second' => 0, 'hp_ex_ibox' => 0, 'non_hp' => 0, 'angkat_barang' => 0, 'refund' => 0];
            }
            if ($s->category === 'refund') {
                $brandStatsMap[$s->brand]['refund'] += $s->count;
            } elseif ($s->category === 'angkat_barang') {
                $brandStatsMap[$s->brand]['angkat_barang'] += $s->count;
            } else {
                $brandStatsMap[$s->brand]['non_hp'] += $s->count;
            }
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

    public function exportRankingExcel(Request $request)
    {
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->query('start_date') ?: null;
        $endDate = $request->query('end_date') ?: null;

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

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'angkat_barang', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pos', 'sale', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'pelunasan_dp', 'dp'];
        $salesCategoriesExtended = array_merge($salesCategories, ['refund', 'balancing', 'refund_dp']);

        // 1. Get Payment Methods
        $paymentMethods = \App\Models\PaymentMethod::all();
        
        // Let's get the base stats from DB
        $baseQuery = DB::table('stock_outs')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategoriesExtended)
            ->where('stock_outs.status', '!=', 'cancelled')
            ->whereNull('stock_outs.deleted_at');

        if ($startDate) $baseQuery->where('stock_outs.reporting_date', '>=', $startDate);
        if ($endDate) $baseQuery->where('stock_outs.reporting_date', '<=', $endDate);

        $statsByLocation = [];

        $baseQuery->select(
            'stock_outs.id',
            'stock_outs.category',
            'stock_outs.sub_category',
            'stock_outs.selling_price',
            'stock_outs.paid_amount',
            'stock_outs.payment_method_id',
            'stock_outs.split_payments',
            'stock_outs.notes',
            'stock_outs.sales_account',
            'stock_outs.receipt_id',
            DB::raw('COALESCE(stock_outs.branch_id, users.branch_id) as branch_id'),
            DB::raw('COALESCE(stock_outs.online_shop_id, users.online_shop_id) as online_shop_id')
        )->orderBy('stock_outs.id')->chunk(2000, function ($transactions) use (&$statsByLocation, $paymentMethods) {

            $txIds = $transactions->pluck('id')->toArray();
            $receiptIds = $transactions->pluck('receipt_id')->filter()->unique()->toArray();

            $items = empty($txIds) ? collect() : DB::table('stock_out_items')
                ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
                ->join('products', 'product_details.product_id', '=', 'products.id')
                ->whereIn('stock_out_items.stock_out_id', $txIds)
                ->where('products.type', 'hp')
                ->select(
                    'stock_out_items.stock_out_id',
                    'products.brand',
                    'product_details.condition',
                    'product_details.cost_price',
                    'stock_out_items.selling_price as item_price',
                    'products.price as product_price'
                )->get();
            $itemsByTx = $items->groupBy('stock_out_id');

            $nonHpItems = empty($txIds) ? collect() : DB::table('stock_out_non_hp_items')
                ->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')
                ->whereIn('stock_out_non_hp_items.stock_out_id', $txIds)
                ->select(
                    'stock_out_non_hp_items.stock_out_id',
                    'stock_out_non_hp_items.quantity',
                    'products.price as product_price'
                )->get();
            $nonHpItemsByTx = $nonHpItems->groupBy('stock_out_id');

            $tukarTambahs = empty($receiptIds) ? collect() : DB::table('tukar_tambahs')
                ->whereIn('receipt_id', $receiptIds)
                ->get()->groupBy('receipt_id');
                
            $downgrades = empty($receiptIds) ? collect() : DB::table('downgrades')
                ->whereIn('receipt_id', $receiptIds)
                ->get()->groupBy('receipt_id');

        foreach ($transactions as $tx) {
            $locKey = $tx->branch_id ? 'B_' . $tx->branch_id : 'O_' . $tx->online_shop_id;
            if (!isset($statsByLocation[$locKey])) {
                $statsByLocation[$locKey] = [
                    'omset' => 0,
                    'omset_bersih' => 0,
                    'profit' => 0,
                    'payments' => [],
                    'iphone_new_qty' => 0, 'iphone_new_amt' => 0,
                    'iphone_scd_qty' => 0, 'iphone_scd_amt' => 0,
                    'android_qty' => 0, 'android_amt' => 0,
                    'balancing_penjualan_qty' => 0, 'balancing_penjualan_amt' => 0,
                    'balancing_pembayaran_qty' => 0, 'balancing_pembayaran_amt' => 0,
                    'dp_qty' => 0, 'dp_amt' => 0,
                    'pelunasan_dp_qty' => 0, 'pelunasan_dp_amt' => 0,
                    'refund_qty' => 0, 'refund_amt' => 0,
                    'refund_dp_qty' => 0, 'refund_dp_amt' => 0,
                    'angkat_barang_qty' => 0, 'angkat_barang_amt' => 0,
                    'tukar_tambah_qty' => 0, 'tukar_tambah_amt' => 0,
                    'tukar_unit_qty' => 0, 'tukar_unit_amt' => 0,
                    'downgrade_qty' => 0, 'downgrade_amt' => 0,
                ];
                foreach ($paymentMethods as $pm) {
                    $statsByLocation[$locKey]['payments'][$pm->id] = 0;
                }
            }

            $cat = strtolower(str_replace(' ', '_', $tx->category));
            $subCat = strtolower($tx->sub_category ?? '');
            $notes = strtolower($tx->notes ?? '');
            $account = strtolower($tx->sales_account ?? '');

            $isBalancingPenjualan = $cat === 'balancing' && $subCat === 'balancing_penjualan_terlewat';
            $isBalancingPembayaran = $cat === 'balancing' && $subCat === 'balancing_metode_pembayaran';
            $isDp = $cat === 'dp';
            $isPelunasanDp = $cat === 'pelunasan_dp';
            
            $isTukarTambah = $cat === 'tukar_tambah' || str_contains($notes, 'tukar tambah') || str_contains($notes, 'tukar_tambah') || str_contains($account, 'tukar tambah') || str_contains($account, 'tukar_tambah');
            $isRefund = $cat === 'refund' || str_contains($notes, 'refund') || str_contains($account, 'refund');
            $isRefundDp = $cat === 'refund_dp';
            $isAngkatBarang = $cat === 'angkat_barang' || str_contains($notes, 'barang angkat') || str_contains($notes, 'angkat barang') || str_contains($notes, 'angkat_barang') || str_contains($account, 'barang angkat') || str_contains($account, 'angkat barang') || str_contains($account, 'angkat_barang');
            $isTukarUnit = $cat === 'tukar_unit' || str_contains($notes, 'tukar unit') || str_contains($notes, 'tukar_unit') || str_contains($account, 'tukar unit') || str_contains($account, 'tukar_unit');
            $isDowngrade = $cat === 'downgrade' || str_contains($notes, 'downgrade') || str_contains($account, 'downgrade');
            
            $isNormalSales = in_array($cat, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pelunasan_dp', 'dp']);

            $sellingPrice = ($cat === 'pelunasan_dp' || $cat === 'dp') ? (float) ($tx->paid_amount ?? 0) : (float) ($tx->selling_price ?? 0);
            $txOmset = 0;
            $txOmsetBersih = 0;
            
            $txItems = $itemsByTx->get($tx->id, []);
            $txNonHpItems = $nonHpItemsByTx->get($tx->id, []);
            
            $txModal = 0;
            foreach ($txItems as $item) {
                $txModal += (float) $item->cost_price;
            }
            foreach ($txNonHpItems as $item) {
                $txModal += (float) ($item->product_price ?? 0) * $item->quantity;
            }

            $spTotal = 0;
            if ($tx->split_payments) {
                $sData = is_string($tx->split_payments) ? json_decode($tx->split_payments, true) : $tx->split_payments;
                if (is_array($sData)) {
                    foreach ($sData as $sp) {
                        $spTotal += abs((float) ($sp['amount'] ?? 0));
                    }
                }
            }
            $effectivePrice = ($spTotal > 0) ? $spTotal : abs($sellingPrice);

            if ($isTukarTambah) {
                $tt = $tukarTambahs->get($tx->receipt_id);
                $ttOutgoing = $tt ? $tt->sum('outgoing_price') : $effectivePrice;
                $ttCost = $tt ? $tt->sum('incoming_cost_price') : 0;
                $txOmset = max(0, abs($ttOutgoing));
                $txOmsetBersih = max(0, $ttCost);
                $txProfit = max(0, abs($ttOutgoing)) - $txModal;
                $statsByLocation[$locKey]['tukar_tambah_qty'] += 1;
                $statsByLocation[$locKey]['tukar_tambah_amt'] += $txOmset;
            } elseif ($isDowngrade) {
                $dg = $downgrades->get($tx->receipt_id);
                $dgOutgoing = $dg ? $dg->sum('outgoing_price') : $effectivePrice;
                $txOmset = max(0, abs($dgOutgoing));
                $txOmsetBersih = $dg ? $dg->sum(fn($d) => $d->incoming_cost_price) : -abs($effectivePrice);
                $txProfit = $dgOutgoing - $txModal;
                $statsByLocation[$locKey]['downgrade_qty'] += 1;
                $statsByLocation[$locKey]['downgrade_amt'] += $txOmset;
            } elseif ($isNormalSales || $isBalancingPenjualan || $isBalancingPembayaran) {
                $txOmset = max(0, $effectivePrice);
                $txOmsetBersih = $txOmset;
                $txProfit = max(0, $effectivePrice) - $txModal;
                if ($isDp) {
                    $statsByLocation[$locKey]['dp_qty'] += 1;
                    $statsByLocation[$locKey]['dp_amt'] += $txOmset;
                } elseif ($isPelunasanDp) {
                    $statsByLocation[$locKey]['pelunasan_dp_qty'] += 1;
                    $statsByLocation[$locKey]['pelunasan_dp_amt'] += $txOmset;
                } elseif ($isBalancingPenjualan) {
                    $statsByLocation[$locKey]['balancing_penjualan_qty'] += 1;
                    $statsByLocation[$locKey]['balancing_penjualan_amt'] += $txOmset;
                } elseif ($isBalancingPembayaran) {
                    $statsByLocation[$locKey]['balancing_pembayaran_qty'] += 1;
                    $statsByLocation[$locKey]['balancing_pembayaran_amt'] += $txOmset;
                }
            } elseif ($isAngkatBarang) {
                $txOmsetBersih = -abs($sellingPrice);
                $txProfit = $txModal - abs($sellingPrice);
                $statsByLocation[$locKey]['angkat_barang_qty'] += 1;
                $statsByLocation[$locKey]['angkat_barang_amt'] += abs($sellingPrice);
            } elseif ($isRefund) {
                $txOmsetBersih = -abs($sellingPrice);
                $txProfit = $txModal - abs($sellingPrice);
                $statsByLocation[$locKey]['refund_qty'] += 1;
                $statsByLocation[$locKey]['refund_amt'] += abs($sellingPrice);
            } elseif ($isRefundDp) {
                $txOmsetBersih = -abs($sellingPrice);
                $txProfit = -abs($sellingPrice);
                $statsByLocation[$locKey]['refund_dp_qty'] += 1;
                $statsByLocation[$locKey]['refund_dp_amt'] += abs($sellingPrice);
            } elseif ($isTukarUnit) {
                $txProfit = abs($sellingPrice) - $txModal;
                $statsByLocation[$locKey]['tukar_unit_qty'] += 1;
                $statsByLocation[$locKey]['tukar_unit_amt'] += abs($sellingPrice);
            }

            $statsByLocation[$locKey]['omset'] += $txOmset;
            $statsByLocation[$locKey]['omset_bersih'] += $txOmsetBersih;
            $statsByLocation[$locKey]['profit'] += $txProfit ?? 0;

            // Payments - match AuditController exactly
            if ($isNormalSales || $isBalancingPenjualan || $isBalancingPembayaran || $isTukarTambah) {
                $splits = json_decode($tx->split_payments, true);
                if (is_array($splits) && count($splits) > 0) {
                    $remainingToAllocate = abs($sellingPrice);
                    foreach ($splits as $split) {
                        $pmId = $split['payment_method_id'] ?? ($split['method_id'] ?? null);
                        $amt = abs((float) ($split['amount'] ?? 0));
                        $allocatedAmt = min($amt, $remainingToAllocate);
                        
                        if ($pmId && isset($statsByLocation[$locKey]['payments'][$pmId])) {
                            $statsByLocation[$locKey]['payments'][$pmId] += $allocatedAmt;
                        }
                        $remainingToAllocate -= $allocatedAmt;
                    }
                } elseif ($tx->payment_method_id) {
                    if (isset($statsByLocation[$locKey]['payments'][$tx->payment_method_id])) {
                        $statsByLocation[$locKey]['payments'][$tx->payment_method_id] += abs($sellingPrice);
                    }
                }
            }

            // Items breakdown
            $txItems = $itemsByTx->get($tx->id, []);
            
            $trxItemsTotal = 0;
            foreach ($txItems as $item) {
                $trxItemsTotal += ($item->item_price > 0) ? (float) $item->item_price : (float) $item->product_price;
            }
            foreach ($txNonHpItems as $item) {
                $trxItemsTotal += (float) ($item->product_price ?? 0) * $item->quantity;
            }
            
            $discrepancy = 0;
            if ($isNormalSales && $trxItemsTotal > 0 && round($trxItemsTotal) != round($txOmset)) {
                $discrepancy = $trxItemsTotal - $txOmset;
            }
            
            foreach ($txItems as $item) {
                $brand = strtolower($item->brand);
                $isIphone = str_contains($brand, 'iphone') || str_contains($brand, 'apple');
                
                $basePrice = ($item->item_price > 0) ? (float) $item->item_price : (float) $item->product_price;
                $price = $basePrice;
                if ($discrepancy != 0 && $trxItemsTotal > 0) {
                    $price -= ($basePrice / $trxItemsTotal) * $discrepancy;
                }
                
                if ($isIphone) {
                    if ($item->condition === 'new') {
                        $statsByLocation[$locKey]['iphone_new_qty'] += 1;
                        $statsByLocation[$locKey]['iphone_new_amt'] += $price;
                    } else {
                        $statsByLocation[$locKey]['iphone_scd_qty'] += 1;
                        $statsByLocation[$locKey]['iphone_scd_amt'] += $price;
                    }
                } else {
                    $statsByLocation[$locKey]['android_qty'] += 1;
                    $statsByLocation[$locKey]['android_amt'] += $price;
                }
            }
            
            // non hp items discrepancy distribution
            foreach ($txNonHpItems as $item) {
                $basePrice = (float) ($item->product_price ?? 0) * $item->quantity;
                $price = $basePrice;
                if ($discrepancy != 0 && $trxItemsTotal > 0) {
                    $price -= ($basePrice / $trxItemsTotal) * $discrepancy;
                }
                $statsByLocation[$locKey]['android_amt'] += 0; // non hp items usually not counted here, but if needed we can add logic
            }
        }
        });

        $branches = \App\Models\Branch::all()->keyBy('id');
        $shops = \App\Models\OnlineShop::all()->keyBy('id');

        $accessibleBranchIds = $user->getAccessibleBranchIds();
        $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();
        $isRestricted = !$user->hasRole('super_admin') && !$user->hasRole('analist');

        $finalData = [];
        foreach ($statsByLocation as $locKey => $stats) {
            $type = $locKey[0];
            $id = (int) substr($locKey, 2);
            
            if ($isRestricted) {
                if ($type === 'B' && !in_array($id, $accessibleBranchIds)) continue;
                if ($type === 'O' && !in_array($id, $accessibleOnlineShopIds)) continue;
            }
            
            if ($request->boolean('include_zero', false) === false && $stats['omset'] == 0 && $stats['omset_bersih'] == 0) {
                continue;
            }

            $name = $type === 'B' ? ($branches[$id]->name ?? 'Unknown') : ($shops[$id]->name ?? 'Unknown');
            
            $stats['name'] = $name;
            $stats['type'] = $type === 'B' ? 'Offline' : 'Online';
            $finalData[] = $stats;
        }

        $sortBy = $request->query('sort_by', 'omset');
        if ($sortBy === 'omset') {
            usort($finalData, fn($a, $b) => $b['omset'] <=> $a['omset']);
        } else {
            usort($finalData, fn($a, $b) => strcmp($a['name'], $b['name']));
        }

        $header = ['No', 'Nama Cabang', 'Total Omset Kotor', 'Total Omset Bersih', 'Profit'];
        foreach ($paymentMethods as $pm) {
            $header[] = $pm->name;
        }
        $header = array_merge($header, [
            'iPhone New (Qty)', 'iPhone New (Rp)',
            'iPhone Scd (Qty)', 'iPhone Scd (Rp)',
            'Android (Qty)', 'Android (Rp)',
            'Balancing Penjualan (Qty)', 'Balancing Penjualan (Rp)',
            'Balancing Pembayaran (Qty)', 'Balancing Pembayaran (Rp)',
            'DP (Qty)', 'DP (Rp)',
            'Pelunasan DP (Qty)', 'Pelunasan DP (Rp)',
            'Refund (Qty)', 'Refund (Rp)',
            'Angkat Barang (Qty)', 'Angkat Barang (Rp)',
            'Tukar Tambah (Qty)', 'Tukar Tambah (Rp)',
            'Tukar Unit (Qty)', 'Tukar Unit (Rp)',
            'Downgrade (Qty)', 'Downgrade (Rp)'
        ]);

        $rows = [$header];
        
        $offlineData = array_filter($finalData, fn($r) => $r['type'] === 'Offline');
        $onlineData = array_filter($finalData, fn($r) => $r['type'] === 'Online');
        
        $buildRow = function($row, $no) use ($paymentMethods) {
            $excelRow = [
                $no,
                $row['name'],
                $row['omset'],
                $row['omset_bersih'],
                $row['profit'] ?? 0,
            ];
            foreach ($paymentMethods as $pm) {
                $excelRow[] = $row['payments'][$pm->id] ?? 0;
            }
            return array_merge($excelRow, [
                $row['iphone_new_qty'], $row['iphone_new_amt'],
                $row['iphone_scd_qty'], $row['iphone_scd_amt'],
                $row['android_qty'], $row['android_amt'],
                $row['balancing_penjualan_qty'], $row['balancing_penjualan_amt'],
                $row['balancing_pembayaran_qty'], $row['balancing_pembayaran_amt'],
                $row['dp_qty'], $row['dp_amt'],
                $row['pelunasan_dp_qty'], $row['pelunasan_dp_amt'],
                $row['refund_qty'], $row['refund_amt'],
                $row['angkat_barang_qty'], $row['angkat_barang_amt'],
                $row['tukar_tambah_qty'], $row['tukar_tambah_amt'],
                $row['tukar_unit_qty'], $row['tukar_unit_amt'],
                $row['downgrade_qty'], $row['downgrade_amt'],
            ]);
        };
        
        $createTotal = function($data, $label) use ($paymentMethods, $buildRow) {
            if (empty($data)) return null;
            $total = [
                'name' => $label,
                'omset' => 0, 'omset_bersih' => 0, 'profit' => 0,
                'payments' => [],
                'iphone_new_qty' => 0, 'iphone_new_amt' => 0,
                'iphone_scd_qty' => 0, 'iphone_scd_amt' => 0,
                'android_qty' => 0, 'android_amt' => 0,
                'balancing_penjualan_qty' => 0, 'balancing_penjualan_amt' => 0,
                'balancing_pembayaran_qty' => 0, 'balancing_pembayaran_amt' => 0,
                'dp_qty' => 0, 'dp_amt' => 0,
                'pelunasan_dp_qty' => 0, 'pelunasan_dp_amt' => 0,
                'refund_qty' => 0, 'refund_amt' => 0,
                'angkat_barang_qty' => 0, 'angkat_barang_amt' => 0,
                'tukar_tambah_qty' => 0, 'tukar_tambah_amt' => 0,
                'tukar_unit_qty' => 0, 'tukar_unit_amt' => 0,
                'downgrade_qty' => 0, 'downgrade_amt' => 0,
            ];
            foreach ($data as $r) {
                $total['omset'] += $r['omset'];
                $total['omset_bersih'] += $r['omset_bersih'];
                $total['profit'] += $r['profit'] ?? 0;
                foreach ($paymentMethods as $pm) {
                    $total['payments'][$pm->id] = ($total['payments'][$pm->id] ?? 0) + ($r['payments'][$pm->id] ?? 0);
                }
                $total['iphone_new_qty'] += $r['iphone_new_qty']; $total['iphone_new_amt'] += $r['iphone_new_amt'];
                $total['iphone_scd_qty'] += $r['iphone_scd_qty']; $total['iphone_scd_amt'] += $r['iphone_scd_amt'];
                $total['android_qty'] += $r['android_qty']; $total['android_amt'] += $r['android_amt'];
                $total['balancing_penjualan_qty'] += $r['balancing_penjualan_qty']; $total['balancing_penjualan_amt'] += $r['balancing_penjualan_amt'];
                $total['balancing_pembayaran_qty'] += $r['balancing_pembayaran_qty']; $total['balancing_pembayaran_amt'] += $r['balancing_pembayaran_amt'];
                $total['dp_qty'] += $r['dp_qty']; $total['dp_amt'] += $r['dp_amt'];
                $total['pelunasan_dp_qty'] += $r['pelunasan_dp_qty']; $total['pelunasan_dp_amt'] += $r['pelunasan_dp_amt'];
                $total['refund_qty'] += $r['refund_qty']; $total['refund_amt'] += $r['refund_amt'];
                $total['angkat_barang_qty'] += $r['angkat_barang_qty']; $total['angkat_barang_amt'] += $r['angkat_barang_amt'];
                $total['tukar_tambah_qty'] += $r['tukar_tambah_qty']; $total['tukar_tambah_amt'] += $r['tukar_tambah_amt'];
                $total['tukar_unit_qty'] += $r['tukar_unit_qty']; $total['tukar_unit_amt'] += $r['tukar_unit_amt'];
                $total['downgrade_qty'] += $r['downgrade_qty']; $total['downgrade_amt'] += $r['downgrade_amt'];
            }
            return $buildRow($total, '');
        };

        $no = 1;
        // Offline
        foreach ($offlineData as $row) {
            $rows[] = $buildRow($row, $no++);
        }
        if (!empty($offlineData)) {
            $rows[] = $createTotal($offlineData, 'TOTAL CABANG FISIK');
        }
        
        $rows[] = array_fill(0, count($header), ''); // Empty row
        $rows[] = array_fill(0, count($header), ''); // Empty row

        // Online
        foreach ($onlineData as $row) {
            $rows[] = $buildRow($row, $no++);
        }
        if (!empty($onlineData)) {
            $rows[] = $createTotal($onlineData, 'TOTAL TOKO ONLINE');
        }
        
        $rows[] = array_fill(0, count($header), ''); // Empty row
        
        // Total ALL
        if (!empty($finalData)) {
            $rows[] = $createTotal($finalData, 'TOTAL KESELURUHAN (ALL)');
        }

        $xlsx = \App\Utils\SimpleXLSXGen::fromArray($rows);
        $fileName = 'Ranking_Performa_' . ($startDate ?: 'All') . '.xlsx';
        
        return response((string) $xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"'
        ]);
    }

    public function getRankingReport(Request $request)
    {
      try {
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->query('start_date') ?: null;
        $endDate = $request->query('end_date') ?: null;

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

        
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'angkat_barang', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pos', 'sale', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'pelunasan_dp', 'dp'];
        $salesCategoriesExtended = array_merge($salesCategories, ['refund', 'balancing', 'refund_dp']);

        // 1. Get Base Stats (Omset & Transaction Count)
        $baseQuery = DB::table('stock_outs')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategoriesExtended)
            ->whereNull('stock_outs.deleted_at');

        $startTS = $startDate ? $startDate . ' 05:00:00' : null;
        $endTS = $endDate ? date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59' : null;

        if ($startDate && $endDate) {
            $baseQuery->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
                $q->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
                  /* ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]); */;
            });
        } elseif ($startDate) {
            $baseQuery->where(function ($q) use ($startDate, $startTS) {
                $q->where('stock_outs.reporting_date', '>=', $startDate)
                  ->orWhere('stock_outs.created_at', '>=', $startTS);
            });
        } elseif ($endDate) {
            $baseQuery->where(function ($q) use ($endDate, $endTS) {
                $q->where('stock_outs.reporting_date', '<=', $endDate)
                  ->orWhere('stock_outs.created_at', '<=', $endTS);
            });
        }

        $aggregatedStats = [];
        $baseQuery->select(
            'stock_outs.id',
            'stock_outs.category',
            'stock_outs.sub_category',
            'stock_outs.selling_price',
            'stock_outs.paid_amount',
            'stock_outs.total_discount',
            'stock_outs.split_payments',
            'stock_outs.notes',
            'stock_outs.sales_account',
            'stock_outs.receipt_id',
            DB::raw('COALESCE(stock_outs.branch_id, users.branch_id) as branch_id'),
            DB::raw('COALESCE(stock_outs.online_shop_id, users.online_shop_id) as online_shop_id')
        )->orderBy('stock_outs.id')->chunk(2000, function ($rawTransactions) use (&$aggregatedStats) {

        $ttData = DB::table('tukar_tambahs')
            ->whereIn('receipt_id', $rawTransactions->pluck('receipt_id')->unique())
            ->select('receipt_id', 'outgoing_price', 'incoming_cost_price')
            ->get()
            ->groupBy('receipt_id');

        $dgData = DB::table('downgrades')
            ->whereIn('receipt_id', $rawTransactions->pluck('receipt_id')->unique())
            ->select('receipt_id', 'outgoing_price', 'incoming_cost_price')
            ->get()
            ->groupBy('receipt_id');

        foreach ($rawTransactions as $tx) {
            $cat = strtolower(str_replace(' ', '_', $tx->category));
            $subCat = strtolower($tx->sub_category ?? '');
            $notes = strtolower($tx->notes ?? '');
            $sa = strtolower($tx->sales_account ?? '');

            $saleType = 'ignored';
            if ($cat === 'tukar_tambah' || str_contains($notes, 'tukar tambah') || str_contains($notes, 'tukar_tambah') || str_contains($sa, 'tukar tambah') || str_contains($sa, 'tukar_tambah')) {
                $saleType = 'tukar_tambah';
            } elseif ($cat === 'balancing' && $subCat === 'balancing_penjualan_terlewat') {
                $saleType = 'balancing_penjualan';
            } elseif ($cat === 'balancing' && $subCat === 'balancing_metode_pembayaran') {
                $saleType = 'balancing_pembayaran';
            } elseif (in_array($cat, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship'])) {
                $saleType = 'base_sale';
            } elseif ($cat === 'dp') {
                $saleType = 'dp';
            } elseif ($cat === 'pelunasan_dp') {
                $saleType = 'pelunasan_dp';
            } elseif (str_contains($notes, 'barang angkat') || str_contains($notes, 'angkat barang') || str_contains($notes, 'angkat_barang') || str_contains($sa, 'barang angkat') || str_contains($sa, 'angkat barang') || str_contains($sa, 'angkat_barang') || $cat === 'angkat_barang') {
                $saleType = 'angkat_barang';
            } elseif (str_contains($notes, 'refund') || str_contains($sa, 'refund') || $cat === 'refund') {
                $saleType = 'refund';
            } elseif (str_contains($notes, 'downgrade') || str_contains($sa, 'downgrade') || $cat === 'downgrade') {
                $saleType = 'downgrade';
            } elseif ($cat === 'refund_dp') {
                $saleType = 'refund_dp';
            }

            $price = ($cat === 'pelunasan_dp' || $cat === 'dp') ? max(0, abs((float) ($tx->paid_amount ?? 0))) : max(0, abs((float) ($tx->selling_price ?? 0)));
            $spTotal = 0;
            if ($tx->split_payments) {
                $sData = is_string($tx->split_payments) ? json_decode($tx->split_payments, true) : $tx->split_payments;
                if (is_array($sData)) {
                    foreach ($sData as $sp) {
                        $spTotal += abs((float) ($sp['amount'] ?? 0));
                    }
                }
            }
            
            // USER RULE: Omset MUST strictly follow actual received payments (spTotal) over database selling_price!
            $effectivePrice = ($spTotal > 0) ? $spTotal : $price;

            $txOmset = 0;
            $txOmsetBersih = 0;
            $isTransaction = false;
            $isDp = false;
            $isPelunasanDp = false;
            $isBalancingPenjualan = false;
            $isBalancingPembayaran = false;
            $isRefund = false;
            $isAb = false;
            $refundAmt = 0;
            $abAmt = 0;

            if ($saleType === 'base_sale') {
                $txOmset = $effectivePrice;
                $txOmsetBersih = $effectivePrice;
                $isTransaction = true;
            } elseif ($saleType === 'dp') {
                $txOmset = $effectivePrice;
                $txOmsetBersih = $effectivePrice;
                $isTransaction = true;
                $isDp = true;
            } elseif ($saleType === 'pelunasan_dp') {
                $txOmset = $effectivePrice;
                $txOmsetBersih = $effectivePrice;
                $isTransaction = true;
                $isPelunasanDp = true;
            } elseif ($saleType === 'balancing_penjualan') {
                $txOmset = $effectivePrice;
                $txOmsetBersih = $effectivePrice;
                $isTransaction = true;
                $isBalancingPenjualan = true;
            } elseif ($saleType === 'balancing_pembayaran') {
                $txOmset = $effectivePrice;
                $txOmsetBersih = $effectivePrice;
                $isTransaction = true;
                $isBalancingPembayaran = true;
            } elseif ($saleType === 'tukar_tambah') {
                $tt = $ttData->get($tx->receipt_id);
                $outPrice = $tt ? $tt->sum('outgoing_price') : 0;
                if ($outPrice <= 0) $outPrice = $effectivePrice;
                $inPrice = $tt ? $tt->sum('incoming_cost_price') : 0;

                $txOmset = $outPrice;
                $txOmsetBersih = $outPrice - $inPrice; // Match AuditController exact logic
                $isTransaction = true;
            } elseif ($saleType === 'downgrade') {
                $dg = $dgData->get($tx->receipt_id);
                $outDg = $dg ? $dg->sum('outgoing_price') : 0;
                $inDg = $dg ? $dg->sum('incoming_cost_price') : 0;
                
                if ($outDg > 0 || $inDg > 0) {
                    $txOmset = $outDg;
                    $txOmsetBersih = $outDg - $inDg;
                } else {
                    $txOmsetBersih = -$effectivePrice;
                }
                $isTransaction = true;
            } elseif ($saleType === 'refund') {
                $txOmsetBersih = -$effectivePrice;
                $isRefund = true;
                $refundAmt = $effectivePrice;
            } elseif ($saleType === 'angkat_barang') {
                $txOmsetBersih = -$effectivePrice;
                $isAb = true;
                $abAmt = $effectivePrice;
            } elseif ($saleType === 'refund_dp') {
                $txOmsetBersih = -$effectivePrice;
                $isRefund = true;
                $refundAmt = $effectivePrice;
            }

            $locKey = ($tx->branch_id ? 'B_' . $tx->branch_id : 'O_' . $tx->online_shop_id);
            if (!isset($aggregatedStats[$locKey])) {
                $aggregatedStats[$locKey] = [
                    'branch_id' => $tx->branch_id,
                    'online_shop_id' => $tx->online_shop_id,
                    'sales_omset' => 0,
                    'omset_bersih' => 0,
                    'transaction_count' => 0,
                    'dp_amount' => 0,
                    'pelunasan_dp_amount' => 0,
                    'balancing_penjualan_amount' => 0,
                    'balancing_pembayaran_amount' => 0,
                    'refund_count' => 0,
                    'refund_amount' => 0,
                    'ab_count' => 0,
                    'ab_amount' => 0,
                    'refund_dp_amount' => 0
                ];
            }

            $aggregatedStats[$locKey]['sales_omset'] += $txOmset;
            $aggregatedStats[$locKey]['omset_bersih'] += $txOmsetBersih;
            if ($isTransaction) $aggregatedStats[$locKey]['transaction_count']++;
            if ($isDp) $aggregatedStats[$locKey]['dp_amount'] += $txOmset;
            if ($isPelunasanDp) $aggregatedStats[$locKey]['pelunasan_dp_amount'] += $txOmset;
            if ($isBalancingPenjualan) $aggregatedStats[$locKey]['balancing_penjualan_amount'] += $txOmset;
            if ($isBalancingPembayaran) $aggregatedStats[$locKey]['balancing_pembayaran_amount'] += $txOmset;
            if ($isRefund) {
                $aggregatedStats[$locKey]['refund_count']++;
                $aggregatedStats[$locKey]['refund_amount'] += $refundAmt;
                if ($saleType === 'refund_dp') {
                    $aggregatedStats[$locKey]['refund_dp_amount'] += $refundAmt;
                }
            }
            if ($isAb) {
                $aggregatedStats[$locKey]['ab_count']++;
                $aggregatedStats[$locKey]['ab_amount'] += $abAmt;
            }
        }
        });

        $baseStats = collect(array_values($aggregatedStats));

        // 2. Get Item Counts (Iphone vs Android)
        $itemCountsQuery = DB::table('stock_outs')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->join('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereNull('stock_outs.deleted_at')
            ->where('products.type', 'hp');

        if ($startDate) $itemCountsQuery->where('stock_outs.reporting_date', '>=', $startDate);
        if ($endDate) $itemCountsQuery->where('stock_outs.reporting_date', '<=', $endDate);

        $itemCounts = $itemCountsQuery->select(
            DB::raw('COALESCE(stock_outs.branch_id, users.branch_id) as branch_id'),
            DB::raw('COALESCE(stock_outs.online_shop_id, users.online_shop_id) as online_shop_id'),
            DB::raw("COUNT(CASE WHEN (LOWER(products.brand) LIKE '%iphone%' OR LOWER(products.brand) LIKE '%apple%') THEN 1 END) as iphone_count"),
            DB::raw("COUNT(CASE WHEN (LOWER(products.brand) NOT LIKE '%iphone%' AND LOWER(products.brand) NOT LIKE '%apple%') THEN 1 END) as android_count")
        )
        ->groupBy(DB::raw('COALESCE(stock_outs.branch_id, users.branch_id)'), DB::raw('COALESCE(stock_outs.online_shop_id, users.online_shop_id)'))
        ->get();

        // 3. Get Top Android Models
        $androidModelsQuery = DB::table('stock_outs')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->join('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereNull('stock_outs.deleted_at')
            ->where('products.type', 'hp')
            ->where(function($q) {
                $q->where(DB::raw('LOWER(products.brand)'), 'NOT LIKE', '%iphone%')
                  ->where(DB::raw('LOWER(products.brand)'), 'NOT LIKE', '%apple%');
            });

        if ($startDate) $androidModelsQuery->where('stock_outs.reporting_date', '>=', $startDate);
        if ($endDate) $androidModelsQuery->where('stock_outs.reporting_date', '<=', $endDate);

        $androidModels = $androidModelsQuery->select(
            DB::raw('COALESCE(stock_outs.branch_id, users.branch_id) as branch_id'),
            DB::raw('COALESCE(stock_outs.online_shop_id, users.online_shop_id) as online_shop_id'),
            'products.name as model_name',
            DB::raw('COUNT(*) as count')
        )
        ->groupBy(DB::raw('COALESCE(stock_outs.branch_id, users.branch_id)'), DB::raw('COALESCE(stock_outs.online_shop_id, users.online_shop_id)'), 'products.name')
        ->orderBy('count', 'desc')
        ->get();

        $branches = DB::table('branches')
            ->where('is_active', true)
            ->when($user->hasRole('analist') && !$user->hasRole('super_admin'), function($q) {
                $excludedKeywords = (array) config('kasara.excluded_keywords', []);
                foreach ($excludedKeywords as $kw) $q->where('name', 'not ilike', "%$kw%");
            })
            ->get();

        $branchStats = $branches->map(function($b) use ($baseStats, $itemCounts, $androidModels) {
            $branchBase = $baseStats->where('branch_id', $b->id);
            $branchItems = $itemCounts->where('branch_id', $b->id);
            $topModels = $androidModels->where('branch_id', $b->id)->take(3)->pluck('model_name')->toArray();
            
            // Total Omset = Sales (Consistent with Dashboard Ranking)
            $omset = $branchBase->sum('sales_omset');
            $omsetBersih = $branchBase->sum('omset_bersih');

            return (object) [
                'id' => $b->id,
                'name' => $b->name,
                'type' => 'Offline',
                'omset' => (float) $omset,
                'omset_bersih' => (float) $omsetBersih,
                'dp_amount' => (float) $branchBase->sum('dp_amount'),
                'pelunasan_dp_amount' => (float) $branchBase->sum('pelunasan_dp_amount'),
                'balancing_penjualan_amount' => (float) $branchBase->sum('balancing_penjualan_amount'),
                'balancing_pembayaran_amount' => (float) $branchBase->sum('balancing_pembayaran_amount'),
                'transaction_count' => (int) $branchBase->sum('transaction_count'),
                'refund_count' => (int) $branchBase->sum('refund_count'),
                'refund_amount' => (float) $branchBase->sum('refund_amount'),
                'refund_dp_amount' => (float) $branchBase->sum('refund_dp_amount'),
                'ab_count' => (int) $branchBase->sum('ab_count'),
                'ab_amount' => (float) $branchBase->sum('ab_amount'),
                'iphone_count' => (int) $branchItems->sum('iphone_count'),
                'android_count' => (int) $branchItems->sum('android_count'),
                'top_android_models' => $topModels
            ];
        });

        $shops = DB::table('online_shops')
            ->where('is_active', true)
            ->when($user->hasRole('analist') && !$user->hasRole('super_admin'), function($q) {
                $excludedKeywords = (array) config('kasara.excluded_keywords', []);
                foreach ($excludedKeywords as $kw) $q->where('name', 'not ilike', "%$kw%");
            })
            ->get();

        $onlineStats = $shops->map(function($s) use ($baseStats, $itemCounts, $androidModels) {
            $shopBase = $baseStats->where('online_shop_id', $s->id);
            $shopItems = $itemCounts->where('online_shop_id', $s->id);
            $topModels = $androidModels->where('online_shop_id', $s->id)->take(3)->pluck('model_name')->toArray();
            
            $omset = $shopBase->sum('sales_omset');
            $omsetBersih = $shopBase->sum('omset_bersih');

            return (object) [
                'id' => $s->id,
                'name' => $s->name,
                'type' => 'Online',
                'omset' => (float) $omset,
                'omset_bersih' => (float) $omsetBersih,
                'dp_amount' => (float) $shopBase->sum('dp_amount'),
                'pelunasan_dp_amount' => (float) $shopBase->sum('pelunasan_dp_amount'),
                'balancing_penjualan_amount' => (float) $shopBase->sum('balancing_penjualan_amount'),
                'balancing_pembayaran_amount' => (float) $shopBase->sum('balancing_pembayaran_amount'),
                'transaction_count' => (int) $shopBase->sum('transaction_count'),
                'refund_count' => (int) $shopBase->sum('refund_count'),
                'refund_amount' => (float) $shopBase->sum('refund_amount'),
                'refund_dp_amount' => (float) $shopBase->sum('refund_dp_amount'),
                'ab_count' => (int) $shopBase->sum('ab_count'),
                'ab_amount' => (float) $shopBase->sum('ab_amount'),
                'iphone_count' => (int) $shopItems->sum('iphone_count'),
                'android_count' => (int) $shopItems->sum('android_count'),
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
            $report = $report->filter(fn($item) => $item->omset > 0 || $item->transaction_count > 0);
        }

        $report = $report->sortByDesc('omset')->values();

        return response()->json($report);
      } catch (\Throwable $e) {
          \Illuminate\Support\Facades\Log::error('Ranking Report Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
          return response()->json(['error' => 'Terjadi kesalahan saat memuat ranking: ' . $e->getMessage()], 500);
      }
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
            'branches' => \App\Models\Branch::orderBy('name')
                ->when($user->hasRole('analist') && !$user->hasRole('super_admin'), function($q) {
                    $excludedKeywords = (array) config('kasara.excluded_keywords', []);
                    foreach ($excludedKeywords as $kw) $q->where('name', 'not ilike', "%$kw%");
                })
                ->get(['id', 'name']),
            'online_shops' => \App\Models\OnlineShop::orderBy('name')
                ->when($user->hasRole('analist') && !$user->hasRole('super_admin'), function($q) {
                    $excludedKeywords = (array) config('kasara.excluded_keywords', []);
                    foreach ($excludedKeywords as $kw) $q->where('name', 'not ilike', "%$kw%");
                })
                ->get(['id', 'name'])
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
                    DB::raw('count(*) as qty')
                )
                ->whereIn('product_details.status', ['available', 'booking', 'returned', 'process', 'service'])
                ->whereNull('product_details.deleted_at')
                ->whereNull('products.deleted_at');

            if ($user->hasRole('analist') && !$user->hasRole('super_admin')) {
                $excludedKeywords = (array) config('kasara.excluded_keywords', []);
                $currentStock->where(function ($q) use ($excludedKeywords) {
                    foreach (['branch', 'online_shop', 'warehouse', 'distributor'] as $pType) {
                        $q->whereNot(function ($sq) use ($pType, $excludedKeywords) {
                            $sq->where('product_details.placement_type', $pType);
                            $modelClass = match ($pType) {
                                'branch' => \App\Models\Branch::class,
                                'online_shop' => \App\Models\OnlineShop::class,
                                'warehouse' => \App\Models\Warehouse::class,
                                'distributor' => \App\Models\Distributor::class,
                            };
                            $sq->whereHasMorph('placement', [$modelClass], function ($pq) use ($excludedKeywords) {
                                $pq->where(function ($nq) use ($excludedKeywords) {
                                    foreach ($excludedKeywords as $kw) $nq->orWhere('name', 'ilike', "%$kw%");
                                });
                            });
                        });
                    }
                });
            }

            if (!empty($filterBranchIds)) $currentStock->whereIn('product_details.placement_id', $filterBranchIds)->where('product_details.placement_type', 'branch');
            elseif (!empty($filterOnlineShopIds)) $currentStock->whereIn('product_details.placement_id', $filterOnlineShopIds)->where('product_details.placement_type', 'online_shop');
            elseif (!empty($filterWarehouseIds)) $currentStock->whereIn('product_details.placement_id', $filterWarehouseIds)->where('product_details.placement_type', 'warehouse');

            $currentStock->groupBy('products.id', 'products.brand', 'products.name', 'products.type', 'products.has_imei', 'product_details.storage', 'product_details.condition');

            $soldCategories = ['penjualan_offline', 'shopee', 'orderan_online', 'penjualan_store', 'bundling'];
            $keluarCategories = ['giveaway_customer', 'hadiah', 'brand_ambassador', 'event_sponsorship', 'promo', 'inventaris', 'hilang'];
            $incomingAuditCategories = ['barang_masuk', 'pembelian', 'cancel_penjualan', 'retur_customer', 'refund'];

            $defaultRow = [
                'initial' => 0, 
                'in_total' => 0, 'in_manual' => 0, 'in_tt' => 0, 'in_tu' => 0, 'in_dw' => 0, 'in_rf' => 0, 'in_ab' => 0,
                'out_total' => 0, 'out_sold' => 0, 'out_tt' => 0, 'out_tu' => 0, 'out_dw' => 0, 'out_pindah' => 0, 'out_kesalahan' => 0, 'out_keluar' => 0, 'out_hilang' => 0, 'out_retur' => 0,
                'final' => 0
            ];

            // Helper function for consistent naming and grouping
            $normalize = function($brand, $name, $storage, $condition, $isActuallyHp = true) {
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
                
                if ($isActuallyHp) {
                    if ($s) $dispName .= " ({$s})";
                    $dispName .= " (" . ($c === 'new' ? 'Baru' : ($c === 'ex_ibox' ? 'Ex iBox' : 'Bekas')) . ")";
                }
                
                return [
                    'display' => $dispName,
                    // Bulletproof key: only letters, numbers, and plus sign (for Pro+)
                    'key' => md5(preg_replace('/[^a-z0-9\+]/', '', strtolower($dispName)))
                ];
            };

            // 2. Initial Data from Current Stock (HP)
            foreach($currentStock->get() as $s) {
                $isHpItem = ($s->type ?? ($s->has_imei ? 'hp' : 'non-hp')) === 'hp' || $s->has_imei;
                $norm = $normalize($s->brand, $s->product_name, $s->storage, $s->condition, $isHpItem);
                $groupKey = $norm['key'];

                if (!isset($results[$groupKey])) {
                    $results[$groupKey] = array_merge($defaultRow, [
                        'name' => $norm['display'],
                        'type' => $s->type ?? ($s->has_imei ? 'hp' : 'non-hp'),
                        'has_imei' => $s->has_imei,
                    ]);
                }
                $results[$groupKey]['final'] += $s->qty;
            }

            // 2b. Initial Data from Current Stock (NON HP)
            $currentNonHpStock = \App\Models\Inventory::join('products', 'inventories.product_id', '=', 'products.id')
                ->select(
                    'products.id as product_id',
                    'products.brand',
                    'products.name as product_name',
                    'products.type',
                    DB::raw('SUM(inventories.quantity) as qty')
                );

            if (!empty($filterBranchIds)) $currentNonHpStock->whereIn('inventories.placement_id', $filterBranchIds)->where('inventories.placement_type', 'branch');
            elseif (!empty($filterOnlineShopIds)) $currentNonHpStock->whereIn('inventories.placement_id', $filterOnlineShopIds)->where('inventories.placement_type', 'online_shop');
            elseif (!empty($filterWarehouseIds)) $currentNonHpStock->whereIn('inventories.placement_id', $filterWarehouseIds)->where('inventories.placement_type', 'warehouse');

            $currentNonHpStock->groupBy('products.id', 'products.brand', 'products.name', 'products.type');

            foreach($currentNonHpStock->get() as $s) {
                // Non-HP implicitly passes null for storage and condition
                $norm = $normalize($s->brand, $s->product_name, null, null, false);
                $groupKey = $norm['key'];

                if (!isset($results[$groupKey])) {
                    $results[$groupKey] = array_merge($defaultRow, [
                        'name' => $norm['display'],
                        'type' => $s->type ?? 'non-hp',
                        'has_imei' => false,
                    ]);
                }
                $results[$groupKey]['final'] += $s->qty;
            }

            // 3. Mutations (In - InventoryLog)
            $dayLogs = \App\Models\InventoryLog::with('productDetail.product')
                ->where('created_at', '>=', $resetTime)
                ->where('created_at', '<', $endTime)
                ->where('type', 'in');

            if ($user->hasRole('analist') && !$user->hasRole('super_admin')) {
                $excludedKeywords = (array) config('kasara.excluded_keywords', []);
                $dayLogs->where(function ($q) use ($excludedKeywords) {
                    $q->whereNot(function ($sq) use ($excludedKeywords) {
                        $sq->whereNotNull('branch_id')->whereHas('branch', function ($pq) use ($excludedKeywords) {
                            foreach ($excludedKeywords as $kw) $pq->orWhere('name', 'ilike', "%$kw%");
                        });
                    })->whereNot(function ($sq) use ($excludedKeywords) {
                        $sq->whereNotNull('online_shop_id')->whereHas('onlineShop', function ($pq) use ($excludedKeywords) {
                            foreach ($excludedKeywords as $kw) $pq->orWhere('name', 'ilike', "%$kw%");
                        });
                    })->whereNot(function ($sq) use ($excludedKeywords) {
                        $sq->whereNotNull('warehouse_id')->whereHas('warehouse', function ($pq) use ($excludedKeywords) {
                            foreach ($excludedKeywords as $kw) $pq->orWhere('name', 'ilike', "%$kw%");
                        });
                    })->whereNot(function ($sq) use ($excludedKeywords) {
                        $sq->whereNotNull('distributor_id')->whereHas('distributor', function ($pq) use ($excludedKeywords) {
                            foreach ($excludedKeywords as $kw) $pq->orWhere('name', 'ilike', "%$kw%");
                        });
                    });
                });
            }

            if (!empty($filterBranchIds)) $dayLogs->whereIn('branch_id', $filterBranchIds);
            elseif (!empty($filterOnlineShopIds)) $dayLogs->where('online_shop_id', $filterOnlineShopIds[0]);

            $dayLogs->chunk(500, function ($logs) use (&$results, $defaultRow, $normalize) {
                foreach($logs as $log) {
                    if ($log->description && (str_contains($log->description, 'Pindah Cabang') || str_contains($log->description, 'Resi:'))) continue;
                    $pd = $log->productDetail;
                    if (!$pd) continue;

                    $isHpItem = ($pd->product->type ?? ($pd->product->has_imei ? 'hp' : 'non-hp')) === 'hp' || $pd->product->has_imei;
                    $norm = $normalize($pd->product->brand ?? '', $pd->product->name ?? '', $pd->storage, $pd->condition, $isHpItem);
                    $groupKey = $norm['key'];

                    if (!isset($results[$groupKey])) {
                        $results[$groupKey] = array_merge($defaultRow, [
                            'name' => $norm['display'],
                            'type' => $pd->product->type ?? ($pd->product->has_imei ? 'hp' : 'non-hp'),
                            'has_imei' => $pd->product->has_imei,
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
            });

            // 4. Mutations (Out and Incoming StockOuts)
            $dayOuts = StockOut::with(['items.product', 'nonHpItems.product'])
                ->where('created_at', '>=', $resetTime)
                ->where('created_at', '<', $endTime)
                ->where('status', '!=', 'cancelled');
            
            if ($user->hasRole('analist') && !$user->hasRole('super_admin')) {
                $excludedKeywords = (array) config('kasara.excluded_keywords', []);
                $dayOuts->where(function ($q) use ($excludedKeywords) {
                    $q->whereNot(function ($sq) use ($excludedKeywords) {
                        $sq->whereNotNull('branch_id')->whereHas('branch', function ($pq) use ($excludedKeywords) {
                            foreach ($excludedKeywords as $kw) $pq->orWhere('name', 'ilike', "%$kw%");
                        });
                    })->whereNot(function ($sq) use ($excludedKeywords) {
                        $sq->whereNotNull('online_shop_id')->whereHas('onlineShop', function ($pq) use ($excludedKeywords) {
                            foreach ($excludedKeywords as $kw) $pq->orWhere('name', 'ilike', "%$kw%");
                        });
                    })->whereNot(function ($sq) use ($excludedKeywords) {
                        $sq->whereNotNull('warehouse_id')->whereHas('warehouse', function ($pq) use ($excludedKeywords) {
                            foreach ($excludedKeywords as $kw) $pq->orWhere('name', 'ilike', "%$kw%");
                        });
                    });
                });
            }

            if (!empty($filterBranchIds)) {
                $dayOuts->where(function($q) use ($filterBranchIds) {
                    $q->whereIn('branch_id', $filterBranchIds)->orWhere('destination_id', $filterBranchIds);
                });
            }

            $dayOuts->chunk(500, function ($outs) use (&$results, $defaultRow, $normalize, $incomingAuditCategories, $soldCategories) {
                foreach($outs as $out) {
                    $cat = $out->category;
                    $isAB = $cat === 'angkat_barang';
                    $isIncoming = in_array($cat, $incomingAuditCategories) || $isAB;
                    
                    foreach($out->items as $pd) {
                        $isHpItem = ($pd->product->type ?? ($pd->product->has_imei ? 'hp' : 'non-hp')) === 'hp' || $pd->product->has_imei;
                        $norm = $normalize($pd->product->brand ?? '', $pd->product->name ?? '', $pd->storage, $pd->condition, $isHpItem);
                        $groupKey = $norm['key'];

                        if (!isset($results[$groupKey])) {
                            $results[$groupKey] = array_merge($defaultRow, [
                                'name' => $norm['display'],
                                'type' => $pd->product->type ?? ($pd->product->has_imei ? 'hp' : 'non-hp'),
                                'has_imei' => $pd->product->has_imei,
                            ]);
                        }
                        
                        if ($isIncoming) {
                            $results[$groupKey]['in_total']++;
                            if ($isAB) $results[$groupKey]['in_ab']++;
                            elseif ($cat === 'refund') $results[$groupKey]['in_rf']++;
                            else $results[$groupKey]['in_manual']++;
                        } else {
                            $results[$groupKey]['out_total']++;
                            $cat = $out->category;
                            if (in_array($cat, $soldCategories)) $results[$groupKey]['out_sold']++;
                            elseif ($cat === 'retur') $results[$groupKey]['out_retur']++;
                            else $results[$groupKey]['out_keluar']++;
                        }
                    }

                    foreach($out->nonHpItems as $nhi) {
                        $norm = $normalize($nhi->product->brand ?? '', $nhi->product->name ?? '', null, null, false);
                        $groupKey = $norm['key'];

                        if (!isset($results[$groupKey])) {
                            $results[$groupKey] = array_merge($defaultRow, [
                                'name' => $norm['display'],
                                'type' => $nhi->product->type ?? 'non-hp',
                                'has_imei' => false,
                            ]);
                        }
                        $qty = $nhi->quantity;
                        if ($isIncoming) {
                            $results[$groupKey]['in_total'] += $qty;
                            if ($cat === 'refund') $results[$groupKey]['in_rf'] += $qty;
                            else $results[$groupKey]['in_manual'] += $qty;
                        } else {
                            $results[$groupKey]['out_total'] += $qty;
                            if (in_array($cat, $soldCategories)) $results[$groupKey]['out_sold'] += $qty;
                            elseif ($cat === 'retur') $results[$groupKey]['out_retur'] += $qty;
                            else $results[$groupKey]['out_keluar'] += $qty;
                        }
                    }
                }
            });

            // 5. Final Calculation: Initial = Final - In + Out
            foreach ($results as $key => $val) {
                $results[$key]['initial'] = $results[$key]['final'] - $results[$key]['in_total'] + $results[$key]['out_total'];
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
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $query = ExportLog::with(['user.roles', 'user.branch', 'user.onlineShop', 'user.warehouse', 'user.distributor']);

        if (!$user->hasRole(['super_admin', 'analist', 'analis'])) {
            $accessibleBranchIds = $user->getAccessibleBranchIds();
            $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();
            $accessibleWarehouseIds = $user->getAccessibleWarehouseIds();
            $accessibleDistributorIds = $user->getAccessibleDistributorIds();

            $query->where(function ($q) use ($user, $accessibleBranchIds, $accessibleOnlineShopIds, $accessibleWarehouseIds, $accessibleDistributorIds) {
                $q->whereIn('params->branch_id', $accessibleBranchIds)
                  ->orWhereIn('params->online_shop_id', $accessibleOnlineShopIds)
                  ->orWhereIn('params->warehouse_id', $accessibleWarehouseIds)
                  ->orWhereIn('params->distributor_id', $accessibleDistributorIds)
                  ->orWhere('user_id', $user->id);
            });
        }

        // Filter by request location params if provided
        if ($request->filled('branch_id')) {
            $query->where('params->branch_id', $request->query('branch_id'));
        }
        if ($request->filled('online_shop_id')) {
            $query->where('params->online_shop_id', $request->query('online_shop_id'));
        }
        if ($request->filled('warehouse_id')) {
            $query->where('params->warehouse_id', $request->query('warehouse_id'));
        }
        if ($request->filled('distributor_id')) {
            $query->where('params->distributor_id', $request->query('distributor_id'));
        }

        $perPage = $request->query('per_page', 20);
        $history = $query->latest()->paginate($perPage);

        $history->getCollection()->transform(function($log) {
            $params = $log->params;
            $locationName = 'Semua Lokasi';
            $resolvedFromParams = false;
            
            if (!empty($params)) {
                if (!empty($params['branch_id'])) {
                    $branch = \App\Models\Branch::find($params['branch_id']);
                    $locationName = $branch ? '[Cabang] ' . $branch->name : 'Cabang #' . $params['branch_id'];
                    $resolvedFromParams = true;
                } elseif (!empty($params['online_shop_id'])) {
                    $shop = \App\Models\OnlineShop::find($params['online_shop_id']);
                    $locationName = $shop ? '[Toko] ' . $shop->name : 'Toko #' . $params['online_shop_id'];
                    $resolvedFromParams = true;
                } elseif (!empty($params['warehouse_id'])) {
                    $warehouse = \App\Models\Warehouse::find($params['warehouse_id']);
                    $locationName = $warehouse ? '[Gudang] ' . $warehouse->name : 'Gudang #' . $params['warehouse_id'];
                    $resolvedFromParams = true;
                } elseif (!empty($params['distributor_id'])) {
                    $distributor = \App\Models\Distributor::find($params['distributor_id']);
                    $locationName = $distributor ? '[Distributor] ' . $distributor->name : 'Distributor #' . $params['distributor_id'];
                    $resolvedFromParams = true;
                }
            }
            
            // Fallback to user primary assignment if not resolved from params
            if (!$resolvedFromParams && $log->user) {
                if ($log->user->branch) {
                    $locationName = '[Cabang] ' . $log->user->branch->name;
                } elseif ($log->user->onlineShop) {
                    $locationName = '[Toko] ' . $log->user->onlineShop->name;
                } elseif ($log->user->warehouse) {
                    $locationName = '[Gudang] ' . $log->user->warehouse->name;
                } elseif ($log->user->distributor) {
                    $locationName = '[Distributor] ' . $log->user->distributor->name;
                }
            }
            
            $log->location_name = $locationName;
            return $log;
        });

        return response()->json($history);
    }
}
