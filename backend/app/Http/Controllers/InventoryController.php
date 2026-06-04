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

    /**
     * Get filter options for Stock Analysis that only show items WITH available stock.
     * Supports both HP (IMEI) and Non-HP (Non-IMEI) modes.
     */
    public function stockAnalysisFilters(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasRole(['super_admin', 'analist', 'audit'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mode = $request->input('mode', 'hp'); // 'hp' or 'non-hp'

        // Role-based location filter closure
        $applyRoleFilter = function ($query, $tablePrefix) use ($user) {
            if ($user->hasRole('audit') && !$user->hasRole(['super_admin', 'analist'])) {
                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $onlineShopIds = $user->getAccessibleOnlineShopIds();
                $query->where(function ($q) use ($branchIds, $warehouseIds, $onlineShopIds, $tablePrefix) {
                    $hasConstraint = false;
                    if (!empty($branchIds)) { $q->orWhere(fn($sq) => $sq->where("{$tablePrefix}.placement_type", 'branch')->whereIn("{$tablePrefix}.placement_id", $branchIds)); $hasConstraint = true; }
                    if (!empty($warehouseIds)) { $q->orWhere(fn($sq) => $sq->where("{$tablePrefix}.placement_type", 'warehouse')->whereIn("{$tablePrefix}.placement_id", $warehouseIds)); $hasConstraint = true; }
                    if (!empty($onlineShopIds)) { $q->orWhere(fn($sq) => $sq->where("{$tablePrefix}.placement_type", 'online_shop')->whereIn("{$tablePrefix}.placement_id", $onlineShopIds)); $hasConstraint = true; }
                    if (!$hasConstraint) $q->whereRaw('0 = 1');
                });
            }
        };

        if ($mode === 'non-hp') {
            // ===== NON-HP (Non-IMEI) MODE =====
            $baseQuery = Inventory::query()
                ->where('inventories.quantity', '>', 0)
                ->join('products', 'products.id', '=', 'inventories.product_id')
                ->whereNull('products.deleted_at');

            $applyRoleFilter($baseQuery, 'inventories');

            // Brands with stock
            $brands = (clone $baseQuery)
                ->select('products.brand', DB::raw('SUM(inventories.quantity) as qty'))
                ->groupBy('products.brand')
                ->orderBy('products.brand')
                ->get()
                ->where('qty', '>', 0)
                ->pluck('brand')
                ->filter()
                ->values();

            // Types (filtered by brand)
            $typesQuery = clone $baseQuery;
            if ($request->filled('brand')) {
                $typesQuery->where('products.brand', $request->brand);
            }
            $types = $typesQuery
                ->select('products.name', DB::raw('SUM(inventories.quantity) as qty'))
                ->groupBy('products.name')
                ->orderBy('products.name')
                ->get()
                ->where('qty', '>', 0)
                ->map(fn($row) => ['label' => $row->name, 'value' => $row->name, 'qty' => (int) $row->qty])
                ->values();

            // Total available
            $filteredQuery = clone $baseQuery;
            if ($request->filled('brand')) $filteredQuery->where('products.brand', $request->brand);
            if ($request->filled('product_name')) $filteredQuery->where('products.name', $request->product_name);
            $totalAvailable = (int) $filteredQuery->sum('inventories.quantity');

            return response()->json([
                'brands' => $brands,
                'types' => $types,
                'storages' => [],
                'conditions' => [],
                'total_available' => $totalAvailable,
            ]);
        }

        // ===== HP (IMEI) MODE =====
        $baseQuery = ProductDetail::query()
            ->where('product_details.status', 'available')
            ->join('products', 'products.id', '=', 'product_details.product_id')
            ->whereNull('products.deleted_at');

        $applyRoleFilter($baseQuery, 'product_details');

        // Apply cascading filters for total count
        $filteredQuery = clone $baseQuery;
        if ($request->filled('brand')) $filteredQuery->where('products.brand', $request->brand);
        if ($request->filled('product_name')) $filteredQuery->where('products.name', $request->product_name);
        if ($request->filled('storage')) $filteredQuery->where('product_details.storage', $request->storage);
        if ($request->filled('condition')) $filteredQuery->where('product_details.condition', $request->condition);

        // Brands
        $brands = (clone $baseQuery)
            ->select('products.brand', DB::raw('COUNT(*) as qty'))
            ->groupBy('products.brand')
            ->orderBy('products.brand')
            ->get()
            ->where('qty', '>', 0)
            ->pluck('brand')
            ->filter()
            ->values();

        // Types (filtered by brand)
        $typesQuery = clone $baseQuery;
        if ($request->filled('brand')) $typesQuery->where('products.brand', $request->brand);
        $types = $typesQuery
            ->select('products.name', DB::raw('COUNT(*) as qty'))
            ->groupBy('products.name')
            ->orderBy('products.name')
            ->get()
            ->where('qty', '>', 0)
            ->map(fn($row) => ['label' => $row->name, 'value' => $row->name, 'qty' => $row->qty])
            ->values();

        // Storages (filtered by brand + type)
        $storageQuery = clone $baseQuery;
        if ($request->filled('brand')) $storageQuery->where('products.brand', $request->brand);
        if ($request->filled('product_name')) $storageQuery->where('products.name', $request->product_name);
        $storages = $storageQuery
            ->select('product_details.storage', DB::raw('COUNT(*) as qty'))
            ->whereNotNull('product_details.storage')
            ->where('product_details.storage', '!=', '')
            ->groupBy('product_details.storage')
            ->orderBy('product_details.storage')
            ->get()
            ->where('qty', '>', 0)
            ->map(fn($row) => ['label' => $row->storage, 'value' => $row->storage, 'qty' => $row->qty])
            ->values();

        // Conditions (filtered by brand + type + storage)
        $conditionQuery = clone $baseQuery;
        if ($request->filled('brand')) $conditionQuery->where('products.brand', $request->brand);
        if ($request->filled('product_name')) $conditionQuery->where('products.name', $request->product_name);
        if ($request->filled('storage')) $conditionQuery->where('product_details.storage', $request->storage);
        $conditionLabels = ['new' => 'Baru (New)', 'second' => 'Second', 'ex_ibox' => 'Ex-iBox', 'ex_inter' => 'Ex-Inter', 'refurbished' => 'Refurbished'];
        $conditions = $conditionQuery
            ->select('product_details.condition', DB::raw('COUNT(*) as qty'))
            ->whereNotNull('product_details.condition')
            ->groupBy('product_details.condition')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get()
            ->where('qty', '>', 0)
            ->map(fn($row) => [
                'label' => ($conditionLabels[$row->condition] ?? ucfirst($row->condition)) . " ({$row->qty})",
                'value' => $row->condition,
                'qty' => $row->qty,
            ])
            ->values();

        $totalAvailable = $filteredQuery->count();

        return response()->json([
            'brands' => $brands,
            'types' => $types,
            'storages' => $storages,
            'conditions' => $conditions,
            'total_available' => $totalAvailable,
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

    /**
     * Bulk fetch ALL inventory for Stock Opname (single request, no pagination)
     * Returns both HP and Non-HP items in one response
     */
    public function opnameBulk(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $osIds = (array) ($user->getAccessibleOnlineShopIds() ?: []);
        $bIds = (array) ($user->getAccessibleBranchIds() ?: []);
        $wIds = (array) ($user->getAccessibleWarehouseIds() ?: []);

        if ($user->online_shop_id) $osIds[] = $user->online_shop_id;
        if ($user->branch_id) $bIds[] = $user->branch_id;
        if ($user->warehouse_id) $wIds[] = $user->warehouse_id;

        $osIds = array_unique(array_filter($osIds));
        $bIds = array_unique(array_filter($bIds));
        $wIds = array_unique(array_filter($wIds));
        $dIds = array_unique(array_filter((array) ($user->getAccessibleDistributorIds() ?: [])));

        $unrestricted = $user->hasRole(['super_admin', 'admin_produk', 'owner', 'analist']);

        // Location filter from request
        $filterBranch = $request->branch_id;
        $filterShop = $request->online_shop_id;
        $filterWarehouse = $request->warehouse_id;
        $filterDistributor = $request->distributor_id;

        $applySecurity = function ($query) use ($unrestricted, $osIds, $bIds, $wIds, $dIds, $filterBranch, $filterShop, $filterWarehouse, $filterDistributor) {
            // Apply specific location filter first
            if ($filterBranch) {
                $query->where('placement_type', 'branch')->where('placement_id', $filterBranch);
            } elseif ($filterShop) {
                $query->where('placement_type', 'online_shop')->where('placement_id', $filterShop);
            } elseif ($filterWarehouse) {
                $query->where('placement_type', 'warehouse')->where('placement_id', $filterWarehouse);
            } elseif ($filterDistributor) {
                $query->where('placement_type', 'distributor')->where('placement_id', $filterDistributor);
            } elseif (!$unrestricted) {
                $query->where(function ($q) use ($osIds, $bIds, $wIds, $dIds) {
                    $hasConstraint = false;
                    if (!empty($osIds)) { $q->orWhere(fn($sq) => $sq->where('placement_type', 'online_shop')->whereIn('placement_id', $osIds)); $hasConstraint = true; }
                    if (!empty($bIds)) { $q->orWhere(fn($sq) => $sq->where('placement_type', 'branch')->whereIn('placement_id', $bIds)); $hasConstraint = true; }
                    if (!empty($wIds)) { $q->orWhere(fn($sq) => $sq->where('placement_type', 'warehouse')->whereIn('placement_id', $wIds)); $hasConstraint = true; }
                    if (!empty($dIds)) { $q->orWhere(fn($sq) => $sq->where('placement_type', 'distributor')->whereIn('placement_id', $dIds)); $hasConstraint = true; }
                    if (!$hasConstraint) $q->whereRaw('0 = 1');
                });
            }
        };

        // HP items (only select needed columns for speed)
        $hpQuery = ProductDetail::with(['product:id,name,brand,type,category', 'placement:id,name', 'distributor:id,name'])
            ->select('id', 'product_id', 'placement_type', 'placement_id', 'status', 'condition', 'ram', 'storage', 'distributor_id', 'imei')
            ->whereIn('status', ['available', 'booking', 'returned', 'process'])
            ->whereHas('product', fn($q) => $q->where('type', 'hp')->orWhere('has_imei', true));

        $applySecurity($hpQuery);

        // Non-HP items
        $nonHpQuery = Inventory::with(['product:id,name,brand,type,category', 'placement:id,name', 'distributor:id,name'])
            ->select('id', 'product_id', 'placement_type', 'placement_id', 'quantity', 'distributor_id', 'notes')
            ->where('quantity', '>', 0)
            ->whereHas('product', fn($q) => $q->where('type', 'non-hp')->orWhere('has_imei', false));

        $applySecurity($nonHpQuery);

        // Execute both queries
        $hpItems = $hpQuery->get()->map(fn($item) => [
            'id' => $item->id,
            'product' => $item->product ? ['name' => $item->product->name, 'brand' => $item->product->brand, 'category' => $item->product->category] : null,
            'placement_name' => $item->placement->name ?? null,
            'placement_type' => $item->placement_type,
            'status' => $item->status,
            'condition' => $item->condition,
            'ram' => $item->ram,
            'storage' => $item->storage,
            'imei' => $item->imei,
            'distributor' => $item->distributor ? ['name' => $item->distributor->name] : null,
        ]);

        $nonHpItems = $nonHpQuery->get()->map(fn($item) => [
            'id' => $item->id,
            'product' => $item->product ? ['name' => $item->product->name, 'brand' => $item->product->brand, 'category' => $item->product->category] : null,
            'placement_name' => $item->placement->name ?? null,
            'placement_type' => $item->placement_type,
            'quantity' => $item->quantity,
            'balance' => $item->quantity,
            'distributor' => $item->distributor ? ['name' => $item->distributor->name] : null,
        ]);

        return response()->json([
            'hp' => $hpItems,
            'non_hp' => $nonHpItems,
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

        // Dynamic grouping based on active filters
        $hasTypeFilter = $request->filled('product_type_id') || $request->filled('product_name');
        $hasStorageFilter = $request->filled('storage');
        $hasConditionFilter = $request->filled('condition');

        $selectFields = [
            'product_details.placement_type',
            'product_details.placement_id',
            'products.brand',
            DB::raw('COUNT(*) as qty')
        ];
        $groupByFields = [
            'product_details.placement_type',
            'product_details.placement_id',
            'products.brand',
        ];

        if ($hasTypeFilter) {
            $selectFields[] = 'products.name as product_name';
            $groupByFields[] = 'products.name';
        }

        if ($hasStorageFilter) {
            $selectFields[] = 'product_details.storage';
            $groupByFields[] = 'product_details.storage';
        }

        if ($hasConditionFilter) {
            $selectFields[] = 'product_details.condition';
            $groupByFields[] = 'product_details.condition';
        }

        $results = $query->select($selectFields)
            ->groupBy($groupByFields)
            ->orderByDesc('qty');

        // Also query non-HP items from inventories table
        $nonHpQuery = \App\Models\Inventory::query()
            ->where('inventories.quantity', '>', 0)
            ->join('products', 'products.id', '=', 'inventories.product_id');

        // Apply same filters to non-HP
        if ($request->filled('brand')) {
            $nonHpQuery->where('products.brand', $request->brand);
        }
        if ($request->filled('product_type_id')) {
            $productType = \App\Models\ProductType::find($request->product_type_id);
            if ($productType) {
                $nonHpQuery->where('products.name', $productType->name);
            }
        }
        if ($request->filled('product_name')) {
            $nonHpQuery->where('products.name', $request->product_name);
        }
        // Storage and condition filters don't apply to non-HP (they don't have these fields)
        // Skip non-HP results if storage or condition filter is active
        $skipNonHp = $request->filled('storage') || $request->filled('condition');

        // Apply role-based filtering to non-HP
        if ($user->hasRole(['super_admin', 'analist'])) {
            // no restriction
        } elseif ($user->hasRole('audit')) {
            $branchIds = $user->getAccessibleBranchIds();
            $warehouseIds = $user->getAccessibleWarehouseIds();
            $onlineShopIds = $user->getAccessibleOnlineShopIds();
            $nonHpQuery->where(function ($q) use ($branchIds, $warehouseIds, $onlineShopIds) {
                $hasConstraint = false;
                if (!empty($branchIds)) { $q->orWhere(fn($sq) => $sq->where('inventories.placement_type', 'branch')->whereIn('inventories.placement_id', $branchIds)); $hasConstraint = true; }
                if (!empty($warehouseIds)) { $q->orWhere(fn($sq) => $sq->where('inventories.placement_type', 'warehouse')->whereIn('inventories.placement_id', $warehouseIds)); $hasConstraint = true; }
                if (!empty($onlineShopIds)) { $q->orWhere(fn($sq) => $sq->where('inventories.placement_type', 'online_shop')->whereIn('inventories.placement_id', $onlineShopIds)); $hasConstraint = true; }
                if (!$hasConstraint) { $q->whereRaw('0 = 1'); }
            });
        }

        $nonHpResults = collect();
        if (!$skipNonHp) {
            $nonHpResults = $nonHpQuery->select(
                'inventories.placement_type',
                'inventories.placement_id',
                'products.brand',
                'products.name as product_name',
                DB::raw('SUM(inventories.quantity) as qty')
            )
                ->groupBy('inventories.placement_type', 'inventories.placement_id', 'products.brand', 'products.name')
                ->orderByDesc('qty')
                ->get()
                ->map(function ($row) {
                    $row->storage = null;
                    $row->condition = null;
                    return $row;
                });
        }

        // Merge HP + Non-HP results then aggregate duplicates
        $mergedResults = (clone $query)->get()->concat($nonHpResults);
        
        // Aggregate by the same grouping keys to prevent duplicate locations
        $groupKey = function ($row) use ($hasTypeFilter, $hasStorageFilter, $hasConditionFilter) {
            $key = $row->placement_type . ':' . $row->placement_id . ':' . $row->brand;
            if ($hasTypeFilter && isset($row->product_name)) $key .= ':' . $row->product_name;
            if ($hasStorageFilter && isset($row->storage)) $key .= ':' . $row->storage;
            if ($hasConditionFilter && isset($row->condition)) $key .= ':' . $row->condition;
            return $key;
        };

        $allResults = $mergedResults->groupBy($groupKey)->map(function ($group) {
            $first = $group->first();
            $first->qty = $group->sum('qty');
            return $first;
        })->sortByDesc('qty')->values();
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
                'placement_id' => $row->placement_id,
                'qty' => (int) $row->qty,
                'brand' => $row->brand,
                'product_name' => $row->product_name ?? null,
                'storage' => $row->storage ?? null,
                'condition' => $row->condition ?? null,
                'otw_qty' => 0,
            ];
        });

        // Calculate OTW (pending incoming transfers) per location
        $locationIds = $data->pluck('placement_id')->unique()->values();
        if ($locationIds->isNotEmpty()) {
            $otwCounts = \App\Models\StockOut::where('category', 'pindah_cabang')
                ->where('status', 'pending')
                ->whereIn('destination_id', $locationIds)
                ->where('destination_type', 'branch')
                ->select('destination_id', DB::raw('COUNT(DISTINCT id) as transfer_count'))
                ->groupBy('destination_id')
                ->pluck('transfer_count', 'destination_id');

            // Count actual items in transit to each location
            $otwItemCounts = \App\Models\ProductDetail::where('status', 'in_transit')
                ->where('placement_type', 'branch')
                ->whereIn('placement_id', $locationIds);

            // Apply same product filters to OTW count
            if ($request->filled('brand')) {
                $otwItemCounts->whereHas('product', fn($q) => $q->where('brand', $request->brand));
            }
            if ($request->filled('product_type_id')) {
                $productType = \App\Models\ProductType::find($request->product_type_id);
                if ($productType) {
                    $otwItemCounts->whereHas('product', fn($q) => $q->where('name', $productType->name));
                }
            }
            if ($request->filled('storage')) {
                $otwItemCounts->where('storage', $request->storage);
            }
            if ($request->filled('condition')) {
                $otwItemCounts->where('condition', $request->condition);
            }

            $otwPerLocation = $otwItemCounts
                ->select('placement_id', DB::raw('COUNT(*) as qty'))
                ->groupBy('placement_id')
                ->pluck('qty', 'placement_id');

            $data = $data->map(function ($item) use ($otwPerLocation) {
                $item['otw_qty'] = (int) ($otwPerLocation[$item['placement_id']] ?? 0);
                return $item;
            });
        }

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

    public function soldAnalysisFilters(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasRole(['super_admin', 'analist', 'audit'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mode = $request->input('mode', 'hp'); // 'hp' or 'non-hp'
        $timeFilter = $request->input('time_filter');
        $month = $request->input('month');
        $year = $request->input('year');

        $baseQuery = StockOut::query()->where('stock_outs.status', '!=', 'cancelled');

        // Apply Time Filter
        if (!empty($month) && !empty($year)) {
            $baseQuery->whereMonth('stock_outs.reporting_date', $month)
                      ->whereYear('stock_outs.reporting_date', $year);
        } elseif (!empty($year)) {
            $baseQuery->whereYear('stock_outs.reporting_date', $year);
        } elseif ($timeFilter === 'bulan_ini') {
            $baseQuery->whereMonth('stock_outs.reporting_date', now()->month)
                      ->whereYear('stock_outs.reporting_date', now()->year);
        } elseif ($timeFilter === 'tahun_ini') {
            $baseQuery->whereYear('stock_outs.reporting_date', now()->year);
        }

        // Apply Role Filter for location
        if ($user->hasRole('audit') && !$user->hasRole(['super_admin', 'analist'])) {
            $branchIds = $user->getAccessibleBranchIds();
            $warehouseIds = $user->getAccessibleWarehouseIds();
            $onlineShopIds = $user->getAccessibleOnlineShopIds();
            
            $baseQuery->leftJoin('users', 'stock_outs.user_id', '=', 'users.id')
                      ->where(function ($q) use ($branchIds, $warehouseIds, $onlineShopIds) {
                $hasConstraint = false;
                if (!empty($branchIds)) {
                    $q->orWhereIn('users.branch_id', $branchIds);
                    $hasConstraint = true;
                }
                if (!empty($warehouseIds)) {
                    $q->orWhereIn('users.warehouse_id', $warehouseIds);
                    $hasConstraint = true;
                }
                if (!empty($onlineShopIds)) {
                    $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                    $hasConstraint = true;
                }
                if (!$hasConstraint) {
                    $q->whereRaw('0 = 1');
                }
            });
        }

        if ($mode === 'non-hp') {
            $baseQuery->join('stock_out_non_hp_items', 'stock_outs.id', '=', 'stock_out_non_hp_items.stock_out_id')
                      ->join('products', 'products.id', '=', 'stock_out_non_hp_items.product_id')
                      ->whereNull('products.deleted_at')
                      ->where(function($q) {
                          $q->where('products.type', 'non-hp')->orWhere('products.has_imei', false);
                      });

            $filteredQuery = clone $baseQuery;
            if ($request->filled('brand')) $filteredQuery->where('products.brand', $request->brand);
            if ($request->filled('product_name')) $filteredQuery->where('products.name', $request->product_name);

            $brands = (clone $baseQuery)->select('products.brand', DB::raw('SUM(stock_out_non_hp_items.quantity) as qty'))
                ->groupBy('products.brand')->orderBy('products.brand')->get()
                ->where('qty', '>', 0)->pluck('brand')->filter()->values();

            $typesQuery = clone $baseQuery;
            if ($request->filled('brand')) $typesQuery->where('products.brand', $request->brand);
            $types = $typesQuery->select('products.name', DB::raw('SUM(stock_out_non_hp_items.quantity) as qty'))
                ->groupBy('products.name')->orderBy('products.name')->get()
                ->where('qty', '>', 0)->map(fn($row) => ['label' => $row->name, 'value' => $row->name, 'qty' => (int) $row->qty])->values();

            $totalAvailable = (int) $filteredQuery->sum('stock_out_non_hp_items.quantity');

            return response()->json([
                'brands' => $brands,
                'types' => $types,
                'storages' => [],
                'conditions' => [],
                'total_available' => $totalAvailable,
            ]);
        }

        // HP Mode
        $baseQuery->join('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
                  ->join('product_details', 'product_details.id', '=', 'stock_out_items.product_detail_id')
                  ->join('products', 'products.id', '=', 'product_details.product_id')
                  ->whereNull('products.deleted_at')
                  ->whereNull('product_details.deleted_at')
                  ->where(function($q) {
                      $q->where('products.type', 'hp')->orWhere('products.has_imei', true);
                  });

        $filteredQuery = clone $baseQuery;
        if ($request->filled('brand')) $filteredQuery->where('products.brand', $request->brand);
        if ($request->filled('product_name')) $filteredQuery->where('products.name', $request->product_name);
        if ($request->filled('storage')) $filteredQuery->where('product_details.storage', $request->storage);
        if ($request->filled('condition')) $filteredQuery->where('product_details.condition', $request->condition);

        $brands = (clone $baseQuery)->select('products.brand', DB::raw('COUNT(*) as qty'))
            ->groupBy('products.brand')->orderBy('products.brand')->get()
            ->where('qty', '>', 0)->pluck('brand')->filter()->values();

        $typesQuery = clone $baseQuery;
        if ($request->filled('brand')) $typesQuery->where('products.brand', $request->brand);
        $types = $typesQuery->select('products.name', DB::raw('COUNT(*) as qty'))
            ->groupBy('products.name')->orderBy('products.name')->get()
            ->where('qty', '>', 0)->map(fn($row) => ['label' => $row->name, 'value' => $row->name, 'qty' => $row->qty])->values();

        $storageQuery = clone $baseQuery;
        if ($request->filled('brand')) $storageQuery->where('products.brand', $request->brand);
        if ($request->filled('product_name')) $storageQuery->where('products.name', $request->product_name);
        $storages = $storageQuery->select('product_details.storage', DB::raw('COUNT(*) as qty'))
            ->whereNotNull('product_details.storage')->where('product_details.storage', '!=', '')
            ->groupBy('product_details.storage')->orderBy('product_details.storage')->get()
            ->where('qty', '>', 0)->map(fn($row) => ['label' => $row->storage, 'value' => $row->storage, 'qty' => $row->qty])->values();

        $conditionQuery = clone $baseQuery;
        if ($request->filled('brand')) $conditionQuery->where('products.brand', $request->brand);
        if ($request->filled('product_name')) $conditionQuery->where('products.name', $request->product_name);
        if ($request->filled('storage')) $conditionQuery->where('product_details.storage', $request->storage);
        
        $conditionLabels = ['new' => 'Baru (New)', 'second' => 'Second', 'ex_ibox' => 'Ex-iBox', 'ex_inter' => 'Ex-Inter', 'refurbished' => 'Refurbished'];
        $conditions = $conditionQuery->select('product_details.condition', DB::raw('COUNT(*) as qty'))
            ->whereNotNull('product_details.condition')->groupBy('product_details.condition')->orderByDesc(DB::raw('COUNT(*)'))->get()
            ->where('qty', '>', 0)->map(fn($row) => [
                'label' => ($conditionLabels[$row->condition] ?? ucfirst($row->condition)) . " ({$row->qty})",
                'value' => $row->condition,
                'qty' => $row->qty,
            ])->values();

        $totalAvailable = $filteredQuery->count();

        return response()->json([
            'brands' => $brands,
            'types' => $types,
            'storages' => $storages,
            'conditions' => $conditions,
            'total_available' => $totalAvailable,
        ]);
    }

    public function soldAnalysis(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasRole(['super_admin', 'analist', 'audit'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $timeFilter = $request->input('time_filter');
        $month = $request->input('month');
        $year = $request->input('year');
        
        // Categories definition
        $catTerjual = "CASE WHEN stock_outs.category IN ('penjualan_store', 'penjualan_offline', 'shopee', 'orderan_online', 'bundling') THEN ";
        $catAngkatBarang = "CASE WHEN stock_outs.category = 'angkat_barang' THEN ";
        $catRefund = "CASE WHEN stock_outs.category = 'refund' THEN ";
        $catTukarTambah = "CASE WHEN stock_outs.category = 'tukar_tambah' THEN ";
        $catTukarUnit = "CASE WHEN stock_outs.category = 'tukar_unit' THEN ";
        $catDowngrade = "CASE WHEN stock_outs.category = 'downgrade' THEN ";
        $catRetur = "CASE WHEN stock_outs.category = 'retur' THEN ";

        // Base Query HP
        $hpQuery = StockOut::query()->where('stock_outs.status', '!=', 'cancelled')
            ->join('stock_out_items', 'stock_outs.id', '=', 'stock_out_items.stock_out_id')
            ->join('product_details', 'product_details.id', '=', 'stock_out_items.product_detail_id')
            ->join('products', 'products.id', '=', 'product_details.product_id')
            ->leftJoin('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereNull('products.deleted_at')
            ->whereNull('product_details.deleted_at')
            ->where(function($q) {
                $q->where('products.type', 'hp')->orWhere('products.has_imei', true);
            });

        // Base Query Non HP
        $nonHpQuery = StockOut::query()->where('stock_outs.status', '!=', 'cancelled')
            ->join('stock_out_non_hp_items', 'stock_outs.id', '=', 'stock_out_non_hp_items.stock_out_id')
            ->join('products', 'products.id', '=', 'stock_out_non_hp_items.product_id')
            ->leftJoin('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereNull('products.deleted_at')
            ->where(function($q) {
                $q->where('products.type', 'non-hp')->orWhere('products.has_imei', false);
            });

        // Filters
        $applyFilters = function($query, $isHp = true) use ($request, $timeFilter, $user, $month, $year) {
            if (!empty($month) && !empty($year)) {
                $query->whereMonth('stock_outs.reporting_date', $month)
                      ->whereYear('stock_outs.reporting_date', $year);
            } elseif (!empty($year)) {
                $query->whereYear('stock_outs.reporting_date', $year);
            } elseif ($timeFilter === 'bulan_ini') {
                $query->whereMonth('stock_outs.reporting_date', now()->month)
                      ->whereYear('stock_outs.reporting_date', now()->year);
            } elseif ($timeFilter === 'tahun_ini') {
                $query->whereYear('stock_outs.reporting_date', now()->year);
            }
            
            if ($user->hasRole('audit') && !$user->hasRole(['super_admin', 'analist'])) {
                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $onlineShopIds = $user->getAccessibleOnlineShopIds();
                $query->where(function ($q) use ($branchIds, $warehouseIds, $onlineShopIds) {
                    $hasConstraint = false;
                    if (!empty($branchIds)) { $q->orWhereIn('users.branch_id', $branchIds); $hasConstraint = true; }
                    if (!empty($warehouseIds)) { $q->orWhereIn('users.warehouse_id', $warehouseIds); $hasConstraint = true; }
                    if (!empty($onlineShopIds)) { $q->orWhereIn('users.online_shop_id', $onlineShopIds); $hasConstraint = true; }
                    if (!$hasConstraint) { $q->whereRaw('0 = 1'); }
                });
            }

            if ($request->filled('brand')) $query->where('products.brand', $request->brand);
            if ($request->filled('product_name')) $query->where('products.name', $request->product_name);
            
            if ($isHp) {
                if ($request->filled('storage')) $query->where('product_details.storage', $request->storage);
                if ($request->filled('condition')) $query->where('product_details.condition', $request->condition);
            }
        };

        $applyFilters($hpQuery, true);
        
        $skipNonHp = $request->filled('storage') || $request->filled('condition');
        if (!$skipNonHp) {
            $applyFilters($nonHpQuery, false);
        }

        // Dynamic fields
        $hasTypeFilter = $request->filled('product_name');
        $hasStorageFilter = $request->filled('storage');
        $hasConditionFilter = $request->filled('condition');

        $selectFieldsHp = [
            DB::raw('COALESCE(users.branch_id, users.warehouse_id, users.online_shop_id, 0) as location_id'),
            DB::raw("CASE WHEN users.branch_id IS NOT NULL THEN 'branch' WHEN users.warehouse_id IS NOT NULL THEN 'warehouse' WHEN users.online_shop_id IS NOT NULL THEN 'online_shop' ELSE 'unknown' END as location_type"),
            'products.brand',
            DB::raw("SUM($catTerjual 1 ELSE 0 END) as terjual"),
            DB::raw("SUM($catAngkatBarang 1 ELSE 0 END) as angkat_barang"),
            DB::raw("SUM($catRefund 1 ELSE 0 END) as refund"),
            DB::raw("SUM($catTukarTambah 1 ELSE 0 END) as tukar_tambah"),
            DB::raw("SUM($catTukarUnit 1 ELSE 0 END) as tukar_unit"),
            DB::raw("SUM($catDowngrade 1 ELSE 0 END) as downgrade"),
            DB::raw("SUM($catRetur 1 ELSE 0 END) as retur"),
        ];

        $selectFieldsNonHp = [
            DB::raw('COALESCE(users.branch_id, users.warehouse_id, users.online_shop_id, 0) as location_id'),
            DB::raw("CASE WHEN users.branch_id IS NOT NULL THEN 'branch' WHEN users.warehouse_id IS NOT NULL THEN 'warehouse' WHEN users.online_shop_id IS NOT NULL THEN 'online_shop' ELSE 'unknown' END as location_type"),
            'products.brand',
            DB::raw("SUM($catTerjual stock_out_non_hp_items.quantity ELSE 0 END) as terjual"),
            DB::raw("SUM($catAngkatBarang stock_out_non_hp_items.quantity ELSE 0 END) as angkat_barang"),
            DB::raw("SUM($catRefund stock_out_non_hp_items.quantity ELSE 0 END) as refund"),
            DB::raw("SUM($catTukarTambah stock_out_non_hp_items.quantity ELSE 0 END) as tukar_tambah"),
            DB::raw("SUM($catTukarUnit stock_out_non_hp_items.quantity ELSE 0 END) as tukar_unit"),
            DB::raw("SUM($catDowngrade stock_out_non_hp_items.quantity ELSE 0 END) as downgrade"),
            DB::raw("SUM($catRetur stock_out_non_hp_items.quantity ELSE 0 END) as retur"),
        ];

        $groupByFieldsHp = ['users.branch_id', 'users.warehouse_id', 'users.online_shop_id', 'products.brand'];
        $groupByFieldsNonHp = ['users.branch_id', 'users.warehouse_id', 'users.online_shop_id', 'products.brand'];

        if ($hasTypeFilter) {
            $selectFieldsHp[] = 'products.name as product_name';
            $groupByFieldsHp[] = 'products.name';
            
            $selectFieldsNonHp[] = 'products.name as product_name';
            $groupByFieldsNonHp[] = 'products.name';
        }
        
        if ($hasStorageFilter) {
            $selectFieldsHp[] = 'product_details.storage';
            $groupByFieldsHp[] = 'product_details.storage';
        }
        
        if ($hasConditionFilter) {
            $selectFieldsHp[] = 'product_details.condition';
            $groupByFieldsHp[] = 'product_details.condition';
        }

        $hpResults = $hpQuery->select($selectFieldsHp)->groupBy($groupByFieldsHp)->get();
        
        $nonHpResults = collect();
        if (!$skipNonHp) {
            $nonHpResults = $nonHpQuery->select($selectFieldsNonHp)->groupBy($groupByFieldsNonHp)->get()->map(function($row) {
                $row->storage = null;
                $row->condition = null;
                return $row;
            });
        }

        $mergedResults = $hpResults->concat($nonHpResults);

        $groupKey = function ($row) use ($hasTypeFilter, $hasStorageFilter, $hasConditionFilter) {
            $key = $row->location_type . ':' . $row->location_id . ':' . $row->brand;
            if ($hasTypeFilter && isset($row->product_name)) $key .= ':' . $row->product_name;
            if ($hasStorageFilter && isset($row->storage)) $key .= ':' . $row->storage;
            if ($hasConditionFilter && isset($row->condition)) $key .= ':' . $row->condition;
            return $key;
        };

        $allResults = $mergedResults->groupBy($groupKey)->map(function ($group) {
            $first = $group->first();
            $first->terjual = $group->sum('terjual');
            $first->angkat_barang = $group->sum('angkat_barang');
            $first->refund = $group->sum('refund');
            $first->tukar_tambah = $group->sum('tukar_tambah');
            $first->tukar_unit = $group->sum('tukar_unit');
            $first->downgrade = $group->sum('downgrade');
            $first->retur = $group->sum('retur');
            $first->total_qty = $first->terjual + $first->angkat_barang + $first->refund + $first->tukar_tambah + $first->tukar_unit + $first->downgrade + $first->retur;
            return $first;
        })->sortByDesc('total_qty')->values();

        // Paginate
        $perPage = (int) ($request->per_page ?? 20);
        $page = (int) ($request->page ?? 1);
        $total = $allResults->count();
        $paginatedResults = $allResults->slice(($page - 1) * $perPage, $perPage)->values();

        $data = $paginatedResults->map(function ($row) {
            $locationName = $this->resolveLocationName($row->location_type, $row->location_id);
            return [
                'location_name' => $locationName,
                'location_type' => $row->location_type,
                'brand' => $row->brand,
                'product_name' => $row->product_name ?? null,
                'storage' => $row->storage ?? null,
                'condition' => $row->condition ?? null,
                'terjual' => (int) $row->terjual,
                'angkat_barang' => (int) $row->angkat_barang,
                'refund' => (int) $row->refund,
                'tukar_tambah' => (int) $row->tukar_tambah,
                'tukar_unit' => (int) $row->tukar_unit,
                'downgrade' => (int) $row->downgrade,
                'retur' => (int) $row->retur,
                'total_qty' => (int) $row->total_qty
            ];
        });

        $totalTerjual = $allResults->sum('terjual');
        $totalAngkatBarang = $allResults->sum('angkat_barang');
        $totalRefund = $allResults->sum('refund');
        $totalTukarTambah = $allResults->sum('tukar_tambah');
        $totalTukarUnit = $allResults->sum('tukar_unit');
        $totalDowngrade = $allResults->sum('downgrade');
        $totalRetur = $allResults->sum('retur');
        $totalQtyAll = $allResults->sum('total_qty');
        $totalLocations = $allResults->unique(fn($r) => $r->location_type . ':' . $r->location_id)->count();

        return response()->json([
            'data' => $data->values(),
            'summary' => [
                'total_qty' => $totalQtyAll,
                'total_locations' => $totalLocations,
                'terjual' => $totalTerjual,
                'angkat_barang' => $totalAngkatBarang,
                'refund' => $totalRefund,
                'tukar_tambah' => $totalTukarTambah,
                'tukar_unit' => $totalTukarUnit,
                'downgrade' => $totalDowngrade,
                'retur' => $totalRetur,
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

