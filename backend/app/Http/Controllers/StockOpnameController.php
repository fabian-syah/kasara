<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\ProductDetail;
use App\Models\StockOpname;
use App\Models\StockOpnameExcessItem;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class StockOpnameController extends Controller
{
    /**
     * Auto-ensure stock opname tables exist before operations.
     */
    public static function ensureTablesExist()
    {
        if (!Schema::hasTable('stock_opnames')) {
            try {
                $path = database_path('migrations/2026_11_05_100000_create_stock_opnames_tables.php');
                if (file_exists($path)) {
                    $migration = require $path;
                    if (is_object($migration) && method_exists($migration, 'up')) {
                        $migration->up();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('StockOpnameController auto-migration note: ' . $e->getMessage());
            }
        }

        // Ensure executor_role column exists on stock_opnames
        if (Schema::hasTable('stock_opnames')) {
            try {
                if (!Schema::hasColumn('stock_opnames', 'executor_role')) {
                    DB::statement("ALTER TABLE stock_opnames ADD COLUMN IF NOT EXISTS executor_role VARCHAR(30) DEFAULT 'store'");
                    DB::statement("CREATE INDEX IF NOT EXISTS stock_opnames_executor_role_idx ON stock_opnames (executor_role)");
                }
                if (Schema::hasColumn('stock_opnames', 'executor_role')) {
                    DB::statement("UPDATE stock_opnames SET executor_role = 'store' WHERE executor_role IS NULL");
                }
            } catch (\Throwable $e) {
                try {
                    if (!Schema::hasColumn('stock_opnames', 'executor_role')) {
                        Schema::table('stock_opnames', function (\Illuminate\Database\Schema\Blueprint $table) {
                            $table->string('executor_role', 30)->default('store')->index();
                        });
                    }
                } catch (\Throwable $e2) {
                    Log::warning('StockOpnameController executor_role migration note: ' . $e2->getMessage());
                }
            }

            // Normalize 'date' column so past records align with the 05:00 AM daily cycle
            try {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("UPDATE stock_opnames SET date = (created_at - INTERVAL '5 hour')::date WHERE created_at IS NOT NULL");
                } else {
                    DB::statement("UPDATE stock_opnames SET date = DATE(SUBDATE(created_at, INTERVAL 5 HOUR)) WHERE created_at IS NOT NULL");
                }
            } catch (\Throwable $e) {
                Log::warning('StockOpnameController date normalization note: ' . $e->getMessage());
            }
        }
    }

    /**
     * Determine user role scope for Stock Opname segregation (store, leader, audit, admin).
     */
    public static function getUserRoleScope($user): string
    {
        if (!$user) {
            return 'store';
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('audit')) {
            return 'audit';
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('leader')) {
            return 'leader';
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'owner', 'admin_produk', 'analist', 'analis'])) {
            return 'admin';
        }

        return 'store';
    }

    /**
     * Get the current logical business cycle range (resets at 05:00 AM daily).
     */
    public static function getLogicalCycleRange()
    {
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $sessionStart = $logicalNow->copy()->setTime(5, 0, 0);
        $sessionEnd = $sessionStart->copy()->addDay()->subSecond();
        $sessionDate = $logicalNow->toDateString();

        return [
            'start' => $sessionStart,
            'end' => $sessionEnd,
            'date' => $sessionDate,
            'reset_time' => '05:00 AM',
        ];
    }

    /**
     * Get accessible branches for Stock Opname.
     */
    public function getBranches(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['data' => []], 401);
        }

        $isUnrestricted = $user->hasAnyRole(['super_admin', 'owner', 'admin_produk', 'analist', 'analis'])
            || in_array(strtolower($user->role ?? ''), ['super_admin', 'owner', 'admin_produk', 'analist', 'analis']);

        // Unrestricted roles can access all branches
        if ($isUnrestricted) {
            $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
            return response()->json(['data' => $branches]);
        }

        $isLeader = $user->hasRole('leader') || strtolower($user->role ?? '') === 'leader';
        $isAudit = $user->hasRole('audit') || strtolower($user->role ?? '') === 'audit';

        // Leader or Audit: get assigned branches via placements or direct branch_id
        if ($isLeader || $isAudit) {
            $branchIds = (array) ($user->getAccessibleBranchIds() ?: []);
            if ($user->branch_id) {
                $branchIds[] = $user->branch_id;
            }
            if (method_exists($user, 'placements')) {
                $placementBranchIds = $user->placements()
                    ->where(function ($q) {
                        $q->whereIn('model_type', ['branch', 'Branch', 'App\Models\Branch'])
                          ->orWhereRaw("LOWER(model_type) LIKE '%branch%'");
                    })
                    ->pluck('model_id')
                    ->toArray();
                $branchIds = array_merge($branchIds, $placementBranchIds);
            }
            $branchIds = array_unique(array_filter(array_map('intval', $branchIds)));

            if (empty($branchIds)) {
                // If no specific branch restriction, allow active branches
                $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
            } else {
                $branches = Branch::whereIn('id', $branchIds)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
            }

            if ($branches->isEmpty()) {
                $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
            }

            return response()->json(['data' => $branches]);
        }

        // Toko Offline / CS: get accessible branches
        $branchIds = (array) ($user->getAccessibleBranchIds() ?: []);
        if ($user->branch_id) {
            $branchIds[] = $user->branch_id;
        }
        if (method_exists($user, 'placements')) {
            $placementBranchIds = $user->placements()
                ->where(function ($q) {
                    $q->whereIn('model_type', ['branch', 'Branch', 'App\Models\Branch'])
                      ->orWhereRaw("LOWER(model_type) LIKE '%branch%'");
                })
                ->pluck('model_id')
                ->toArray();
            $branchIds = array_merge($branchIds, $placementBranchIds);
        }
        $branchIds = array_unique(array_filter(array_map('intval', $branchIds)));

        if (!empty($branchIds)) {
            $branches = Branch::whereIn('id', $branchIds)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
            if ($branches->isEmpty()) {
                $branches = Branch::whereIn('id', $branchIds)->get(['id', 'name', 'code']);
            }
        } else {
            $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        }

        return response()->json(['data' => $branches]);
    }

    /**
     * Get CS accounts for a specific branch.
     */
    public function getBranchCsUsers(Request $request, $branchId)
    {
        $branchId = (int) $branchId;
        if (!$branchId) {
            return response()->json(['data' => []]);
        }

        // Search active inventory/CS users attached to this branch
        $csUsers = User::role('inventory')
            ->where('is_active', true)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereHas('placements', function ($pq) use ($branchId) {
                        $pq->where('model_type', 'branch')->where('model_id', $branchId);
                    });
            })
            ->with(['createdBy:id,name,full_name'])
            ->select('id', 'name', 'username', 'branch_id', 'photo', 'photo_inventory', 'created_by')
            ->orderBy('name')
            ->get();

        // If no dedicated CS found, also check users with role sales / toko_offline or general users for that branch
        if ($csUsers->isEmpty()) {
            $csUsers = User::where('branch_id', $branchId)
                ->where('is_active', true)
                ->with(['createdBy:id,name,full_name'])
                ->select('id', 'name', 'username', 'branch_id', 'photo', 'photo_inventory', 'created_by')
                ->orderBy('name')
                ->get();
        }

        return response()->json(['data' => $csUsers]);
    }

    /**
     * Get pending items to be audited today (excluding items already audited today).
     */
        public function getPendingItems(Request $request)
    {
        self::ensureTablesExist();

        $request->validate([
            'branch_id' => 'required|integer',
            'category_type' => 'nullable|in:hp,non_hp',
            'method' => 'nullable|in:by_unit,by_imei',
            'brand' => 'nullable',
            'brands' => 'nullable',
            'condition' => 'nullable',
            'conditions' => 'nullable',
            'product_name' => 'nullable',
        ]);

        $branchId = (int) $request->branch_id;
        $categoryType = $request->query('category_type', 'hp');
        $method = $request->query('method', 'by_unit');
        $selectedProductName = $request->filled('product_name') ? (string) $request->product_name : null;

        // Multi-brand extraction (supports array brands[] or comma-separated string brand/brands)
        $rawBrands = $request->input('brands') ?? $request->input('brand');
        $selectedBrands = [];
        if (!empty($rawBrands)) {
            if (is_array($rawBrands)) {
                $selectedBrands = array_filter(array_map('trim', $rawBrands));
            } else {
                $selectedBrands = array_filter(array_map('trim', explode(',', (string)$rawBrands)));
            }
            $selectedBrands = array_values(array_filter($selectedBrands, fn($b) => strtolower($b) !== 'all' && strtolower($b) !== 'semua'));
        }

        // Multi-condition extraction (supports array conditions[] or comma-separated string condition/conditions)
        $rawConditions = $request->input('conditions') ?? $request->input('condition');
        $selectedConditions = [];
        if (!empty($rawConditions)) {
            if (is_array($rawConditions)) {
                $selectedConditions = array_filter(array_map(fn($c) => strtolower(trim((string)$c)), $rawConditions));
            } else {
                $selectedConditions = array_filter(array_map(fn($c) => strtolower(trim((string)$c)), explode(',', (string)$rawConditions)));
            }
            $selectedConditions = array_values(array_filter($selectedConditions, fn($c) => $c !== 'all' && $c !== 'semua' && $c !== 'belum_dipilih'));
        }

        // Normalizer for product condition (new, second, ex_ibox)
        $normalizeCondition = function ($raw) {
            $c = strtolower(trim((string)$raw));
            if ($c === 'new' || str_contains($c, 'baru')) {
                return 'new';
            }
            if ($c === 'ex_ibox' || str_contains($c, 'ibox')) {
                return 'ex_ibox';
            }
            return 'second';
        };

        $getConditionLabel = function ($cond) {
            return match ($cond) {
                'new' => 'Baru',
                'ex_ibox' => 'Ex iBox',
                default => 'Second',
            };
        };

        // Brand & Condition matcher helpers
        $matchesBrand = function ($bName) use ($selectedBrands) {
            if (empty($selectedBrands)) return true;
            foreach ($selectedBrands as $sb) {
                if (strcasecmp($sb, $bName) === 0) return true;
            }
            return false;
        };

        $matchesCondition = function ($itemCond) use ($selectedConditions) {
            if (empty($selectedConditions)) return true;
            return in_array($itemCond, $selectedConditions);
        };

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $userRoleScope = self::getUserRoleScope($user);

        // If privileged/admin, allow explicit scope override via query param if provided
        if ($userRoleScope === 'admin' && $request->filled('executor_role') && $request->executor_role !== 'all') {
            $effectiveRoleScope = $request->executor_role;
        } else {
            $effectiveRoleScope = $userRoleScope;
        }

        $cycle = self::getLogicalCycleRange();

        // Check already audited items in the current 05:00 AM daily cycle for THIS specific role scope
        $auditedImeis = [];
        $auditedProductNamesByUnit = [];
        $auditedNonHpProductIds = [];

        if (Schema::hasTable('stock_opnames') && Schema::hasTable('stock_opname_items')) {
            $todayOpnameQuery = StockOpname::where('branch_id', $branchId)
                ->whereBetween('created_at', [$cycle['start'], $cycle['end']]);

            $hasExecutorRole = Schema::hasColumn('stock_opnames', 'executor_role');

            // Independent SO cycles: Audit, Leader, and Store do NOT block each other!
            if ($effectiveRoleScope === 'audit') {
                $todayOpnameQuery->where(function ($q) use ($hasExecutorRole) {
                    if ($hasExecutorRole) {
                        $q->where('executor_role', 'audit');
                    }
                    $q->orWhereHas('user', fn($uq) => $uq->whereHas('roles', fn($rq) => $rq->where('name', 'audit')));
                });
            } elseif ($effectiveRoleScope === 'leader') {
                $todayOpnameQuery->where(function ($q) use ($hasExecutorRole) {
                    if ($hasExecutorRole) {
                        $q->where('executor_role', 'leader');
                    }
                    $q->orWhereHas('user', fn($uq) => $uq->whereHas('roles', fn($rq) => $rq->where('name', 'leader')));
                });
            } elseif ($effectiveRoleScope === 'admin') {
                if ($hasExecutorRole) {
                    $todayOpnameQuery->where('executor_role', 'admin');
                } else {
                    $todayOpnameQuery->whereHas('user', fn($uq) => $uq->whereHas('roles', fn($rq) => $rq->whereIn('name', ['super_admin', 'owner', 'analist'])));
                }
            } else {
                // Store / CS tier
                $todayOpnameQuery->where(function ($q) use ($hasExecutorRole) {
                    if ($hasExecutorRole) {
                        $q->where('executor_role', 'store')
                          ->orWhereNull('executor_role')
                          ->orWhere(function ($subQ) {
                              $subQ->whereNotIn('executor_role', ['audit', 'leader', 'admin'])
                                   ->whereHas('user', function ($uq) {
                                       $uq->whereDoesntHave('roles', fn($rq) => $rq->whereIn('name', ['audit', 'leader', 'super_admin', 'owner', 'analist']));
                                   });
                          });
                    } else {
                        $q->whereHas('user', function ($uq) {
                            $uq->whereDoesntHave('roles', fn($rq) => $rq->whereIn('name', ['audit', 'leader', 'super_admin', 'owner', 'analist']));
                        });
                    }
                });
            }

            $todayOpnameIds = $todayOpnameQuery->pluck('id');

            if ($todayOpnameIds->isNotEmpty()) {
                $auditedImeis = StockOpnameItem::whereIn('stock_opname_id', $todayOpnameIds)
                    ->whereNotNull('imei')
                    ->where('imei', '!=', '-')
                    ->pluck('imei')
                    ->map(fn($v) => strtoupper(trim((string)$v)))
                    ->toArray();

                $auditedItemsData = StockOpnameItem::whereIn('stock_opname_id', $todayOpnameIds)->get();
                foreach ($auditedItemsData as $ait) {
                    if ($ait->product_name) {
                        $bKey = strtoupper(trim($ait->brand ?? ''));
                        $pKey = strtoupper(trim($ait->product_name));
                        $sKey = strtoupper(trim($ait->storage ?? ''));
                        $rawAitCond = strtolower(trim((string)($ait->condition ?? '')));
                        if ($rawAitCond) {
                            $condNorm = ($rawAitCond === 'new' || str_contains($rawAitCond, 'baru')) ? 'new' : 'second';
                            $key = $bKey . '|' . $pKey . '|' . $sKey . '|' . $condNorm;
                            $auditedProductNamesByUnit[$key] = true;
                        }
                        $legacyKey = $bKey . '|' . $pKey . '|' . $sKey;
                        $auditedProductNamesByUnit[$legacyKey] = true;
                    }
                    if ($ait->product_id) {
                        $auditedNonHpProductIds[] = $ait->product_id;
                    }
                }
            }
        }

        if ($categoryType === 'hp') {
            // HP Query
            $hpQuery = ProductDetail::with([
                'product:id,name,brand,type,category',
                'placement:id,name',
                'distributor:id,name'
            ])
                ->where('placement_type', 'branch')
                ->where('placement_id', $branchId)
                ->whereIn('status', ['available', 'booking', 'returned', 'process'])
                ->whereHas('product', fn($q) => $q->where('type', 'hp')->orWhere('has_imei', true));

            $rawHp = $hpQuery->get();

            if ($method === 'by_unit') {
                // Group by Brand -> Tipe + Storage (GB) + Condition (New vs Second)
                $grouped = [];
                $pendingBrandsMap = [];
                $conditionsCount = ['all' => 0, 'new' => 0, 'second' => 0, 'ex_ibox' => 0];

                foreach ($rawHp as $item) {
                    $bName = trim($item->product?->brand ?? 'Unknown');
                    if (!$bName || $bName === '-') $bName = 'Other';

                    $pName = trim($item->product?->name ?? 'Unknown');
                    $storage = trim($item->storage ?? '');
                    if (!$storage && preg_match('/(\d+\s*(?:GB|TB))/i', $pName, $m)) {
                        $storage = strtoupper($m[1]);
                    }

                    $itemCond = $normalizeCondition($item->condition ?? 'second');

                    $key = strtoupper($bName) . '|' . strtoupper($pName) . '|' . strtoupper($storage) . '|' . $itemCond;
                    $legacyKey = strtoupper($bName) . '|' . strtoupper($pName) . '|' . strtoupper($storage);

                    if (isset($auditedProductNamesByUnit[$key]) || (isset($auditedProductNamesByUnit[$legacyKey]) && !isset($auditedProductNamesByUnit[$key]))) {
                        // Skip if already audited by unit today
                        continue;
                    }

                    // Count towards pending brands
                    $pendingBrandsMap[$bName] = ($pendingBrandsMap[$bName] ?? 0) + 1;

                    // Condition counts (respecting selectedBrands if chosen)
                    if ($matchesBrand($bName)) {
                        $conditionsCount['all']++;
                        if (isset($conditionsCount[$itemCond])) {
                            $conditionsCount[$itemCond]++;
                        }
                    }

                    if (!$matchesBrand($bName)) {
                        continue;
                    }

                    if (!$matchesCondition($itemCond)) {
                        continue;
                    }

                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'key' => $key,
                            'product_id' => $item->product_id,
                            'brand' => $bName,
                            'product_name' => $pName,
                            'storage' => $storage ?: '-',
                            'condition' => $itemCond,
                            'condition_label' => $getConditionLabel($itemCond),
                            'system_qty' => 0,
                            'physical_qty' => 0,
                            'difference' => 0,
                            'is_audited_today' => false,
                        ];
                    }
                    $grouped[$key]['system_qty']++;
                }

                // Available brands only includes brands that STILL HAVE PENDING UNITS to be audited
                arsort($pendingBrandsMap);
                $availableBrands = [];
                foreach ($pendingBrandsMap as $bName => $bCount) {
                    $availableBrands[] = [
                        'brand' => $bName,
                        'count' => $bCount,
                    ];
                }

                $availableConditions = [
                    ['condition' => 'all', 'label' => 'Semua Kondisi', 'count' => $conditionsCount['all']],
                    ['condition' => 'new', 'label' => 'Baru (New)', 'count' => $conditionsCount['new']],
                    ['condition' => 'second', 'label' => 'Second (Bekas)', 'count' => $conditionsCount['second']],
                    ['condition' => 'ex_ibox', 'label' => 'Ex iBox', 'count' => $conditionsCount['ex_ibox']],
                ];

                // Sort descending by system_qty
                $resultList = array_values($grouped);
                usort($resultList, fn($a, $b) => $b['system_qty'] <=> $a['system_qty']);

                // Total available units is the count of units that are ACTUALLY pending
                $totalPendingUnits = array_sum(array_column($resultList, 'system_qty'));

                return response()->json([
                    'category_type' => 'hp',
                    'method' => 'by_unit',
                    'role_scope' => $effectiveRoleScope,
                    'role_label' => match($effectiveRoleScope) {
                        'audit' => 'SO Audit',
                        'leader' => 'SO Leader',
                        'admin' => 'SO Admin',
                        default => 'SO CS Toko',
                    },
                    'available_brands' => $availableBrands,
                    'available_conditions' => $availableConditions,
                    'total_available_units' => $totalPendingUnits,
                    'items' => $resultList,
                ]);
            } else {
                // Method: by_imei
                // Exclude already audited IMEIs
                $filteredHp = $rawHp;
                if (!empty($auditedImeis)) {
                    $filteredHp = $filteredHp->reject(function ($item) use ($auditedImeis) {
                        return in_array(strtoupper(trim((string)$item->imei)), $auditedImeis);
                    });
                }

                // Extract available brands from un-audited items
                $brandsMap = [];
                $conditionsCount = ['all' => 0, 'new' => 0, 'second' => 0, 'ex_ibox' => 0];

                foreach ($filteredHp as $item) {
                    $bName = trim($item->product?->brand ?? 'Unknown');
                    if (!$bName || $bName === '-') $bName = 'Other';
                    $brandsMap[$bName] = ($brandsMap[$bName] ?? 0) + 1;

                    if ($matchesBrand($bName)) {
                        $itemCond = $normalizeCondition($item->condition ?? 'second');
                        $conditionsCount['all']++;
                        if (isset($conditionsCount[$itemCond])) {
                            $conditionsCount[$itemCond]++;
                        }
                    }
                }
                arsort($brandsMap);
                $availableBrands = [];
                foreach ($brandsMap as $bName => $bCount) {
                    $availableBrands[] = [
                        'brand' => $bName,
                        'count' => $bCount,
                    ];
                }

                $availableConditions = [
                    ['condition' => 'all', 'label' => 'Belum Dipilih', 'count' => $conditionsCount['all']],
                    ['condition' => 'new', 'label' => 'Baru (New)', 'count' => $conditionsCount['new']],
                    ['condition' => 'second', 'label' => 'Second (Bekas)', 'count' => $conditionsCount['second']],
                    ['condition' => 'ex_ibox', 'label' => 'Ex iBox', 'count' => $conditionsCount['ex_ibox']],
                ];

                // Available types under selected brand and selected condition(s)
                $typesData = [];
                foreach ($filteredHp as $item) {
                    $bName = trim($item->product?->brand ?? 'Unknown');
                    if (!$bName || $bName === '-') $bName = 'Other';

                    if (!$matchesBrand($bName)) {
                        continue;
                    }

                    $itemCond = $normalizeCondition($item->condition ?? 'second');
                    if (!$matchesCondition($itemCond)) {
                        continue;
                    }

                    $pName = trim($item->product?->name ?? 'Unknown');
                    if (!isset($typesData[$pName])) {
                        $typesData[$pName] = [
                            'product_name' => $pName,
                            'brand' => $bName,
                            'count' => 0,
                        ];
                    }
                    $typesData[$pName]['count']++;
                }
                uasort($typesData, fn($a, $b) => $b['count'] <=> $a['count']);
                $availableTypes = array_values($typesData);

                $imeiList = [];
                if ($selectedProductName) {
                    $selectedHpItems = $filteredHp->filter(function ($item) use ($matchesBrand, $matchesCondition, $normalizeCondition, $selectedProductName) {
                        $bName = trim($item->product?->brand ?? 'Unknown');
                        if (!$bName || $bName === '-') $bName = 'Other';
                        if (!$matchesBrand($bName)) return false;

                        $itemCond = $normalizeCondition($item->condition ?? 'second');
                        if (!$matchesCondition($itemCond)) return false;

                        $pName = trim($item->product?->name ?? 'Unknown');
                        return strcasecmp($pName, $selectedProductName) === 0;
                    });

                    foreach ($selectedHpItems as $item) {
                        $itemCond = $normalizeCondition($item->condition ?? 'second');

                        $imeiList[] = [
                            'product_detail_id' => $item->id,
                            'product_id' => $item->product_id,
                            'brand' => $item->product?->brand ?? '-',
                            'product_name' => $item->product?->name ?? '-',
                            'storage' => $item->storage ?? '-',
                            'condition' => $itemCond,
                            'condition_label' => $getConditionLabel($itemCond),
                            'imei' => $item->imei,
                            'distributor' => $item->distributor?->name ?? '-',
                            'status' => $item->status,
                            'is_present' => null, // Default: belum dipilih!
                        ];
                    }
                }

                $totalPendingUnits = !empty($selectedBrands)
                    ? array_sum(array_intersect_key($brandsMap, array_flip($selectedBrands)))
                    : $filteredHp->count();

                return response()->json([
                    'category_type' => 'hp',
                    'method' => 'by_imei',
                    'role_scope' => $effectiveRoleScope,
                    'role_label' => match($effectiveRoleScope) {
                        'audit' => 'SO Audit',
                        'leader' => 'SO Leader',
                        'admin' => 'SO Admin',
                        default => 'SO CS Toko',
                    },
                    'available_brands' => $availableBrands,
                    'available_conditions' => $availableConditions,
                    'available_types' => $availableTypes,
                    'total_available_units' => $totalPendingUnits,
                    'items' => $imeiList,
                ]);
            }
        } else {
            // Category: Non-HP
            $nonHpQuery = Inventory::with([
                'product:id,name,brand,type,category',
                'placement:id,name',
                'distributor:id,name'
            ])
                ->where('placement_type', 'branch')
                ->where('placement_id', $branchId)
                ->where('quantity', '>', 0)
                ->whereHas('product', fn($q) => $q->where('type', 'non-hp')->orWhere('has_imei', false));

            if (!empty($auditedNonHpProductIds)) {
                $nonHpQuery->whereNotIn('product_id', $auditedNonHpProductIds);
            }

            $rawNonHp = $nonHpQuery->get();

            // Extract available brands for un-audited items
            $brandsMap = [];
            foreach ($rawNonHp as $item) {
                $bName = trim($item->product?->brand ?? 'Unknown');
                if (!$bName || $bName === '-') $bName = 'Non-Brand';
                $brandsMap[$bName] = ($brandsMap[$bName] ?? 0) + $item->quantity;
            }
            arsort($brandsMap);
            $availableBrands = [];
            foreach ($brandsMap as $bName => $bCount) {
                $availableBrands[] = [
                    'brand' => $bName,
                    'count' => $bCount,
                ];
            }

            // Group by Product
            $grouped = [];
            foreach ($rawNonHp as $item) {
                $bName = trim($item->product?->brand ?? 'Unknown');
                if (!$bName || $bName === '-') $bName = 'Non-Brand';

                if (!$matchesBrand($bName)) {
                    continue;
                }

                $pName = trim($item->product?->name ?? 'Unknown');
                $key = $item->product_id;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'key' => (string)$key,
                        'product_id' => $item->product_id,
                        'brand' => $bName,
                        'product_name' => $pName,
                        'storage' => '-',
                        'system_qty' => 0,
                        'physical_qty' => 0,
                        'difference' => 0,
                    ];
                }
                $grouped[$key]['system_qty'] += $item->quantity;
            }

            $resultList = array_values($grouped);
            usort($resultList, fn($a, $b) => $b['system_qty'] <=> $a['system_qty']);

            $totalPendingUnits = !empty($selectedBrands)
                ? array_sum(array_intersect_key($brandsMap, array_flip($selectedBrands)))
                : array_sum(array_column($resultList, 'system_qty'));

            return response()->json([
                'category_type' => 'non_hp',
                'method' => 'by_unit',
                'role_scope' => $effectiveRoleScope,
                'role_label' => match($effectiveRoleScope) {
                    'audit' => 'SO Audit',
                    'leader' => 'SO Leader',
                    'admin' => 'SO Admin',
                    default => 'SO CS Toko',
                },
                'available_brands' => $availableBrands,
                'total_available_units' => $totalPendingUnits,
                'items' => $resultList,
            ]);
        }
    }

    /**
     * Submit Stock Opname session.
     */
    public function store(Request $request)
    {
        self::ensureTablesExist();

        $request->validate([
            'branch_id' => 'required|integer',
            'cs_user_id' => 'required|integer',
            'category_type' => 'required|in:hp,non_hp',
            'method' => 'required|in:by_unit,by_imei',
            'brand' => 'nullable',
            'product_type' => 'nullable',
            'items' => 'required|array|min:1',
            'excess_items' => 'nullable|array',
            'photos' => 'nullable|array',
            'notes' => 'nullable|string|max:2000',
            'password' => 'nullable|string',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $csUser = User::find($request->cs_user_id);
        if (!$csUser) {
            return response()->json(['message' => 'Akun penanggung jawab tidak ditemukan.'], 404);
        }

        $isLeaderOrAudit = $user->hasAnyRole(['leader', 'audit', 'super_admin', 'owner', 'analist', 'analis'])
            || in_array(strtolower($user->role ?? ''), ['leader', 'audit', 'super_admin', 'owner', 'analist', 'analis']);

        // Toko offline role or accessing as store account requires password verification
        $isStoreAccount = !$isLeaderOrAudit && ($user->hasRole('toko_offline') || ($user->id !== $csUser->id));
        if ($isStoreAccount) {
            if (empty($request->password)) {
                return response()->json(['message' => 'Verifikasi password CS diperlukan untuk akun toko.'], 422);
            }

            $passwordMatches = false;
            if ($csUser->password && Hash::check($request->password, $csUser->password)) {
                $passwordMatches = true;
            } elseif ($user->password && Hash::check($request->password, $user->password)) {
                $passwordMatches = true;
            }

            if (!$passwordMatches) {
                return response()->json(['message' => 'Password verifikasi CS salah.'], 422);
            }
        }

        $userRoleScope = self::getUserRoleScope($user);

        // Prevent redundant submission if already audited in current 05:00 AM cycle within the SAME role scope
        $cycle = self::getLogicalCycleRange();
        $todayOpnameQuery = StockOpname::where('branch_id', $request->branch_id)
            ->whereBetween('created_at', [$cycle['start'], $cycle['end']]);

        $hasExecutorRole = Schema::hasColumn('stock_opnames', 'executor_role');

        if ($userRoleScope === 'audit') {
            $todayOpnameQuery->where(function ($q) use ($hasExecutorRole) {
                if ($hasExecutorRole) {
                    $q->where('executor_role', 'audit');
                }
                $q->orWhereHas('user', fn($uq) => $uq->whereHas('roles', fn($rq) => $rq->where('name', 'audit')));
            });
        } elseif ($userRoleScope === 'leader') {
            $todayOpnameQuery->where(function ($q) use ($hasExecutorRole) {
                if ($hasExecutorRole) {
                    $q->where('executor_role', 'leader');
                }
                $q->orWhereHas('user', fn($uq) => $uq->whereHas('roles', fn($rq) => $rq->where('name', 'leader')));
            });
        } elseif ($userRoleScope === 'admin') {
            if ($hasExecutorRole) {
                $todayOpnameQuery->where('executor_role', 'admin');
            } else {
                $todayOpnameQuery->whereHas('user', fn($uq) => $uq->whereHas('roles', fn($rq) => $rq->whereIn('name', ['super_admin', 'owner', 'analist'])));
            }
        } else {
            $todayOpnameQuery->where(function ($q) use ($hasExecutorRole) {
                if ($hasExecutorRole) {
                    $q->where('executor_role', 'store')
                      ->orWhereNull('executor_role')
                      ->orWhere(function ($subQ) {
                          $subQ->whereNotIn('executor_role', ['audit', 'leader', 'admin'])
                               ->whereHas('user', function ($uq) {
                                   $uq->whereDoesntHave('roles', fn($rq) => $rq->whereIn('name', ['audit', 'leader', 'super_admin', 'owner', 'analist']));
                               });
                      });
                } else {
                    $q->whereHas('user', function ($uq) {
                        $uq->whereDoesntHave('roles', fn($rq) => $rq->whereIn('name', ['audit', 'leader', 'super_admin', 'owner', 'analist']));
                    });
                }
            });
        }

        $todayOpnameIds = $todayOpnameQuery->pluck('id');

        if ($todayOpnameIds->isNotEmpty() && $request->method === 'by_imei') {
            $auditedImeis = StockOpnameItem::whereIn('stock_opname_id', $todayOpnameIds)
                ->whereNotNull('imei')
                ->where('imei', '!=', '-')
                ->pluck('imei')
                ->map(fn($v) => strtoupper(trim((string)$v)))
                ->toArray();

            $submittedImeis = array_filter(array_map(fn($it) => strtoupper(trim((string)($it['imei'] ?? ''))), $request->items));
            if (!empty($submittedImeis) && empty(array_diff($submittedImeis, $auditedImeis))) {
                return response()->json([
                    'message' => 'Seluruh IMEI yang dipilih sudah selesai di-SO hari ini. Sesi SO baru akan aktif otomatis besok pukul 05:00 AM.',
                    'success' => false,
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            // Save uploaded photos (base64 or paths)
            $savedPhotos = [];
            if (!empty($request->photos)) {
                if (!Storage::disk('public')->exists('stock_opname_photos')) {
                    Storage::disk('public')->makeDirectory('stock_opname_photos');
                }
                foreach ($request->photos as $pData) {
                    if (is_string($pData) && preg_match('/^data:image\/(\w+);base64,/', $pData, $type)) {
                        $data = substr($pData, strpos($pData, ',') + 1);
                        $type = strtolower($type[1]);
                        if (!in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
                            continue;
                        }
                        $data = base64_decode($data);
                        $fileName = 'so_' . date('Ymd_His') . '_' . uniqid() . '.' . $type;
                        Storage::disk('public')->put('stock_opname_photos/' . $fileName, $data);
                        $savedPhotos[] = 'storage/stock_opname_photos/' . $fileName;
                    } elseif (is_string($pData) && str_starts_with($pData, 'storage/')) {
                        $savedPhotos[] = $pData;
                    }
                }
            }

            // Calculate totals
            $totalSystem = 0;
            $totalPhysical = 0;
            $totalMissing = 0;

            $itemsData = $request->items;
            $method = $request->method;

            foreach ($itemsData as $item) {
                if ($method === 'by_imei') {
                    $totalSystem += 1;
                    $isPresent = (bool)($item['is_present'] ?? true);
                    if ($isPresent) {
                        $totalPhysical += 1;
                    } else {
                        $totalMissing += 1;
                    }
                } else {
                    $sysQty = (int)($item['system_qty'] ?? 0);
                    $physQty = (int)($item['physical_qty'] ?? 0);
                    $diff = $physQty - $sysQty;

                    $totalSystem += $sysQty;
                    $totalPhysical += $physQty;
                    if ($diff < 0) {
                        $totalMissing += abs($diff);
                    }
                }
            }

            $totalExcess = 0;
            $excessItems = $request->excess_items ?? [];
            foreach ($excessItems as $eItem) {
                $totalExcess += max(1, (int)($eItem['quantity'] ?? 1));
            }

            $hasDifference = ($totalMissing > 0 || $totalExcess > 0);
            $status = $hasDifference ? 'has_difference' : 'completed';

            // Generate unique opname number
            $recordDate = $cycle['date'];
            $todayStr = date('Ymd');
            $countToday = StockOpname::whereBetween('created_at', [$cycle['start'], $cycle['end']])->count() + 1;
            $opnameNumber = 'SO-' . $todayStr . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            $brandVal = $request->filled('brand') ? (string) $request->brand : null;
            $productTypeVal = $request->filled('product_type') ? (string) $request->product_type : null;

            // Create StockOpname record
            $recordData = [
                'opname_number' => $opnameNumber,
                'date' => $recordDate,
                'branch_id' => $request->branch_id,
                'user_id' => $user->id,
                'cs_user_id' => $csUser->id,
                'category_type' => $request->category_type,
                'method' => $request->method,
                'brand' => $brandVal,
                'product_type' => $productTypeVal,
                'total_system' => $totalSystem,
                'total_physical' => $totalPhysical,
                'total_missing' => $totalMissing,
                'total_excess' => $totalExcess,
                'photos' => $savedPhotos,
                'notes' => $request->notes,
                'status' => $status,
            ];
            if ($hasExecutorRole) {
                $recordData['executor_role'] = $userRoleScope;
            }
            $stockOpname = StockOpname::create($recordData);

            // Insert Items
            foreach ($itemsData as $item) {
                if ($method === 'by_imei') {
                    $isPresent = (bool)($item['is_present'] ?? true);
                    $stockOpname->items()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'product_detail_id' => $item['product_detail_id'] ?? null,
                        'brand' => isset($item['brand']) ? (string)$item['brand'] : $brandVal,
                        'product_name' => isset($item['product_name']) ? (string)$item['product_name'] : $productTypeVal,
                        'storage' => isset($item['storage']) ? (string)$item['storage'] : null,
                        'condition' => isset($item['condition']) ? (string)$item['condition'] : 'second',
                        'imei' => isset($item['imei']) ? (string)$item['imei'] : null,
                        'system_qty' => 1,
                        'physical_qty' => $isPresent ? 1 : 0,
                        'difference' => $isPresent ? 0 : -1,
                        'is_present' => $isPresent,
                        'notes' => isset($item['notes']) ? (string)$item['notes'] : null,
                    ]);
                } else {
                    $sysQty = (int)($item['system_qty'] ?? 0);
                    $physQty = (int)($item['physical_qty'] ?? 0);
                    $diff = $physQty - $sysQty;

                    $stockOpname->items()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'product_detail_id' => null,
                        'brand' => isset($item['brand']) ? (string)$item['brand'] : $brandVal,
                        'product_name' => isset($item['product_name']) ? (string)$item['product_name'] : '-',
                        'storage' => isset($item['storage']) ? (string)$item['storage'] : null,
                        'condition' => isset($item['condition']) ? (string)$item['condition'] : null,
                        'imei' => null,
                        'system_qty' => $sysQty,
                        'physical_qty' => $physQty,
                        'difference' => $diff,
                        'is_present' => ($diff >= 0),
                        'notes' => isset($item['notes']) ? (string)$item['notes'] : null,
                    ]);
                }
            }

            // Insert Excess Items (Barang Lebih)
            foreach ($excessItems as $eItem) {
                $ePhotos = [];
                if (!empty($eItem['photos']) && is_array($eItem['photos'])) {
                    foreach ($eItem['photos'] as $epData) {
                        if (is_string($epData) && preg_match('/^data:image\/(\w+);base64,/', $epData, $type)) {
                            $data = substr($epData, strpos($epData, ',') + 1);
                            $type = strtolower($type[1]);
                            if (in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
                                $data = base64_decode($data);
                                $fileName = 'excess_' . date('Ymd_His') . '_' . uniqid() . '.' . $type;
                                Storage::disk('public')->put('stock_opname_photos/' . $fileName, $data);
                                $ePhotos[] = 'storage/stock_opname_photos/' . $fileName;
                            }
                        }
                    }
                }

                $stockOpname->excessItems()->create([
                    'brand' => $eItem['brand'] ?? null,
                    'product_name' => $eItem['product_name'] ?? '-',
                    'storage' => $eItem['storage'] ?? null,
                    'imei' => $eItem['imei'] ?? null,
                    'quantity' => max(1, (int)($eItem['quantity'] ?? 1)),
                    'notes' => $eItem['notes'] ?? null,
                    'photos' => $ePhotos,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stok Opname berhasil disimpan.',
                'data' => $stockOpname->load(['branch', 'csUser', 'items', 'excessItems']),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Stock Opname Store Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan stok opname: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * History of Stock Opnames.
     */
    public function history(Request $request)
    {
        self::ensureTablesExist();

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['data' => []], 401);
        }

        if (!Schema::hasTable('stock_opnames')) {
            return response()->json([
                'current_page' => 1,
                'data' => [],
                'last_page' => 1,
                'total' => 0,
                'per_page' => 15,
            ]);
        }

        $query = StockOpname::with([
            'branch:id,name,code',
            'user:id,name,username',
            'csUser:id,name,username',
            'items' => fn($q) => $q->select('id', 'stock_opname_id', 'product_id', 'product_detail_id', 'brand', 'product_name', 'storage', 'condition', 'imei', 'system_qty', 'physical_qty', 'difference', 'is_present', 'notes')->orderBy('id', 'asc'),
            'excessItems' => fn($q) => $q->select('id', 'stock_opname_id', 'brand', 'product_name', 'storage', 'imei', 'quantity', 'notes')->orderBy('id', 'asc')
        ]);

        // Branch filtering based on user role and request
        $requestedBranchId = $request->query('branch_id');

        if ($requestedBranchId && $requestedBranchId !== 'all') {
            $query->where('branch_id', $requestedBranchId);
        } elseif (!$user->hasAnyRole(['super_admin', 'owner', 'admin_produk', 'analist'])) {
            $branchIds = (array) ($user->getAccessibleBranchIds() ?: []);
            if ($user->branch_id) $branchIds[] = $user->branch_id;
            $branchIds = array_unique(array_filter($branchIds));
            if (!empty($branchIds)) {
                $query->whereIn('branch_id', $branchIds);
            }
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('date') && $request->date !== 'all') {
            $dateVal = $request->date;
            $query->where(function ($q) use ($dateVal) {
                $q->whereDate('date', $dateVal)
                  ->orWhereDate('created_at', $dateVal);
            });
        }

        if ($request->filled('category_type') && $request->category_type !== 'all') {
            $query->where('category_type', $request->category_type);
        }

        if ($request->filled('method') && $request->method !== 'all') {
            $query->where('method', $request->method);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $isCsUser = method_exists($user, 'hasRole') && $user->hasRole('inventory');
        if ($isCsUser) {
            // Akun CS cuma bisa nampilin history SO dia sendiri aja
            $query->where(function ($q) use ($user) {
                $q->where('cs_user_id', $user->id)
                  ->orWhere('user_id', $user->id);
            });
        } else {
            // Akun toko, leader, dan audit bisa melihat semuanya (atau filter CS tertentu)
            if ($request->filled('cs_user_id') && $request->cs_user_id !== 'all') {
                $query->where('cs_user_id', $request->cs_user_id);
            }
        }

        // Executor role segregation & filtering
        $userRoleScope = self::getUserRoleScope($user);
        $requestedRole = $request->query('executor_role');
        $hasExecutorRole = Schema::hasColumn('stock_opnames', 'executor_role');

        if ($requestedRole && $requestedRole !== 'all') {
            if ($requestedRole === 'audit') {
                $query->where(function ($q) use ($hasExecutorRole) {
                    if ($hasExecutorRole) {
                        $q->where('executor_role', 'audit');
                    }
                    $q->orWhereHas('user', fn($uq) => $uq->whereHas('roles', fn($rq) => $rq->where('name', 'audit')));
                });
            } elseif ($requestedRole === 'leader') {
                $query->where(function ($q) use ($hasExecutorRole) {
                    if ($hasExecutorRole) {
                        $q->where('executor_role', 'leader');
                    }
                    $q->orWhereHas('user', fn($uq) => $uq->whereHas('roles', fn($rq) => $rq->where('name', 'leader')));
                });
            } elseif ($requestedRole === 'store') {
                $query->where(function ($q) use ($hasExecutorRole) {
                    if ($hasExecutorRole) {
                        $q->where('executor_role', 'store')
                          ->orWhereNull('executor_role')
                          ->orWhere(function ($subQ) {
                              $subQ->whereNotIn('executor_role', ['audit', 'leader', 'admin'])
                                   ->whereHas('user', function ($uq) {
                                       $uq->whereDoesntHave('roles', fn($rq) => $rq->whereIn('name', ['audit', 'leader', 'super_admin', 'owner', 'analist']));
                                   });
                          });
                    } else {
                        $q->whereHas('user', function ($uq) {
                            $uq->whereDoesntHave('roles', fn($rq) => $rq->whereIn('name', ['audit', 'leader', 'super_admin', 'owner', 'analist']));
                        });
                    }
                });
            } elseif ($requestedRole === 'admin') {
                if ($hasExecutorRole) {
                    $query->where('executor_role', 'admin');
                } else {
                    $query->whereHas('user', fn($uq) => $uq->whereHas('roles', fn($rq) => $rq->whereIn('name', ['super_admin', 'owner', 'analist'])));
                }
            }
        } elseif (!$requestedRole && $userRoleScope === 'store') {
            // Default store users to store SO only
            $query->where(function ($q) use ($hasExecutorRole) {
                if ($hasExecutorRole) {
                    $q->where('executor_role', 'store')
                      ->orWhereNull('executor_role')
                      ->orWhere(function ($subQ) {
                          $subQ->whereNotIn('executor_role', ['audit', 'leader', 'admin'])
                               ->whereHas('user', function ($uq) {
                                   $uq->whereDoesntHave('roles', fn($rq) => $rq->whereIn('name', ['audit', 'leader', 'super_admin', 'owner', 'analist']));
                               });
                      });
                } else {
                    $q->whereHas('user', function ($uq) {
                        $uq->whereDoesntHave('roles', fn($rq) => $rq->whereIn('name', ['audit', 'leader', 'super_admin', 'owner', 'analist']));
                    });
                }
            });
        }

        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('opname_number', 'ilike', "%{$term}%")
                    ->orWhere('brand', 'ilike', "%{$term}%")
                    ->orWhere('product_type', 'ilike', "%{$term}%")
                    ->orWhereHas('csUser', fn($uq) => $uq->where('name', 'ilike', "%{$term}%"))
                    ->orWhereHas('branch', fn($bq) => $bq->where('name', 'ilike', "%{$term}%"))
                    ->orWhereHas('items', fn($iq) => $iq->where('imei', 'like', "%{$term}%"));
            });
        }

        $perPage = min(max((int)$request->query('per_page', 15), 5), 100);
        $records = $query->latest('id')->paginate($perPage);

        return response()->json($records);
    }

    /**
     * Get detail of a specific stock opname.
     */
    public function show($id)
    {
        self::ensureTablesExist();

        $stockOpname = StockOpname::with([
            'branch:id,name,code',
            'user:id,name,username',
            'csUser:id,name,username',
            'items',
            'excessItems'
        ])->findOrFail($id);

        return response()->json(['data' => $stockOpname]);
    }
}
