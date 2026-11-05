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

        $role = strtolower($user->roles->first()?->name ?? '');

        // Unrestricted roles can access all branches
        if (in_array($role, ['super_admin', 'owner', 'admin_produk', 'analist'])) {
            $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
            return response()->json(['data' => $branches]);
        }

        // Leader or Audit: get assigned branches via placements or direct branch_id
        if (in_array($role, ['leader', 'audit'])) {
            $branchIds = (array) ($user->getAccessibleBranchIds() ?: []);
            if ($user->branch_id) {
                $branchIds[] = $user->branch_id;
            }
            $branchIds = array_unique(array_filter($branchIds));

            if (empty($branchIds)) {
                // If no specific branch restriction, allow active branches
                $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
            } else {
                $branches = Branch::whereIn('id', $branchIds)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
            }

            return response()->json(['data' => $branches]);
        }

        // Toko Offline / CS (Inventory): locked to user's branch
        $branchId = $user->branch_id;
        if (!$branchId) {
            $branchIds = (array) ($user->getAccessibleBranchIds() ?: []);
            $branchId = !empty($branchIds) ? $branchIds[0] : null;
        }

        if ($branchId) {
            $branches = Branch::where('id', $branchId)->get(['id', 'name', 'code']);
        } else {
            $branches = Branch::where('is_active', true)->orderBy('name')->take(1)->get(['id', 'name', 'code']);
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
            ->select('id', 'name', 'username', 'branch_id')
            ->orderBy('name')
            ->get();

        // If no dedicated CS found, also check users with role sales / toko_offline or general users for that branch
        if ($csUsers->isEmpty()) {
            $csUsers = User::where('branch_id', $branchId)
                ->where('is_active', true)
                ->select('id', 'name', 'username', 'branch_id')
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
            'brand' => 'nullable|string',
            'product_name' => 'nullable|string',
        ]);

        $branchId = (int) $request->branch_id;
        $categoryType = $request->query('category_type', 'hp');
        $method = $request->query('method', 'by_unit');
        $selectedBrand = $request->query('brand');
        $selectedProductName = $request->query('product_name');

        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $logicalToday = $logicalNow->toDateString();
        $calendarToday = now()->toDateString();
        $todayDates = array_unique([$logicalToday, $calendarToday]);

        // Check already audited items today at this branch (matching logical or calendar date)
        $auditedImeis = [];
        $auditedProductNamesByUnit = [];
        $auditedNonHpProductIds = [];

        if (Schema::hasTable('stock_opnames') && Schema::hasTable('stock_opname_items')) {
            $todayOpnameIds = StockOpname::where('branch_id', $branchId)
                ->where(function ($q) use ($todayDates) {
                    $q->whereIn('date', $todayDates)
                      ->orWhereBetween('created_at', [min($todayDates) . ' 00:00:00', max($todayDates) . ' 23:59:59']);
                })
                ->pluck('id');

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
                        $key = strtoupper(trim($ait->brand ?? '')) . '|' . strtoupper(trim($ait->product_name)) . '|' . strtoupper(trim($ait->storage ?? ''));
                        $auditedProductNamesByUnit[$key] = true;
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
                // Group by Brand -> Tipe + Storage (GB)
                $grouped = [];
                $pendingBrandsMap = [];

                foreach ($rawHp as $item) {
                    $bName = trim($item->product?->brand ?? 'Unknown');
                    if (!$bName || $bName === '-') $bName = 'Other';

                    $pName = trim($item->product?->name ?? 'Unknown');
                    $storage = trim($item->storage ?? '');
                    if (!$storage && preg_match('/(\d+\s*(?:GB|TB))/i', $pName, $m)) {
                        $storage = strtoupper($m[1]);
                    }

                    $key = strtoupper($bName) . '|' . strtoupper($pName) . '|' . strtoupper($storage);
                    if (isset($auditedProductNamesByUnit[$key])) {
                        // Skip if already audited by unit today
                        continue;
                    }

                    // Count towards pending brands
                    $pendingBrandsMap[$bName] = ($pendingBrandsMap[$bName] ?? 0) + 1;

                    if ($selectedBrand && strcasecmp($bName, $selectedBrand) !== 0) {
                        continue;
                    }

                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'key' => $key,
                            'product_id' => $item->product_id,
                            'brand' => $bName,
                            'product_name' => $pName,
                            'storage' => $storage ?: '-',
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

                // Sort descending by system_qty
                $resultList = array_values($grouped);
                usort($resultList, fn($a, $b) => $b['system_qty'] <=> $a['system_qty']);

                // Total available units is the count of units that are ACTUALLY pending
                $totalPendingUnits = array_sum(array_column($resultList, 'system_qty'));

                return response()->json([
                    'category_type' => 'hp',
                    'method' => 'by_unit',
                    'available_brands' => $availableBrands,
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
                foreach ($filteredHp as $item) {
                    $bName = trim($item->product?->brand ?? 'Unknown');
                    if (!$bName || $bName === '-') $bName = 'Other';
                    $brandsMap[$bName] = ($brandsMap[$bName] ?? 0) + 1;
                }
                arsort($brandsMap);
                $availableBrands = [];
                foreach ($brandsMap as $bName => $bCount) {
                    $availableBrands[] = [
                        'brand' => $bName,
                        'count' => $bCount,
                    ];
                }

                // Available types under selected brand
                $typesMap = [];
                foreach ($filteredHp as $item) {
                    $bName = trim($item->product?->brand ?? 'Unknown');
                    if (!$bName || $bName === '-') $bName = 'Other';

                    if ($selectedBrand && strcasecmp($bName, $selectedBrand) !== 0) {
                        continue;
                    }

                    $pName = trim($item->product?->name ?? 'Unknown');
                    $typesMap[$pName] = ($typesMap[$pName] ?? 0) + 1;
                }
                arsort($typesMap);
                $availableTypes = [];
                foreach ($typesMap as $pName => $pCount) {
                    $availableTypes[] = [
                        'product_name' => $pName,
                        'count' => $pCount,
                    ];
                }

                $imeiList = [];
                if ($selectedProductName) {
                    $selectedHpItems = $filteredHp->filter(function ($item) use ($selectedBrand, $selectedProductName) {
                        $bName = trim($item->product?->brand ?? 'Unknown');
                        if (!$bName || $bName === '-') $bName = 'Other';
                        if ($selectedBrand && strcasecmp($bName, $selectedBrand) !== 0) return false;

                        $pName = trim($item->product?->name ?? 'Unknown');
                        return strcasecmp($pName, $selectedProductName) === 0;
                    });

                    foreach ($selectedHpItems as $item) {
                        $imeiList[] = [
                            'product_detail_id' => $item->id,
                            'product_id' => $item->product_id,
                            'brand' => $item->product?->brand ?? '-',
                            'product_name' => $item->product?->name ?? '-',
                            'storage' => $item->storage ?? '-',
                            'condition' => $item->condition ?? 'second',
                            'imei' => $item->imei,
                            'distributor' => $item->distributor?->name ?? '-',
                            'status' => $item->status,
                            'is_present' => true,
                        ];
                    }
                }

                $totalPendingUnits = $selectedBrand
                    ? ($brandsMap[$selectedBrand] ?? 0)
                    : $filteredHp->count();

                return response()->json([
                    'category_type' => 'hp',
                    'method' => 'by_imei',
                    'available_brands' => $availableBrands,
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

                if ($selectedBrand && strcasecmp($bName, $selectedBrand) !== 0) {
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

            $totalPendingUnits = array_sum(array_column($resultList, 'system_qty'));

            return response()->json([
                'category_type' => 'non_hp',
                'method' => 'by_unit',
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
            'brand' => 'nullable|string',
            'product_type' => 'nullable|string',
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
            return response()->json(['message' => 'Akun CS tidak ditemukan.'], 404);
        }

        // Toko offline role or accessing as store account requires password verification
        $isStoreAccount = $user->hasRole('toko_offline') || ($user->id !== $csUser->id && !$user->hasAnyRole(['super_admin', 'leader', 'audit', 'analist']));
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

        try {
            DB::beginTransaction();

            // Save uploaded photos (base64 or paths)
            $savedPhotos = [];
            if (!empty($request->photos)) {
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
            $logicalNow = now()->hour < 5 ? now()->subDay() : now();
            $recordDate = $logicalNow->toDateString();
            $todayStr = date('Ymd');
            $countToday = StockOpname::where(function($q) use ($recordDate) {
                $q->whereDate('date', $recordDate)->orWhereDate('created_at', now()->toDateString());
            })->count() + 1;
            $opnameNumber = 'SO-' . $todayStr . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            // Create StockOpname record
            $stockOpname = StockOpname::create([
                'opname_number' => $opnameNumber,
                'date' => $recordDate,
                'branch_id' => $request->branch_id,
                'user_id' => $user->id,
                'cs_user_id' => $csUser->id,
                'category_type' => $request->category_type,
                'method' => $request->method,
                'brand' => $request->brand,
                'product_type' => $request->product_type,
                'total_system' => $totalSystem,
                'total_physical' => $totalPhysical,
                'total_missing' => $totalMissing,
                'total_excess' => $totalExcess,
                'photos' => $savedPhotos,
                'notes' => $request->notes,
                'status' => $status,
            ]);

            // Insert Items
            foreach ($itemsData as $item) {
                if ($method === 'by_imei') {
                    $isPresent = (bool)($item['is_present'] ?? true);
                    $stockOpname->items()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'product_detail_id' => $item['product_detail_id'] ?? null,
                        'brand' => $item['brand'] ?? $request->brand,
                        'product_name' => $item['product_name'] ?? $request->product_type,
                        'storage' => $item['storage'] ?? null,
                        'condition' => $item['condition'] ?? 'second',
                        'imei' => $item['imei'] ?? null,
                        'system_qty' => 1,
                        'physical_qty' => $isPresent ? 1 : 0,
                        'difference' => $isPresent ? 0 : -1,
                        'is_present' => $isPresent,
                        'notes' => $item['notes'] ?? null,
                    ]);
                } else {
                    $sysQty = (int)($item['system_qty'] ?? 0);
                    $physQty = (int)($item['physical_qty'] ?? 0);
                    $diff = $physQty - $sysQty;

                    $stockOpname->items()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'product_detail_id' => null,
                        'brand' => $item['brand'] ?? $request->brand,
                        'product_name' => $item['product_name'] ?? '-',
                        'storage' => $item['storage'] ?? null,
                        'condition' => null,
                        'imei' => null,
                        'system_qty' => $sysQty,
                        'physical_qty' => $physQty,
                        'difference' => $diff,
                        'is_present' => ($diff >= 0),
                        'notes' => $item['notes'] ?? null,
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
            'csUser:id,name,username'
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

        if ($request->filled('cs_user_id') && $request->cs_user_id !== 'all') {
            $query->where('cs_user_id', $request->cs_user_id);
        }

        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('opname_number', 'ilike', "%{$term}%")
                    ->orWhere('brand', 'ilike', "%{$term}%")
                    ->orWhere('product_type', 'ilike', "%{$term}%")
                    ->orWhereHas('csUser', fn($uq) => $uq->where('name', 'ilike', "%{$term}%"))
                    ->orWhereHas('branch', fn($bq) => $bq->where('name', 'ilike', "%{$term}%"));
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
