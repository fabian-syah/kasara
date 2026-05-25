<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\Distributor;
use App\Models\StockOut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Traits\VerifiesPin;

class InventoryController extends Controller
{
    use VerifiesPin;

    // List Inventory
    // List Inventory (Granular / Unit based)
    // Filtered by branch - only super_admin can see all
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $type = $request->type ?? 'hp';

        // 1. Accessibility
        $osIds = (array) ($user->getAccessibleOnlineShopIds() ?: []);
        $bIds = (array) ($user->getAccessibleBranchIds() ?: []);
        $wIds = (array) ($user->getAccessibleWarehouseIds() ?: []);

        if ($user->online_shop_id)
            $osIds[] = $user->online_shop_id;
        if ($user->branch_id)
            $bIds[] = $user->branch_id;
        if ($user->warehouse_id)
            $wIds[] = $user->warehouse_id;

        $osIds = array_unique(array_filter($osIds));
        $bIds = array_unique(array_filter($bIds));
        $wIds = array_unique(array_filter($wIds));
        $dIds = array_unique(array_filter((array) ($user->getAccessibleDistributorIds() ?: [])));

        $unrestricted = $user->hasRole(['super_admin', 'admin_produk', 'owner', 'analist']);

        // 2. Base Query
        if ($type === 'non-hp') {
            $query = Inventory::with(['product', 'user', 'user.distributor', 'distributor', 'latestLog', 'latestLog.distributor', 'placement'])
                ->select(
                    'product_id',
                    'placement_type',
                    'placement_id',
                    'user_id',
                    'notes',
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('MAX(id) as id'), 
                    DB::raw('MAX(distributor_id) as distributor_id'),
                    DB::raw('MAX(cost_price) as cost_price'), // Aggregated HPP
                    DB::raw('MAX(selling_price) as selling_price') // Per-branch selling price
                )
                ->where('quantity', '>', 0)
                ->whereHas('product', function ($q) {
                    $q->where('type', 'non-hp');
                })
                ->groupBy('product_id', 'placement_type', 'placement_id', 'user_id', 'notes');
        } else {
            $query = ProductDetail::with([
                'product',
                'distributor',
                'user',
                'refund',
                'refund.paymentMethod',
                'tradeIn',
                'tradeIn.paymentMethod',
                'placement',
                'stockOuts' => function ($q) {
                    $q->where('category', 'retur');
                }
            ])
                ->whereHas('product', function ($q) {
                    $q->where('type', 'hp')->orWhere('has_imei', true);
                });
        }

        // 3. Security Filter
        // Special case: When fetching retur/service items, warehouse users can see ALL returned items
        // (because returs originate from branches/shops but need to be accepted by warehouse)
        $isReturRequest = ($request->status === 'service') && !empty($wIds);

        if (!$unrestricted && !$isReturRequest) {
            $query->where(function ($q) use ($osIds, $bIds, $wIds, $dIds) {
                $hasConstraint = false;
                if (!empty($osIds)) {
                    $q->orWhere(fn($sq) => $sq->where('placement_type', 'online_shop')->whereIn('placement_id', $osIds));
                    $hasConstraint = true;
                }
                if (!empty($bIds)) {
                    $q->orWhere(fn($sq) => $sq->where('placement_type', 'branch')->whereIn('placement_id', $bIds));
                    $hasConstraint = true;
                }
                if (!empty($wIds)) {
                    $q->orWhere(fn($sq) => $sq->where('placement_type', 'warehouse')->whereIn('placement_id', $wIds));
                    $hasConstraint = true;
                }
                if (!empty($dIds)) {
                    $q->orWhere(fn($sq) => $sq->where('placement_type', 'distributor')->whereIn('placement_id', $dIds));
                    $hasConstraint = true;
                }
                if (!$hasConstraint)
                    $q->whereRaw('0 = 1');
            });
        }

        // 3.1 Analist Exclusion (Hide trial/testing branches)
        if ($user->hasRole('analist') && !$user->hasRole('super_admin')) {
            /** @var array $excludedKeywords */
            $excludedKeywords = config('kasara.excluded_keywords');
            $query->where(function ($q) use ($excludedKeywords) {
                foreach (['branch', 'online_shop', 'warehouse', 'distributor'] as $pType) {
                    $q->whereNot(function ($sq) use ($pType, $excludedKeywords) {
                        $sq->where('placement_type', $pType);
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

        // 4. Frontend Filters
        if ($request->filled('online_shop_id'))
            $query->where('placement_type', 'online_shop')->where('placement_id', $request->online_shop_id);
        if ($request->filled('branch_id'))
            $query->where('placement_type', 'branch')->where('placement_id', $request->branch_id);
        if ($request->filled('warehouse_id'))
            $query->where('placement_type', 'warehouse')->where('placement_id', $request->warehouse_id);

        if ($request->filled('placement_type')) {
            $query->where('placement_type', $request->placement_type);
        }

        if ($request->filled('distributor_id')) {
            $query->where('placement_type', 'distributor')->where('placement_id', $request->distributor_id);
        }

        if ($request->filled('brand')) {
            $brands = explode(',', $request->brand);
            $query->whereHas('product', fn($q) => $q->whereIn('brand', $brands));
        }

        if ($request->filled('product')) {
            $products = explode(',', $request->product);
            $query->whereHas('product', fn($q) => $q->whereIn('name', $products));
        }

        if ($type === 'hp') {
            if ($request->filled('capacity')) {
                $caps = explode(',', $request->capacity);
                $query->where(function ($q) use ($caps) {
                    foreach ($caps as $cap) {
                        $cap = trim($cap);
                        if (str_contains($cap, '/')) {
                            [$ram, $storage] = explode('/', $cap);
                            $q->orWhere(fn($sq) => $sq->where('ram', $ram)->where('storage', $storage));
                        } else {
                            $q->orWhere('storage', $cap);
                        }
                    }
                });
            }

            if ($request->filled('condition') && $request->condition !== 'all')
                $query->where('condition', $request->condition);

            $status = $request->status ?? $request->stock_status;
            if ($status && $status !== 'all') {
                $query->where('status', $status);
            } else {
                $query->whereIn('status', ['available', 'booking', 'returned', 'process']);
            }
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s, $type) {
                if ($type === 'hp') {
                    $q->where('imei', 'like', "%$s%");
                }

                $q->orWhereHas('product', function ($pq) use ($s) {
                    $pq->where('name', 'like', "%$s%")
                        ->orWhere('brand', 'like', "%$s%");

                    if (Schema::hasColumn('products', 'non_imei_category')) {
                        $pq->orWhere('non_imei_category', 'like', "%$s%");
                    } else {
                        $pq->orWhereExists(function ($eq) use ($s) {
                            $eq->select(DB::raw(1))
                                ->from('product_types')
                                ->whereColumn('product_types.name', 'products.name')
                                ->where('product_types.category', 'like', "%$s%");
                        });
                    }
                });
            });
        }

        // 5. Pagination & Response
        $perPage = $request->per_page ?? 20;
        if ($perPage == -1)
            $perPage = 999999;

        // REMOVED CACHE FOR REAL-TIME UPDATES
        $items = $query->latest('id')->paginate($perPage);

        $items->getCollection()->transform(function ($item) use ($type, $request) {
                $placement = $item->placement;
                $item->placement_name = $placement ? $placement->name : ($item->placement_type . ' #' . $item->placement_id);

                if ($type === 'non-hp') {
                    $item->quantity = $item->total_quantity ?? $item->quantity;

                    // Priority for distributor name:
                    // 1. Existing distributor relationship on the inventory record
                    // 2. Latest log distributor
                    // 3. User distributor fallback
                    $distName = null;
                    if ($item->distributor_id) {
                        $distName = $item->distributor?->name;
                    }

                    if (!$distName) {
                        // Use pre-loaded latestLog relationship instead of N+1 query
                        $lastInLog = $item->latestLog;
                        $distName = $lastInLog && $lastInLog->distributor ? $lastInLog->distributor->name : ($lastInLog->supplier_name ?? null);
                    }
                    
                    if (!$distName && $item->user && $item->user->distributor) {
                        $distName = $item->user->distributor->name;
                    }

                    $item->latest_distributor = $distName ?? '-';
                    $item->latest_supplier = $item->latestLog ? $item->latestLog->supplier_name : null;

                    // Set prices for Detail Modal â€” use per-branch selling_price from inventory if set, otherwise fall back to product master price
                    $item->selling_price = ($item->selling_price !== null && (float)$item->selling_price > 0) 
                        ? (float)$item->selling_price 
                        : ($item->product->price ?? ($item->product->selling_price ?? 0));
                    $item->price = $item->selling_price;
                }

                if ($request->status === 'service' && $type === 'hp') {
                    $returStockOut = $item->stockOuts->first();
                    if ($returStockOut) {
                        $item->retur_data = [
                            'receipt_id' => $returStockOut->receipt_id,
                            'customer_name' => $returStockOut->customer_name,
                            'customer_phone' => $returStockOut->customer_phone,
                            'retur_officer' => $returStockOut->retur_officer,
                            'retur_issue' => $returStockOut->retur_issue,
                            'retur_seal' => $returStockOut->retur_seal,
                            'proof_image' => $returStockOut->proof_image ? asset('storage/' . $returStockOut->proof_image) : null,
                            'selling_price' => $returStockOut->selling_price,
                            'notes' => $returStockOut->notes,
                            'created_at' => $returStockOut->created_at?->toDateTimeString(),
                        ];
                    }
                }
                return $item;
            });

            // If non-hp, we group by resolved name to avoid split rows (e.g. 14+1 Arcis)
            if ($type === 'non-hp') {
                $uniqueCollection = $items->getCollection()->groupBy(function ($item) {
                    // Group by product and placement AND distributor ID to prevent merging different distributor stock
                    return $item->product_id . '_' . $item->placement_type . '_' . $item->placement_id . '_' . ($item->distributor_id ?? '0') . '_' . $item->latest_distributor;
                })->map(function ($group) {
                    $first = $group->first();
                    $first->quantity = $group->sum('quantity');
                    return $first;
                })->values();
                $items->setCollection($uniqueCollection);
            }

            $res = $items->toArray();
            if ($type === 'hp') {
                $res['total_value'] = (clone $query)->sum('selling_price');
            } else {
                // For non-hp, calculate global total value using a subquery for price
                // to avoid join-related column ambiguity (Fixes Error 500)
                $totalValueQuery = Inventory::where('inventories.quantity', '>', 0);
                
                // Apply same filters as the main list
                $this->applyInventoryFilters($totalValueQuery, $request, 'non-hp');
                
                if ($request->filled('placement_type')) {
                    $totalValueQuery->where('inventories.placement_type', $request->placement_type);
                }
                
                $res['total_value'] = (float) $totalValueQuery->selectRaw('
                    SUM(
                        COALESCE(
                            NULLIF(inventories.selling_price, 0),
                            (SELECT price FROM products WHERE products.id = inventories.product_id LIMIT 1),
                            0
                        ) * inventories.quantity
                    ) as total
                ')->value('total') ?? 0;
            }

            return response()->json($res);
        }
    private function applyInventoryFilters($query, $request, $type)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($request->search) {
            $search = $request->search;
            if ($type === 'hp') {
                $query->where(function ($q) use ($search) {
                    $q->where('imei', 'like', "%{$search}%")->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%");
                    });
                });
            } else {
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
                });
            }
        }

        if ($request->branch_id) $query->where('placement_type', 'branch')->where('placement_id', $request->branch_id);
        if ($request->online_shop_id) $query->where('placement_type', 'online_shop')->where('placement_id', $request->online_shop_id);
        if ($request->warehouse_id) $query->where('placement_type', 'warehouse')->where('placement_id', $request->warehouse_id);
        
        if ($request->brand) {
            $brandArr = explode(',', $request->brand);
            $query->whereHas('product', fn($q) => $q->whereIn('brand', $brandArr));
        }
        
        if ($type === 'hp') {
            $status = $request->status ?? $request->stock_status;
            if ($status && $status !== 'all') $query->where('status', $status);
            else $query->whereIn('status', ['available', 'booking', 'returned', 'process']);
        } else {
            $query->where('quantity', '>', 0);
        }

        $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner', 'analist'];
        if (!$user->hasRole($unrestrictedRoles)) {
            $query->where(function ($q) use ($user) {
                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $shopIds = $user->getAccessibleOnlineShopIds();
                if (!empty($branchIds)) $q->orWhere(fn($sq) => $sq->where('placement_type', 'branch')->whereIn('placement_id', $branchIds));
                if (!empty($warehouseIds)) $q->orWhere(fn($sq) => $sq->where('placement_type', 'warehouse')->whereIn('placement_id', $warehouseIds));
                if (!empty($shopIds)) $q->orWhere(fn($sq) => $sq->where('placement_type', 'online_shop')->whereIn('placement_id', $shopIds));
            });
        }
    }

    // Stock history methods moved to App\Http\Controllers\Inventory\StockInController

    public function getProducts(Request $request)
    {
        $query = Product::query();
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->name) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        return response()->json($query->select('id', 'name', 'type', 'sku', 'brand', 'price')->limit(20)->get());
    }

    // Update item status (e.g., accept return: service -> available)
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:available,sold,returned,deleted,out',
            'inventory_user_id' => 'nullable|exists:users,id',
            'transaction_pin' => 'nullable|string|size:4',
        ]);

        /** @var \App\Models\User $targetUser */
        $targetUser = Auth::user();
        if ($request->has('inventory_user_id') && $request->inventory_user_id) {
            $targetUser = \App\Models\User::find($request->inventory_user_id);
        }

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError)
            return $pinError;

        $item = ProductDetail::findOrFail($id);
        $oldStatus = $item->status;

        $updateData = ['status' => $request->status];

        // If accepting a return and an inventory account is specified,
        // record who accepted it by updating user_id
        if ($request->status === 'available' && $request->inventory_user_id) {
            $updateData['user_id'] = $request->inventory_user_id;

            // Get the warehouse placement from the inventory user
            $invUser = \App\Models\User::find($request->inventory_user_id);
            if ($invUser && $invUser->warehouse_id) {
                $updateData['placement_type'] = 'warehouse';
                $updateData['placement_id'] = $invUser->warehouse_id;
            }
        }

        $item->update($updateData);

        // If this was a retur acceptance (service -> available),
        // mark the retur stock_out as confirmed so it shows as MASUK in Lacak Barang
        if ($oldStatus === 'service' && $request->status === 'available') {
            $returStockOut = $item->stockOuts()
                ->where('category', 'retur')
                ->whereNull('confirmed_at')
                ->latest()
                ->first();

            if ($returStockOut) {
                $returStockOut->update([
                    'status' => 'received',
                    'confirmed_at' => now(),
                    'confirmed_by' => $request->inventory_user_id ?? Auth::id(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diubah',
            'data' => $item
        ]);
    }

    public function rejectReturn(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
            'inventory_user_id' => 'nullable|exists:users,id',
            'transaction_pin' => 'nullable|string|size:4',
        ]);

        $pinError = $this->verifyPin($request);
        if ($pinError)
            return $pinError;

        return DB::transaction(function () use ($request, $id) {
            $item = ProductDetail::findOrFail($id);

            if ($item->status !== 'service') {
                return response()->json(['message' => 'Barang ini bukan retur yang menunggu diproses.'], 422);
            }

            $returStockOut = $item->stockOuts()
                ->where('category', 'retur')
                ->whereNull('confirmed_at')
                ->latest()
                ->first();

            if (!$returStockOut) {
                return response()->json(['message' => 'Data retur untuk barang ini tidak ditemukan.'], 422);
            }

            $sender = $returStockOut->user;
            $placementType = null;
            $placementId = null;

            if ($returStockOut->branch_id || $sender?->branch_id) {
                $placementType = 'branch';
                $placementId = $returStockOut->branch_id ?: $sender->branch_id;
            } elseif ($returStockOut->warehouse_id || $sender?->warehouse_id) {
                $placementType = 'warehouse';
                $placementId = $returStockOut->warehouse_id ?: $sender->warehouse_id;
            } elseif ($returStockOut->online_shop_id || $sender?->online_shop_id) {
                $placementType = 'online_shop';
                $placementId = $returStockOut->online_shop_id ?: $sender->online_shop_id;
            } elseif ($sender?->distributor_id) {
                $placementType = 'distributor';
                $placementId = $sender->distributor_id;
            }

            if (!$placementType || !$placementId) {
                return response()->json(['message' => 'Lokasi asal retur tidak bisa ditentukan.'], 422);
            }

            $processedBy = $request->inventory_user_id ?: Auth::id();

            $item->update([
                'status' => 'available',
                'placement_type' => $placementType,
                'placement_id' => $placementId,
                'user_id' => $sender?->id ?: $item->user_id,
            ]);

            $notes = trim((string) $returStockOut->notes);
            $reason = trim((string) $request->rejection_reason);
            $rejectNote = 'Retur ditolak oleh gudang' . ($reason ? ': ' . $reason : '');

            $returStockOut->update([
                'status' => 'rejected',
                'confirmed_at' => now(),
                'confirmed_by' => $processedBy,
                'notes' => $notes ? $notes . "\n" . $rejectNote : $rejectNote,
            ]);

            InventoryLog::create([
                'product_id' => $item->product_id,
                'type' => 'in',
                'quantity' => 1,
                'balance_after' => 1,
                'description' => 'RETUR DITOLAK - Kembali ke lokasi asal (' . ($item->imei ?? '-') . ')',
                'reference_id' => $returStockOut->receipt_id,
                'user_id' => $processedBy,
                'distributor_id' => $item->distributor_id,
                'branch_id' => $placementType === 'branch' ? $placementId : null,
                'warehouse_id' => $placementType === 'warehouse' ? $placementId : null,
                'online_shop_id' => $placementType === 'online_shop' ? $placementId : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Retur ditolak dan barang dikembalikan ke lokasi asal.',
                'data' => $item->fresh(),
            ]);
        });
    }

    // Update inventory item (HP or Non-HP)
    public function update(Request $request, $id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        // 1. Authorization
        $superRoles = ['super_admin', 'audit', 'owner', 'admin_produk'];
        $restrictedRoles = ['inventory', 'toko_offline', 'inventory_kasir', 'gudang', 'toko_online'];
        
        if (!$user->hasRole(array_merge($superRoles, $restrictedRoles))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $isRestricted = !$user->hasRole($superRoles);

        // 2. Identify Model (HP vs Non-HP)
        $item = ProductDetail::find($id);
        $type = 'hp';
        
        if (!$item) {
            $item = Inventory::find($id);
            $type = 'non-hp';
        }

        if (!$item) {
            return response()->json(['message' => 'Item tidak ditemukan'], 404);
        }

        // 3. Inventory Role Logic
        if ($isRestricted) {
            // Determine which account's PIN to verify
            $targetUser = $user;
            if ($request->inventory_user_id) {
                $targetUser = User::find($request->inventory_user_id);
                // Security: Ensure this inventory account belongs to the current user
                if (!$targetUser || ($targetUser->created_by !== $user->id && !$user->hasRole(['super_admin', 'owner']))) {
                    return response()->json(['message' => 'Akun Inventory tidak valid atau bukan milik Anda'], 403);
                }
            }

            // Must have PIN enabled and set
            if (!$targetUser->pin_enabled || !$targetUser->transaction_pin) {
                return response()->json(['message' => 'Akun ' . $targetUser->name . ' belum memasang/mengaktifkan PIN.'], 403);
            }

            // Verify PIN
            if (!$request->pin || !Hash::check($request->pin, $targetUser->transaction_pin)) {
                return response()->json(['message' => 'PIN Keamanan salah'], 422);
            }

            // Check if current price is 0
            $currentPrice = ($type === 'hp') 
                ? ($item->selling_price ?: 0) 
                : ($item->product->price ?? ($item->product->selling_price ?? 0));
            
            if ($currentPrice > 0) {
                return response()->json(['message' => 'Anda hanya diizinkan mengisi harga jual yang masih Rp 0'], 403);
            }
        }

        // 4. Validation
        $rules = [
            'notes' => 'nullable|string',
            'selling_price' => 'required|numeric|min:0',
        ];

        if (!$isRestricted) {
            if ($type === 'hp') {
                $rules['imei'] = 'required|string|unique:product_details,imei,' . $id;
                $rules['status'] = 'required|in:available,sold,retur,missing';
                $rules['cost_price'] = 'required|numeric';
            } else {
                $rules['cost_price'] = 'nullable|numeric';
            }
        }

        $request->validate($rules);

        // 5. Update Execution
        if ($type === 'hp') {
            $data = $request->only(['selling_price', 'notes']);
            if (!$isRestricted) {
                $data = array_merge($data, $request->only(['imei', 'storage', 'cost_price', 'status']));
            }
            $item->update($data);
        } else {
            // Non-HP: Update per-branch selling price on inventory record
            $updateData = ['notes' => $request->notes];
            if ($request->has('selling_price')) {
                $updateData['selling_price'] = $request->selling_price;
            }
            if ($request->has('cost_price')) {
                $updateData['cost_price'] = $request->cost_price;
            }
            $item->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Inventory berhasil diupdate',
            'data' => $item->load('product')
        ]);
    }

    // FIXER: Split merged IMEIs (Temporary Tool)
    public function fixMergedImeis()
    {
        \Illuminate\Support\Facades\Log::info("!!! PUBLIC DIAGNOSTIC HIT !!!", ['time' => now()->toDateTimeString()]);
        $details = ProductDetail::where(function ($q) {
            $q->where('imei', 'like', "%\n%")
                ->orWhere('imei', 'like', "% %")
                ->orWhere('imei', 'like', "%,%");
        })->get();

        $fixedCount = 0;
        $newRowsCount = 0;

        DB::beginTransaction();
        try {
            foreach ($details as $detail) {
                /** @var \App\Models\ProductDetail $detail */
                // Split by newline, comma, space
                $imeis = preg_split('/[\s,\n]+/', $detail->imei, -1, PREG_SPLIT_NO_EMPTY);
                $imeis = array_values(array_unique($imeis));

                if (count($imeis) > 1) {
                    // Valid details extracted
                    foreach ($imeis as $singleImei) {
                        // Check if this single IMEI already exists globally
                        $exists = ProductDetail::where('imei', $singleImei)->exists();

                        if (!$exists) {
                            // Create new row
                            $newDetail = $detail->replicate();
                            $newDetail->imei = $singleImei;
                            $logicalNow = now()->hour < 5 ? now()->subDay() : now();
                            $newDetail->created_at = $logicalNow;
                            $newDetail->updated_at = $logicalNow;
                            $newDetail->save();
                            $newRowsCount++;
                        }
                    }

                    // Delete the original corrupted row
                    $detail->forceDelete();
                    $fixedCount++;
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Fixer ANTIGRAVITY VERSION 1.0',
                'corrupted_rows_removed' => $fixedCount,
                'new_valid_rows_created' => $newRowsCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // getMyInventoryUsers moved to App\Http\Controllers\Inventory\InventoryAccountController

    public function getFilterOptions(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $type = $request->type ?? 'hp';

        $productNames = [];
        $capacities = [];

        // Common Restriction Logic (Same as Index)
        $applyLocationFilter = function ($query, $tablePrefix = '') use ($user) {
            $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner', 'analist'];
            if (!$user->hasRole($unrestrictedRoles)) {
                $query->where(function ($q) use ($user, $tablePrefix) {
                    $branchIds = $user->getAccessibleBranchIds();
                    $warehouseIds = $user->getAccessibleWarehouseIds();
                    $onlineShopIds = $user->getAccessibleOnlineShopIds();

                    $hasConstraint = false;
                    $colType = $tablePrefix ? $tablePrefix . '.placement_type' : 'placement_type';
                    $colId = $tablePrefix ? $tablePrefix . '.placement_id' : 'placement_id';

                    if (!empty($branchIds)) {
                        $q->orWhere(function ($sub) use ($colType, $colId, $branchIds) {
                            $sub->where($colType, 'branch')
                                ->whereIn($colId, $branchIds);
                        });
                        $hasConstraint = true;
                    }
                    if (!empty($warehouseIds)) {
                        $q->orWhere(function ($sub) use ($colType, $colId, $warehouseIds) {
                            $sub->where($colType, 'warehouse')
                                ->whereIn($colId, $warehouseIds);
                        });
                        $hasConstraint = true;
                    }
                    if (!empty($onlineShopIds)) {
                        $q->orWhere(function ($sub) use ($colType, $colId, $onlineShopIds) {
                            $sub->where($colType, 'online_shop')
                                ->whereIn($colId, $onlineShopIds);
                        });
                        $hasConstraint = true;
                    }

                    if (!$hasConstraint) {
                        $q->whereRaw('0 = 1');
                    }
                });
            }
        };

        if ($type === 'hp') {
            // HP: Query ProductDetail
            // Use distinct on product_id to get product names
            $query = ProductDetail::where('product_details.status', '!=', 'sold')
                ->join('products', 'product_details.product_id', '=', 'products.id')
                ->whereNull('products.deleted_at') // Disambiguate
                ->whereNull('product_details.deleted_at'); // Disambiguate


            $applyLocationFilter($query, 'product_details');

            $productNames = (clone $query)
                ->select('products.name')
                ->distinct()
                ->pluck('products.name')
                ->sort()
                ->values();

            // For capacities, we need RAM and Storage
            // We use select distinct to avoid fetching full objects
            $capacitiesRaw = (clone $query)
                ->select('product_details.ram', 'product_details.storage')
                ->distinct()
                ->get();

            $capacities = $capacitiesRaw->map(function ($item) {
                // Check for dummy RAM '1'
                $ram = ($item->ram == '1') ? null : $item->ram;

                if ($ram && $item->storage)
                    return "{$ram}/{$item->storage}";
                return $item->storage ?: $ram;
            })
                ->filter()
                ->unique()
                ->sort(function ($a, $b) {
                    return strnatcmp($a, $b);
                })
                ->values();

            // Brands
            $brands = (clone $query)
                ->select('products.brand')
                ->distinct()
                ->pluck('products.brand')
                ->filter()
                ->sort()
                ->values();

        } else {
            // Non-HP: Query Inventory
            $query = Inventory::where('quantity', '>', 0)
                ->join('products', 'inventories.product_id', '=', 'products.id');

            $applyLocationFilter($query, 'inventories');

            $productNames = (clone $query)
                ->select('products.name')
                ->distinct()
                ->pluck('products.name')
                ->sort()
                ->values();

            $brands = (clone $query)
                ->select('products.brand')
                ->distinct()
                ->pluck('products.brand')
                ->filter()
                ->sort()
                ->values();
        }

        return response()->json([
            'products' => $productNames,
            'capacities' => $capacities,
            'brands' => $brands
        ]);
    }

    public function getMetaLocations(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isAnalistOnly = $user->hasRole('analist') && !$user->hasRole('super_admin');
        /** @var array $excludedKeywords */
        $excludedKeywords = config('kasara.excluded_keywords');

        // 1. Accessibility Restrictions
        $osIds = (array) ($user->getAccessibleOnlineShopIds() ?: []);
        $bIds = (array) ($user->getAccessibleBranchIds() ?: []);
        $wIds = (array) ($user->getAccessibleWarehouseIds() ?: []);
        $dIds = (array) ($user->getAccessibleDistributorIds() ?: []);

        if ($user->online_shop_id)
            $osIds[] = $user->online_shop_id;
        if ($user->branch_id)
            $bIds[] = $user->branch_id;
        if ($user->warehouse_id)
            $wIds[] = $user->warehouse_id;

        $osIds = array_unique(array_filter($osIds));
        $bIds = array_unique(array_filter($bIds));
        $wIds = array_unique(array_filter($wIds));
        $dIds = array_unique(array_filter($dIds));

        $unrestricted = $user->hasRole(['super_admin', 'admin_produk', 'owner', 'analist']);

        $fetcher = function ($modelClass, $ids) use ($unrestricted, $isAnalistOnly, $excludedKeywords) {
            return $modelClass::where('is_active', true)
                ->when(!$unrestricted, fn($q) => $q->whereIn('id', $ids))
                ->when($isAnalistOnly, function ($q) use ($excludedKeywords) {
                    foreach ($excludedKeywords as $kw) {
                        $q->where('name', 'not ilike', "%$kw%");
                    }
                })
                ->get(['id', 'name']);
        };

        if (app()->bound(\Laravel\Octane\Contracts\DispatchesTasks::class)) {
            [$branches, $shops, $warehouses, $distributors] = \Laravel\Octane\Facades\Octane::concurrently([
                fn() => $fetcher(\App\Models\Branch::class, $bIds),
                fn() => $fetcher(\App\Models\OnlineShop::class, $osIds),
                fn() => $fetcher(\App\Models\Warehouse::class, $wIds),
                fn() => $fetcher(\App\Models\Distributor::class, $dIds),
            ]);
        } else {
            $branches = $fetcher(\App\Models\Branch::class, $bIds);
            $shops = $fetcher(\App\Models\OnlineShop::class, $osIds);
            $warehouses = $fetcher(\App\Models\Warehouse::class, $wIds);
            $distributors = $fetcher(\App\Models\Distributor::class, $dIds);
        }

        return response()->json([
            'branches' => $branches,
            'online_shops' => $shops,
            'warehouses' => $warehouses,
            'distributors' => $distributors
        ]);
    }

    /**
     * Monitoring Stok Online Shop
     * For leaders assigned to online shops â€” shows stock grouped by online shop.
     */
    public function monitoringOnlineShop(Request $request)
    {
        $user = $request->user();
        $userRole = strtolower($user->roles->first()->name ?? '');

        $accessibleIds = $user->getAccessibleOnlineShopIds();

        // Only leader/super_admin can access
        if ($userRole === 'leader') {
            if (empty($accessibleIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda belum dikaitkan dengan toko online manapun.'
                ], 403);
            }
        }

        // 1. HP Items (IMEI-based, available)
        $hpQuery = ProductDetail::with(['product'])
            ->where('status', 'available')
            ->where('placement_type', 'online_shop');

        if ($userRole === 'leader') {
            $hpQuery->whereIn('placement_id', $accessibleIds);
        } elseif ($userRole === 'super_admin' && $request->online_shop_id) {
            $hpQuery->where('placement_id', $request->online_shop_id);
        }

        $hpItems = $hpQuery->get();

        // 2. Non-HP Items (quantity-based, qty > 0)
        $nonHpQuery = Inventory::with(['product'])
            ->where('quantity', '>', 0)
            ->where('placement_type', 'online_shop')
            ->whereHas('product', function ($q) {
                $q->where('type', 'non-hp')->orWhere('has_imei', false);
            });

        if ($userRole === 'leader') {
            $nonHpQuery->whereIn('placement_id', $accessibleIds);
        } elseif ($userRole === 'super_admin' && $request->online_shop_id) {
            $nonHpQuery->where('placement_id', $request->online_shop_id);
        }

        $nonHpItems = $nonHpQuery->get();

        // Group by online shop
        $onlineShopNames = \App\Models\OnlineShop::pluck('name', 'id');
        $grouped = [];

        // Process HP
        foreach ($hpItems as $item) {
            $locationName = $onlineShopNames[$item->placement_id] ?? 'Unknown';

            if (!isset($grouped[$locationName])) {
                $grouped[$locationName] = ['location' => $locationName, 'products' => []];
            }

            $brandName = $item->product->brand ?? 'Unknown';
            $typeName = $item->product->name ?? 'Unknown';
            $spec = [];
            if ($item->ram)
                $spec[] = $item->ram;
            if ($item->storage)
                $spec[] = $item->storage;
            $specStr = !empty($spec) ? ' ' . implode('/', $spec) : '';
            $cond = ($item->condition === 'new') ? 'New' : (($item->condition === 'ex_ibox') ? 'Ex iBox' : 'Second');
            $productKey = trim("{$brandName} {$typeName}{$specStr} - {$cond}");

            if (!isset($grouped[$locationName]['products'][$productKey])) {
                $grouped[$locationName]['products'][$productKey] = [
                    'name' => $productKey,
                    'brand' => $brandName,
                    'type_name' => $typeName,
                    'capacity' => implode('/', $spec),
                    'condition_label' => $cond,
                    'qty' => 0,
                    'type' => $item->product->type ?? 'hp',
                    'has_imei' => $item->product->has_imei ?? true,
                    'items' => []
                ];
            }

            $grouped[$locationName]['products'][$productKey]['qty'] += 1;
            $grouped[$locationName]['products'][$productKey]['items'][] = [
                'id' => $item->id,
                'imei' => $item->imei,
                'color' => $item->color,
                'notes' => $item->notes,
                'condition' => $item->condition,
            ];
        }

        // Process Non-HP
        foreach ($nonHpItems as $item) {
            $locationName = $onlineShopNames[$item->placement_id] ?? 'Unknown';

            if (!isset($grouped[$locationName])) {
                $grouped[$locationName] = ['location' => $locationName, 'products' => []];
            }

            $brandName = $item->product->brand ?? 'Unknown';
            $typeName = $item->product->name ?? 'Unknown';
            $cond = 'New';
            $productKey = trim("{$brandName} {$typeName} - {$cond}");

            if (!isset($grouped[$locationName]['products'][$productKey])) {
                $grouped[$locationName]['products'][$productKey] = [
                    'name' => $productKey,
                    'brand' => $brandName,
                    'type_name' => $typeName,
                    'capacity' => null,
                    'condition_label' => $cond,
                    'qty' => 0,
                    'type' => $item->product->type ?? 'non-hp',
                    'has_imei' => false,
                    'items' => []
                ];
            }

            $grouped[$locationName]['products'][$productKey]['qty'] += $item->quantity;
        }

        // Sort and format
        $result = array_values($grouped);
        usort($result, fn($a, $b) => strcmp($a['location'], $b['location']));
        foreach ($result as &$loc) {
            $prodArr = array_values($loc['products']);
            usort($prodArr, fn($a, $b) => strcmp($a['name'], $b['name']));
            $loc['products'] = $prodArr;
        }

        $totalUnits = 0;
        foreach ($result as $loc) {
            foreach ($loc['products'] as $p) {
                $totalUnits += $p['qty'];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'stock' => $result,
                'total_units' => $totalUnits
            ]
        ]);
    }

    /**
     * Monitoring Stok Gudang (Warehouse)
     * For leaders assigned to warehouses â€” shows stock grouped by warehouse.
     */
    public function monitoringWarehouse(Request $request)
    {
        $user = $request->user();
        $userRole = strtolower($user->roles->first()->name ?? '');

        $accessibleIds = $user->getAccessibleWarehouseIds();

        if ($userRole === 'leader') {
            if (empty($accessibleIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda belum dikaitkan dengan gudang manapun.'
                ], 403);
            }
        }

        // 1. HP Items
        $hpQuery = ProductDetail::with(['product'])
            ->where('status', 'available')
            ->where('placement_type', 'warehouse');

        if ($userRole === 'leader') {
            $hpQuery->whereIn('placement_id', $accessibleIds);
        } elseif ($userRole === 'super_admin' && $request->warehouse_id) {
            $hpQuery->where('placement_id', $request->warehouse_id);
        }

        $hpItems = $hpQuery->get();

        // 2. Non-HP Items
        $nonHpQuery = Inventory::with(['product'])
            ->where('quantity', '>', 0)
            ->where('placement_type', 'warehouse')
            ->whereHas('product', function ($q) {
                $q->where('type', 'non-hp')->orWhere('has_imei', false);
            });

        if ($userRole === 'leader') {
            $nonHpQuery->whereIn('placement_id', $accessibleIds);
        } elseif ($userRole === 'super_admin' && $request->warehouse_id) {
            $nonHpQuery->where('placement_id', $request->warehouse_id);
        }

        $nonHpItems = $nonHpQuery->get();

        // Group by warehouse
        $warehouseNames = \App\Models\Warehouse::pluck('name', 'id');
        $grouped = [];

        // Process HP
        foreach ($hpItems as $item) {
            $locationName = $warehouseNames[$item->placement_id] ?? 'Unknown';

            if (!isset($grouped[$locationName])) {
                $grouped[$locationName] = ['location' => $locationName, 'products' => []];
            }

            $brandName = $item->product->brand ?? 'Unknown';
            $typeName = $item->product->name ?? 'Unknown';
            $spec = [];
            if ($item->ram)
                $spec[] = $item->ram;
            if ($item->storage)
                $spec[] = $item->storage;
            $specStr = !empty($spec) ? ' ' . implode('/', $spec) : '';
            $cond = ($item->condition === 'new') ? 'New' : (($item->condition === 'ex_ibox') ? 'Ex iBox' : 'Second');
            $productKey = trim("{$brandName} {$typeName}{$specStr} - {$cond}");

            if (!isset($grouped[$locationName]['products'][$productKey])) {
                $grouped[$locationName]['products'][$productKey] = [
                    'name' => $productKey,
                    'brand' => $brandName,
                    'type_name' => $typeName,
                    'capacity' => implode('/', $spec),
                    'condition_label' => $cond,
                    'qty' => 0,
                    'type' => $item->product->type ?? 'hp',
                    'has_imei' => $item->product->has_imei ?? true,
                    'items' => []
                ];
            }

            $grouped[$locationName]['products'][$productKey]['qty'] += 1;
            $grouped[$locationName]['products'][$productKey]['items'][] = [
                'id' => $item->id,
                'imei' => $item->imei,
                'color' => $item->color,
                'notes' => $item->notes,
                'condition' => $item->condition,
            ];
        }

        // Process Non-HP
        foreach ($nonHpItems as $item) {
            $locationName = $warehouseNames[$item->placement_id] ?? 'Unknown';

            if (!isset($grouped[$locationName])) {
                $grouped[$locationName] = ['location' => $locationName, 'products' => []];
            }

            $brandName = $item->product->brand ?? 'Unknown';
            $typeName = $item->product->name ?? 'Unknown';
            $cond = 'New';
            $productKey = trim("{$brandName} {$typeName} - {$cond}");

            if (!isset($grouped[$locationName]['products'][$productKey])) {
                $grouped[$locationName]['products'][$productKey] = [
                    'name' => $productKey,
                    'brand' => $brandName,
                    'type_name' => $typeName,
                    'capacity' => null,
                    'condition_label' => $cond,
                    'qty' => 0,
                    'type' => $item->product->type ?? 'non-hp',
                    'has_imei' => false,
                    'items' => []
                ];
            }

            $grouped[$locationName]['products'][$productKey]['qty'] += $item->quantity;
        }

        // Sort and format
        $result = array_values($grouped);
        usort($result, fn($a, $b) => strcmp($a['location'], $b['location']));
        foreach ($result as &$loc) {
            $prodArr = array_values($loc['products']);
            usort($prodArr, fn($a, $b) => strcmp($a['name'], $b['name']));
            $loc['products'] = $prodArr;
        }

        $totalUnits = 0;
        foreach ($result as $loc) {
            foreach ($loc['products'] as $p) {
                $totalUnits += $p['qty'];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'stock' => $result,
                'total_units' => $totalUnits
            ]
        ]);
    }

    public function stockSummary(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $osIds = (array) ($user->getAccessibleOnlineShopIds() ?: []);
        $bIds = (array) ($user->getAccessibleBranchIds() ?: []);
        $wIds = (array) ($user->getAccessibleWarehouseIds() ?: []);

        if ($user->online_shop_id)
            $osIds[] = $user->online_shop_id;
        if ($user->branch_id)
            $bIds[] = $user->branch_id;
        if ($user->warehouse_id)
            $wIds[] = $user->warehouse_id;

        $osIds = array_unique(array_filter($osIds));
        $bIds = array_unique(array_filter($bIds));
        $wIds = array_unique(array_filter($wIds));
        $dIds = array_unique(array_filter((array) ($user->getAccessibleDistributorIds() ?: [])));

        $unrestricted = $user->hasRole(['super_admin', 'admin_produk', 'owner', 'analist']);

        // Non-HP Query
        $nonHpQuery = Inventory::with('product')
            ->where('quantity', '>', 0)
            ->whereHas('product', fn($q) => $q->where('type', 'non-hp')->orWhere('has_imei', false));

        // HP Query
        $hpQuery = ProductDetail::with('product')
            ->whereIn('status', ['available', 'booking', 'returned', 'process'])
            ->whereHas('product', fn($q) => $q->where('type', 'hp')->orWhere('has_imei', true));

        // Apply security filter
        $applySecurity = function ($query) use ($unrestricted, $osIds, $bIds, $wIds, $dIds) {
            if (!$unrestricted) {
                $query->where(function ($q) use ($osIds, $bIds, $wIds, $dIds) {
                    $hasConstraint = false;
                    if (!empty($osIds)) {
                        $q->orWhere(fn($sq) => $sq->where('placement_type', 'online_shop')->whereIn('placement_id', $osIds));
                        $hasConstraint = true;
                    }
                    if (!empty($bIds)) {
                        $q->orWhere(fn($sq) => $sq->where('placement_type', 'branch')->whereIn('placement_id', $bIds));
                        $hasConstraint = true;
                    }
                    if (!empty($wIds)) {
                        $q->orWhere(fn($sq) => $sq->where('placement_type', 'warehouse')->whereIn('placement_id', $wIds));
                        $hasConstraint = true;
                    }
                    if (!empty($dIds)) {
                        $q->orWhere(fn($sq) => $sq->where('placement_type', 'distributor')->whereIn('placement_id', $dIds));
                        $hasConstraint = true;
                    }
                    if (!$hasConstraint)
                        $q->whereRaw('0 = 1');
                });
            }
        };

        $applySecurity($nonHpQuery);
        $applySecurity($hpQuery);

        $nonHpItems = $nonHpQuery->get();
        $hpItems = $hpQuery->get();

        $combined = [];
        $totalQty = 0;

        foreach ($nonHpItems as $item) {
            $product = $item->product;
            if (!$product)
                continue;

            $key = 'nonhp_' . $product->id;

            if (!isset($combined[$key])) {
                $combined[$key] = [
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'storage' => null,
                    'condition' => null,
                    'quantity' => 0,
                ];
            }
            $combined[$key]['quantity'] += $item->quantity;
            $totalQty += $item->quantity;
        }

        foreach ($hpItems as $item) {
            $product = $item->product;
            if (!$product)
                continue;

            $storageLabel = implode('/', array_filter([$item->ram, $item->storage]));
            if (empty($storageLabel))
                $storageLabel = null;

            $conditionLabel = $item->condition === 'new' ? 'Baru' : ($item->condition === 'second' ? 'Second' : ($item->condition === 'ex_ibox' ? 'Ex iBox' : $item->condition));

            $key = 'hp_' . $product->id . '_' . $storageLabel . '_' . $conditionLabel;

            if (!isset($combined[$key])) {
                $combined[$key] = [
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'storage' => $storageLabel,
                    'condition' => $conditionLabel,
                    'quantity' => 0,
                ];
            }
            $combined[$key]['quantity'] += 1;
            $totalQty += 1;
        }

        $combinedValues = array_values($combined);
        usort($combinedValues, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return response()->json([
            'success' => true,
            'stats' => [
                'totalItems' => count($combinedValues),
                'totalQuantity' => $totalQty
            ],
            'items' => $combinedValues
        ]);
    }

    public function pendingPhotos()
    {
        // Only return users who have pending_photo_inventory to avoid duplication with standard user photos
        $users = User::with(['roles', 'branch', 'warehouse', 'onlineShop'])
            ->whereNotNull('pending_photo_inventory')
            ->select('id', 'name', 'full_name', 'username', 'photo_inventory', 'pending_photo', 'pending_photo_inventory', 'branch_id', 'warehouse_id', 'online_shop_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function approvePhoto($id)
    {
        $user = User::findOrFail($id);

        // Pilih salah satu yang ada datanya
        $pendingPath = $user->pending_photo_inventory ?: $user->pending_photo;

        if (!$pendingPath) {
            return response()->json(['message' => 'Tidak ada foto yang menunggu persetujuan.'], 400);
        }

        // Hapus foto lama dari storage
        if ($user->photo_inventory && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->photo_inventory)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo_inventory);
        }
        if ($user->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->photo) && $user->photo !== $user->photo_inventory) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo);
        }

        // Pindahkan pending ke asli (Sync keduanya)
        $user->photo_inventory = $pendingPath;
        $user->photo = $pendingPath;

        // Kosongkan semua pending
        $user->pending_photo_inventory = null;
        $user->pending_photo = null;

        $user->save();

        return response()->json(['success' => true, 'message' => 'Foto berhasil disetujui.']);
    }

    public function rejectPhoto($id)
    {
        $user = User::findOrFail($id);
        if ($user->pending_photo_inventory) {
            // Hapus file pending dari storage
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($user->pending_photo_inventory)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->pending_photo_inventory);
            }
            $user->pending_photo_inventory = null;
            $user->save();
        }

        return response()->json(['success' => true, 'message' => 'Perubahan foto inventory ditolak.']);
    }

    /**
     * Stock Analysis - Analisa Stok
     * Returns available stock grouped by location with optional filters.
     * Access: super_admin, analist (all locations), audit (only assigned branches)
     */
    public function stockAnalysis(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Authorization check
        if (!$user->hasRole(['super_admin', 'analist', 'audit'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Base query: available product_details with product info
        $query = ProductDetail::query()
            ->where('product_details.status', 'available')
            ->join('products', 'products.id', '=', 'product_details.product_id')
            ->whereNull('products.deleted_at');

        // Optional filters
        if ($request->filled('brand')) {
            $query->where('products.brand', $request->brand);
        }

        if ($request->filled('product_type_id')) {
            $productType = \App\Models\ProductType::find($request->product_type_id);
            if ($productType) {
                $query->where('products.name', $productType->name);
            }
        }

        if ($request->filled('product_name')) {
            $query->where('products.name', $request->product_name);
        }

        if ($request->filled('storage')) {
            $query->where('product_details.storage', $request->storage);
        }

        if ($request->filled('condition')) {
            $query->where('product_details.condition', $request->condition);
        }

        // Role-based location filtering
        if ($user->hasRole(['super_admin', 'analist'])) {
            // See all locations (excluding test/trial branches for analist)
            if ($user->hasRole('analist') && !$user->hasRole('super_admin')) {
                $excludedKeywords = config('kasara.excluded_keywords', []);
                if (!empty($excludedKeywords)) {
                    $query->where(function ($q) use ($excludedKeywords) {
                        $q->where(function ($sq) use ($excludedKeywords) {
                            $sq->where('product_details.placement_type', '!=', 'branch')
                                ->orWhereNotIn('product_details.placement_id', function ($subq) use ($excludedKeywords) {
                                    $subq->select('id')->from('branches');
                                    foreach ($excludedKeywords as $kw) {
                                        $subq->where('name', 'ilike', "%$kw%");
                                    }
                                });
                        });
                    });
                }
            }
        } elseif ($user->hasRole('audit')) {
            // Audit: only see their accessible locations
            $branchIds = $user->getAccessibleBranchIds();
            $warehouseIds = $user->getAccessibleWarehouseIds();
            $onlineShopIds = $user->getAccessibleOnlineShopIds();

            $query->where(function ($q) use ($branchIds, $warehouseIds, $onlineShopIds) {
                $hasConstraint = false;
                if (!empty($branchIds)) {
                    $q->orWhere(fn($sq) => $sq->where('product_details.placement_type', 'branch')->whereIn('product_details.placement_id', $branchIds));
                    $hasConstraint = true;
                }
                if (!empty($warehouseIds)) {
                    $q->orWhere(fn($sq) => $sq->where('product_details.placement_type', 'warehouse')->whereIn('product_details.placement_id', $warehouseIds));
                    $hasConstraint = true;
                }
                if (!empty($onlineShopIds)) {
                    $q->orWhere(fn($sq) => $sq->where('product_details.placement_type', 'online_shop')->whereIn('product_details.placement_id', $onlineShopIds));
                    $hasConstraint = true;
                }
                if (!$hasConstraint) {
                    $q->whereRaw('0 = 1');
                }
            });
        }

        // Always group by all fields to show full breakdown
        $results = $query->select(
            'product_details.placement_type',
            'product_details.placement_id',
            'products.brand',
            'products.name as product_name',
            'product_details.storage',
            'product_details.condition',
            DB::raw('COUNT(*) as qty')
        )
            ->groupBy(
                'product_details.placement_type',
                'product_details.placement_id',
                'products.brand',
                'products.name',
                'product_details.storage',
                'product_details.condition'
            )
            ->orderByDesc('qty');

        // Get total for summary before pagination
        $allResults = (clone $query)->get();
        $totalQty = $allResults->sum('qty');
        $totalLocations = $allResults->unique(fn($r) => $r->placement_type . ':' . $r->placement_id)->count();

        // Paginate
        $perPage = (int) ($request->per_page ?? 20);
        $page = (int) ($request->page ?? 1);
        $total = $allResults->count();
        $paginatedResults = $allResults->slice(($page - 1) * $perPage, $perPage)->values();

        // Resolve location names
        $data = $paginatedResults->map(function ($row) {
            $locationName = $this->resolveLocationName($row->placement_type, $row->placement_id);
            return [
                'location_name' => $locationName,
                'location_type' => $row->placement_type,
                'qty' => (int) $row->qty,
                'brand' => $row->brand,
                'product_name' => $row->product_name,
                'storage' => $row->storage,
                'condition' => $row->condition,
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'summary' => [
                'total_qty' => $totalQty,
                'total_locations' => $totalLocations,
            ],
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    /**
     * Resolve a placement type + id to a human-readable name.
     */
    private function resolveLocationName(string $type, int $id): string
    {
        return match ($type) {
            'branch' => \App\Models\Branch::find($id)?->name ?? "Branch #$id",
            'warehouse' => \App\Models\Warehouse::find($id)?->name ?? "Warehouse #$id",
            'online_shop' => \App\Models\OnlineShop::find($id)?->name ?? "Online Shop #$id",
            'distributor' => \App\Models\Distributor::find($id)?->name ?? "Distributor #$id",
            default => "$type #$id",
        };
    }

    // destroyAccount and voidStockIn moved to their respective controllers
}

