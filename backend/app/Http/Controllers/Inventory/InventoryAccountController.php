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
            'username' => 'nullable|string|max:50|unique:users,username',
            // 'password' => 'nullable|string|min:4',
            'transaction_pin' => 'nullable|string|size:4'
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user->branch_id && !$user->warehouse_id && !$user->online_shop_id && !$user->distributor_id && !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Anda tidak memiliki lokasi fisik untuk membuat akun inventory.'], 403);
        }

        $username = $request->username ?: 'inv.' . strtolower(Str::random(8)) . '.' . rand(100, 999);
        $email = $username . '@apex-inventory.com';
        $password = $request->password ?: 'inventory123';

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
            'transaction_pin' => 'nullable|string|size:4',
            'remove_pin' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'branch_id' => 'nullable|integer',
            'warehouse_id' => 'nullable|integer',
            'online_shop_id' => 'nullable|integer',
            'distributor_id' => 'nullable|integer',
            'photo_inventory' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:10240',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:10240'
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

        if ($request->has('transaction_pin') && !empty($request->transaction_pin)) {
            $account->transaction_pin = Hash::make($request->transaction_pin);
            $account->pin_enabled = true;
        } elseif ($request->has('remove_pin') && $request->remove_pin == 'true') {
            $account->transaction_pin = null;
            $account->pin_enabled = false;
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
        return response()->json(['success' => false, 'message' => 'Not supported'], 400);
    }

    public function requestResetPin($id)
    {
        return response()->json(['success' => true, 'message' => 'Not supported'], 400);
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
            
            // Ownership isolation: non-unrestricted users ONLY see inventory accounts they created
            if (!$isUnrestrictedUser) {
                $query->where('created_by', $user->id);
                // We SKIP the location filter if it's a restricted user, because they should always see their own inventory accounts
                // regardless of whether the inventory account's location matches their current location.
            } else {
                // Location filter for UNRESTRICTED users (Super Admin, etc)
                if ($branchId) {
                    $query->where('branch_id', $branchId);
                } elseif ($onlineShopId) {
                    $query->where('online_shop_id', $onlineShopId);
                } elseif ($warehouseId) {
                    $query->where('warehouse_id', $warehouseId);
                } else {
                    // Fallback for unrestricted users if no header passed
                    $query->where(function($q) use ($user) {
                        $branchIds = $user->getAccessibleBranchIds();
                        if (!empty($branchIds)) $q->orWhereIn('branch_id', $branchIds);
                        
                        $onlineShopIds = $user->getAccessibleOnlineShopIds();
                        if (!empty($onlineShopIds)) $q->orWhereIn('online_shop_id', $onlineShopIds);

                        $warehouseIds = $user->getAccessibleWarehouseIds();
                        if (!empty($warehouseIds)) $q->orWhereIn('warehouse_id', $warehouseIds);
                    });
                }
            }
        }

        $inventoryUsers = $query->select('id', 'name', 'full_name', 'username', 'code_id', 'created_by', 'photo', 'photo_inventory', 'branch_id', 'warehouse_id', 'online_shop_id', 'pin_enabled')
            ->get();

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

