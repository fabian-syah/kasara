<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\VerifiesPin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InventoryAccountController extends Controller
{
    use VerifiesPin;

    public function createAccount(Request $request)
    {
        Log::info('Entering createAccount', ['user_id' => Auth::id(), 'request' => $request->all()]);

        $request->validate([
            'name' => 'required|string|max:50',
            'transaction_pin' => 'nullable|string|size:4'
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user->branch_id && !$user->warehouse_id && !$user->online_shop_id && !$user->distributor_id && !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Anda tidak memiliki lokasi fisik untuk membuat akun inventory.'], 403);
        }

        $username = 'inv.' . strtolower(Str::random(8)) . '.' . rand(100, 999);
        $email = $username . '@apex-inventory.com';
        $password = 'inventory123';

        DB::beginTransaction();
        try {
            $roleName = 'inventory';
            if (!\Spatie\Permission\Models\Role::where('name', $roleName)->exists()) {
                \Spatie\Permission\Models\Role::create(['name' => $roleName, 'guard_name' => 'web']);
            }

            $newUser = User::create([
                'name' => $request->name,
                'full_name' => $request->name,
                'username' => $username,
                'code_id' => 'INV-' . strtoupper(Str::random(10)),
                'email' => $email,
                'password' => $password,
                'branch_id' => $request->branch_id ?? $user->branch_id ?? ($user->getAccessibleBranchIds()[0] ?? null),
                'warehouse_id' => $request->warehouse_id ?? $user->warehouse_id,
                'online_shop_id' => $request->online_shop_id ?? $user->online_shop_id,
                'distributor_id' => $request->distributor_id ?? $user->distributor_id,
                'created_by' => $user->id,
                'is_active' => true,
                'theme_color' => 'default',
                'transaction_pin' => $request->transaction_pin ?? '0000',
                'pin_enabled' => false,
            ]);

            $newUser->assignRole($roleName);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Akun inventory berhasil dibuat.',
                'data' => $newUser
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create Inventory Account Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateAccount(Request $request, $id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $account = User::findOrFail($id);

        $unrestrictedRoles = ['super_admin', 'owner', 'admin_produk', 'analist'];
        $userRole = strtolower($user->roles->first()->name ?? '');

        // Check if user and account share the same placement
        $isSamePlacement = false;
        if ($account->branch_id && $account->branch_id == ($user->branch_id ?? $request->header('X-Branch-ID'))) {
            $isSamePlacement = true;
        } elseif ($account->online_shop_id && $account->online_shop_id == ($user->online_shop_id ?? $request->header('X-Online-Shop-ID'))) {
            $isSamePlacement = true;
        } elseif ($account->warehouse_id && $account->warehouse_id == ($user->warehouse_id ?? $request->header('X-Warehouse-ID'))) {
            $isSamePlacement = true;
        }

        if ($account->created_by !== $user->id && $account->id !== $user->id && !in_array($userRole, $unrestrictedRoles) && !$isSamePlacement) {
            return response()->json(['message' => 'Unauthorized action. Hanya pembuat akun atau user di penempatan yang sama yang bisa mengedit.'], 403);
        }

        $request->validate([
            'name' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:50|unique:users,username,' . $id,
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'branch_id' => 'nullable|integer',
            'warehouse_id' => 'nullable|integer',
            'online_shop_id' => 'nullable|integer',
            'distributor_id' => 'nullable|integer',
            'photo_inventory' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:10240',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:10240',
            'transaction_pin' => 'nullable|string|size:4',
            'pin_enabled' => 'nullable|boolean'
        ]);

        if ($request->has('branch_id'))
            $account->branch_id = $request->branch_id;
        if ($request->has('warehouse_id'))
            $account->warehouse_id = $request->warehouse_id;
        if ($request->has('online_shop_id'))
            $account->online_shop_id = $request->online_shop_id;
        if ($request->has('distributor_id'))
            $account->distributor_id = $request->distributor_id;

        if ($request->has('name')) {
            $account->name = $request->name;
            $account->full_name = $request->name;
        }

        if ($request->has('username')) {
            $account->username = $request->username;
        }

        if ($request->has('password') && !empty($request->password)) {
            $account->password = Hash::make($request->password);
            $account->password_changed_at = now();
        }

        $account->phone = $request->phone;

        $photoField = $request->hasFile('photo') ? 'photo' : ($request->hasFile('photo_inventory') ? 'photo_inventory' : null);

        if ($photoField) {
            $path = $request->file($photoField)->store('account-photos', 'public');

            if ($account->photo_inventory || $account->photo) {
                $account->pending_photo_inventory = $path;
            } else {
                $account->photo_inventory = $path;
                $account->photo = $path;
            }
        }

        if ($request->has('transaction_pin')) {
            $account->transaction_pin = $request->transaction_pin;
        }

        if ($request->has('pin_enabled')) {
            $account->pin_enabled = (bool) $request->pin_enabled;
        }

        $account->load(['roles', 'createdBy']);
        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Akun inventory berhasil diupdate.',
            'data' => $account
        ]);
    }

    public function togglePin(Request $request, $id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        /** @var \App\Models\User $account */
        $account = User::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id);
            })
            ->firstOrFail();

        if ($account->transaction_pin) {
            $request->validate(['transaction_pin' => 'required|string']);
            if (!Hash::check($request->transaction_pin, $account->transaction_pin)) {
                return response()->json(['success' => false, 'message' => 'PIN salah.'], 422);
            }
        }

        $account->pin_enabled = !$account->pin_enabled;
        
        if (!$account->pin_enabled) {
            $account->transaction_pin = null;
        }
        
        $account->pin_reset_requested_at = null;
        
        $account->save();

        return response()->json(['success' => true, 'data' => $account->load(['roles', 'createdBy'])]);
    }

    public function requestResetPin(Request $request, $id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $account = User::where('id', $id)->where('created_by', $user->id)->firstOrFail();

        $account->pin_reset_requested_at = now();
        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Permintaan reset PIN telah dicatat.'
        ]);
    }

    public function getMyInventoryUsers(Request $request)
    {
        $syncNeeded = User::role('inventory')
            ->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNotNull('photo_inventory')->whereNull('photo');
                })->orWhere(function ($sq) {
                    $sq->whereNotNull('photo')->whereNull('photo_inventory');
                })->orWhereRaw('photo != photo_inventory');
            })->get();

        foreach ($syncNeeded as $u) {
            $u->photo = $u->photo_inventory ?: $u->photo;
            $u->photo_inventory = $u->photo;
            $u->save();
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $branchId = $request->branch_id ?? $request->header('X-Branch-ID');
        $onlineShopId = $request->online_shop_id ?? $request->header('X-Online-Shop-ID');
        $warehouseId = $request->warehouse_id ?? $request->header('X-Warehouse-ID');

        $query = User::role('inventory')
            ->with([
                'roles',
                'createdBy' => function ($q) {
                    $q->select('id', 'name', 'full_name');
                }
            ])
            ->where('is_active', true);

        if ($user->hasRole('inventory')) {
            $query->where('id', $user->id);
        } else {
            $query->where('id', '!=', $user->id);

            $unrestrictedRoles = ['super_admin', 'owner', 'admin_produk', 'analist'];
            $isUnrestrictedUser = $user->hasRole($unrestrictedRoles);
            
            // Location filter (branch/warehouse/online_shop)
            if ($branchId) {
                $query->where('branch_id', $branchId);
            } elseif ($onlineShopId) {
                $query->where('online_shop_id', $onlineShopId);
            } elseif ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            } elseif (!$isUnrestrictedUser) {
                // Fallback: filter by accessible placements
                $query->where(function ($q) use ($user) {
                    if ($user->branch_id) $q->orWhere('branch_id', $user->branch_id);
                    if ($user->online_shop_id) $q->orWhere('online_shop_id', $user->online_shop_id);
                    if ($user->warehouse_id) $q->orWhere('warehouse_id', $user->warehouse_id);

                    if (method_exists($user, 'getAccessibleBranchIds')) {
                        $branchIds = $user->getAccessibleBranchIds();
                        if (!empty($branchIds)) $q->orWhereIn('branch_id', $branchIds);
                        
                        $onlineShopIds = $user->getAccessibleOnlineShopIds();
                        if (!empty($onlineShopIds)) $q->orWhereIn('online_shop_id', $onlineShopIds);

                        $warehouseIds = $user->getAccessibleWarehouseIds();
                        if (!empty($warehouseIds)) $q->orWhereIn('warehouse_id', $warehouseIds);
                    }
                });
            }

            // Ownership isolation: non-unrestricted users only see inventory accounts they created
            if (!$isUnrestrictedUser) {
                $query->where('created_by', $user->id);
            }
        }

        $inventoryUsers = $query->select('id', 'name', 'full_name', 'username', 'code_id', 'created_by', 'pin_enabled', 'transaction_pin', 'pin_reset_requested_at', 'photo', 'photo_inventory', 'branch_id', 'warehouse_id', 'online_shop_id')
            ->get()
            ->map(function ($u) {
                $u->has_pin = !empty($u->transaction_pin);
                return $u;
            });

        return response()->json($inventoryUsers);
    }

    public function destroyAccount($id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $account = User::findOrFail($id);

        $unrestrictedRoles = ['super_admin', 'owner', 'admin_produk', 'analist'];
        if ($account->created_by !== $user->id && !$user->hasRole($unrestrictedRoles)) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $hasHistory = \App\Models\InventoryLog::where('user_id', $account->id)->exists() ||
            \App\Models\ProductDetail::where('user_id', $account->id)->exists() ||
            \App\Models\StockOut::where('inventory_user_id', $account->id)->exists() ||
            \App\Models\StockOut::where('confirmed_by', $account->id)->exists();

        if ($hasHistory) {
            $account->update(['is_active' => false]);
            return response()->json(['message' => 'Akun dinonaktifkan karena memiliki riwayat transaksi.', 'status' => 'archived']);
        }

        $account->delete();
        return response()->json(['message' => 'Akun berhasil dihapus permanen.', 'status' => 'deleted']);
    }
}
