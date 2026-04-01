<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // One-time auto-fix for name vs full_name desyncs
        // Only trigger occasionally or on first load, maybe just run it silently
        $desynced = \App\Models\User::whereNotNull('full_name')
            ->whereRaw('name != full_name')
            ->get();
        if ($desynced->count() > 0) {
            foreach ($desynced as $u) {
                // If the user's login username is 'distributortrial' but full_name is 'adminproduk',
                // we want the profile to show the full_name. We shouldn't necessarily overwrite 
                // username but 'name' which is the display name.
                $u->name = $u->full_name;
                $u->save();
            }
        }

        $query = User::with(['branch', 'warehouse', 'onlineShop', 'distributor', 'roles', 'createdBy', 'createdUsers', 'placements']);

        // Jika bukan super_admin, filter berdasarkan placement user login
        if (!$user->hasRole('super_admin')) {
            // Logic Isolation:
            // Untuk role Toko Online, Sales, dll yang sifatnya "Individual" bukan Branch, 
            // maka hanya bisa melihat akun yang DIA BUAT SENDIRI (e.g. Inventory Account nya).
            // Atau dirinya sendiri.
            if ($user->hasAnyRole(['toko_online', 'toko_offline', 'inventory', 'leader_shopee'])) {
                $query->where(function ($q) use ($user) {
                    // 1. Own accounts or Self
                    $q->where('created_by', $user->id)
                        ->orWhere('id', $user->id);

                    // 2. Allow seeing "Inventory" accounts in the SAME placement
                    $q->orWhere(function ($sub) use ($user) {
                        $sub->whereHas('roles', function ($r) {
                            $r->where('name', 'inventory');
                        });

                        $sub->where(function ($place) use ($user) {
                            // Match any placement that the current user has
                            $hasPlacement = false;
                            if ($user->branch_id) {
                                $place->orWhere('branch_id', $user->branch_id);
                                $hasPlacement = true;
                            }
                            if ($user->warehouse_id) {
                                $place->orWhere('warehouse_id', $user->warehouse_id);
                                $hasPlacement = true;
                            }
                            if ($user->online_shop_id) {
                                $place->orWhere('online_shop_id', $user->online_shop_id);
                                $hasPlacement = true;
                            }

                            // If user has no placement (edge case), ensure we don't return all inventory
                            if (!$hasPlacement) {
                                $place->whereRaw('1 = 0');
                            }
                        });
                    });
                });
            }
            // Untuk Branch/Warehouse/Gudang, kita tetap pakai logic placement sharing
            else {
                if ($user->hasAnyRole(['audit', 'leader'])) {
                    $branchIds = $user->getAccessibleBranchIds();
                    $onlineShopIds = $user->getAccessibleOnlineShopIds();
                    $warehouseIds = $user->getAccessibleWarehouseIds();

                    $query->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds) {
                        if (!empty($branchIds))
                            $q->orWhereIn('branch_id', $branchIds);

                        if (!empty($warehouseIds))
                            $q->orWhereIn('warehouse_id', $warehouseIds);

                        if (!empty($onlineShopIds)) {
                            // Fix: Only show online shop users who are NOT assigned to a physical branch
                            // This prevents "Branch Sales" from appearing in "Online Shop Audit"
                            $q->orWhere(function ($sub) use ($onlineShopIds) {
                                $sub->whereIn('online_shop_id', $onlineShopIds)
                                    ->whereNull('branch_id');
                            });
                        }

                        // Show all if empty? No, show nothing if no access
                        if (empty($branchIds) && empty($onlineShopIds) && empty($warehouseIds))
                            $q->whereRaw('0=1');
                    });
                } else {
                    if ($user->branch_id) {
                        $query->where('branch_id', $user->branch_id);
                    }
                    if ($user->warehouse_id) {
                        $query->where('warehouse_id', $user->warehouse_id);
                    }
                    if ($user->online_shop_id) {
                        $query->where('online_shop_id', $user->online_shop_id);
                    }
                    if ($user->distributor_id) {
                        $query->where('distributor_id', $user->distributor_id);
                    }
                }
            }
        }

        // Filters
        if ($request->has('branch_id'))
            $query->where('branch_id', $request->branch_id);
        if ($request->has('warehouse_id'))
            $query->where('warehouse_id', $request->warehouse_id);
        if ($request->has('role'))
            $query->role($request->role);

        if ($request->boolean('needs_reset')) {
            $query->whereNotNull('pin_reset_requested_at');
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $currentUser = $request->user();

        // Audit role restriction: block forbidden roles
        if ($currentUser->hasRole('audit')) {
            $forbiddenRoles = ['super_admin', 'audit', 'analist', 'admin_produk'];
            if (in_array($request->role, $forbiddenRoles)) {
                return response()->json(['message' => 'Anda tidak memiliki izin untuk membuat user dengan role ini.'], 403);
            }

            // Validate placements against audit user's accessible locations
            $accessibleBranchIds = $currentUser->getAccessibleBranchIds();
            $accessibleWarehouseIds = $currentUser->getAccessibleWarehouseIds();
            $accessibleOnlineShopIds = $currentUser->getAccessibleOnlineShopIds();
            $accessibleDistributorIds = $currentUser->getAccessibleDistributorIds();

            if ($request->branch_id && !in_array($request->branch_id, $accessibleBranchIds)) {
                return response()->json(['message' => 'Anda tidak memiliki akses ke cabang ini.'], 403);
            }
            if ($request->warehouse_id && !in_array($request->warehouse_id, $accessibleWarehouseIds)) {
                return response()->json(['message' => 'Anda tidak memiliki akses ke gudang ini.'], 403);
            }
            if ($request->online_shop_id && !in_array($request->online_shop_id, $accessibleOnlineShopIds)) {
                return response()->json(['message' => 'Anda tidak memiliki akses ke toko online ini.'], 403);
            }
            if ($request->distributor_id && !in_array($request->distributor_id, $accessibleDistributorIds)) {
                return response()->json(['message' => 'Anda tidak memiliki akses ke distributor ini.'], 403);
            }
        }

        $request->validate([
            'username' => 'required|string|unique:users,username',
            'full_name' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        try {
            // Determine placement based on input. Only one should be set ideally, or handled by frontend.
            $branchId = $request->branch_id ?: null;
            $warehouseId = $request->warehouse_id ?: null;
            $onlineShopId = $request->online_shop_id ?: null;
            $distributorId = $request->distributor_id ?: null;

            $user = \App\Models\User::create([
                'name' => $request->full_name,
                'full_name' => $request->full_name,
                'username' => $request->username,
                'email' => $request->username . '@apexpos.com',
                'password' => $request->password,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'online_shop_id' => $onlineShopId,
                'distributor_id' => $distributorId,
                'is_active' => $request->is_active ?? true,
                'theme_color' => 'default',
            ]);

            if ($request->role) {
                $user->assignRole($request->role);
            }

            // Handle Multi-Placements (Audit, Leader, Distributor, etc.)
            // If any of these keys exist in request, we process them
            if (
                $request->has('selected_branches') ||
                $request->has('selected_online_shops') ||
                $request->has('selected_warehouses') ||
                $request->has('selected_distributors')
            ) {
                $placements = [];
                if ($request->selected_branches && is_array($request->selected_branches)) {
                    foreach ($request->selected_branches as $id) {
                        $placements[] = ['model_type' => 'branch', 'model_id' => $id];
                    }
                }
                if ($request->selected_online_shops && is_array($request->selected_online_shops)) {
                    foreach ($request->selected_online_shops as $id) {
                        $placements[] = ['model_type' => 'online_shop', 'model_id' => $id];
                    }
                }
                if ($request->selected_warehouses && is_array($request->selected_warehouses)) {
                    foreach ($request->selected_warehouses as $id) {
                        $placements[] = ['model_type' => 'warehouse', 'model_id' => $id];
                    }
                }
                if ($request->selected_distributors && is_array($request->selected_distributors)) {
                    foreach ($request->selected_distributors as $id) {
                        $placements[] = ['model_type' => 'distributor', 'model_id' => $id];
                    }
                }
                if (!empty($placements)) {
                    $user->placements()->createMany($placements);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $user->load('roles', 'branch', 'warehouse', 'onlineShop', 'distributor', 'placements')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function show(User $user)
    {
        // Simple auth check for now
        return response()->json(['success' => true, 'data' => $user->load('roles', 'branch', 'warehouse', 'onlineShop', 'distributor')]);
    }

    public function update(Request $request, User $user)
    {
        $currentUser = $request->user();

        // Audit role restriction on update
        if ($currentUser->hasRole('audit')) {
            $forbiddenRoles = ['super_admin', 'audit', 'analist', 'admin_produk'];
            if ($request->role && in_array($request->role, $forbiddenRoles)) {
                return response()->json(['message' => 'Anda tidak memiliki izin untuk mengubah role ke role ini.'], 403);
            }

            // Also block editing users that have forbidden roles
            if ($user->hasAnyRole($forbiddenRoles)) {
                return response()->json(['message' => 'Anda tidak memiliki izin untuk mengedit user ini.'], 403);
            }
        }

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'username' => ['sometimes', 'string', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|string|exists:roles,name',
            'branch_id' => 'nullable|exists:branches,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'online_shop_id' => 'nullable|exists:online_shops,id',
            'distributor_id' => 'nullable|exists:distributors,id',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'is_active' => 'boolean',
            'transaction_pin' => 'nullable|string|size:4',
        ]);

        // Logic to clear other placements if one is selected? 
        // For now trusting frontend to send nulls for others, or we explicitly nullify others?
        // Let's rely on payload.

        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($user->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->photo)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo);
            }
            $path = $request->file('photo')->store('profile-photos', 'public');
            $validated['photo'] = $path;

            // Sync with photo_inventory to ensure Stock In and User Management views match
            $validated['photo_inventory'] = $path;
        }

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        } else {
            unset($validated['password']);
        }

        // Sync name with full_name
        if (isset($validated['full_name'])) {
            $validated['name'] = $validated['full_name'];
        }

        if ($request->filled('transaction_pin')) {
            $validated['transaction_pin'] = Hash::make($request->transaction_pin);
            $validated['pin_reset_requested_at'] = null;
            $validated['pin_enabled'] = true; // Enable it if admin sets it
        }

        $user->update($validated);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        // Handle Multi-Placements (Audit, Leader, Distributor, etc.)
        if (
            $request->has('selected_branches') ||
            $request->has('selected_online_shops') ||
            $request->has('selected_warehouses') ||
            $request->has('selected_distributors')
        ) {
            $user->placements()->delete();
            $placements = [];
            if ($request->selected_branches && is_array($request->selected_branches)) {
                foreach ($request->selected_branches as $id) {
                    $placements[] = ['model_type' => 'branch', 'model_id' => $id];
                }
            }
            if ($request->selected_online_shops && is_array($request->selected_online_shops)) {
                foreach ($request->selected_online_shops as $id) {
                    $placements[] = ['model_type' => 'online_shop', 'model_id' => $id];
                }
            }
            if ($request->selected_warehouses && is_array($request->selected_warehouses)) {
                foreach ($request->selected_warehouses as $id) {
                    $placements[] = ['model_type' => 'warehouse', 'model_id' => $id];
                }
            }
            if ($request->selected_distributors && is_array($request->selected_distributors)) {
                foreach ($request->selected_distributors as $id) {
                    $placements[] = ['model_type' => 'distributor', 'model_id' => $id];
                }
            }
            if (!empty($placements)) {
                $user->placements()->createMany($placements);
            }
        }

        return response()->json(['success' => true, 'data' => $user->load('roles', 'branch', 'warehouse', 'onlineShop', 'distributor', 'placements')]);
    }

    public function destroy(User $user)
    {
        $currentUser = request()->user();

        // Audit role: can only delete users within their accessible placements, not forbidden roles
        if ($currentUser->hasRole('audit')) {
            $forbiddenRoles = ['super_admin', 'audit', 'analist', 'admin_produk'];
            if ($user->hasAnyRole($forbiddenRoles)) {
                return response()->json(['message' => 'Anda tidak memiliki izin untuk menghapus user ini.'], 403);
            }

            $accessibleBranchIds = $currentUser->getAccessibleBranchIds();
            $accessibleWarehouseIds = $currentUser->getAccessibleWarehouseIds();
            $accessibleOnlineShopIds = $currentUser->getAccessibleOnlineShopIds();
            $accessibleDistributorIds = $currentUser->getAccessibleDistributorIds();

            $hasAccess = false;
            if ($user->branch_id && in_array($user->branch_id, $accessibleBranchIds))
                $hasAccess = true;
            if ($user->warehouse_id && in_array($user->warehouse_id, $accessibleWarehouseIds))
                $hasAccess = true;
            if ($user->online_shop_id && in_array($user->online_shop_id, $accessibleOnlineShopIds))
                $hasAccess = true;
            if ($user->distributor_id && in_array($user->distributor_id, $accessibleDistributorIds))
                $hasAccess = true;

            if (!$hasAccess) {
                return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus user ini.'], 403);
            }
        } elseif (!$currentUser->hasRole('super_admin') && $currentUser->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user->delete();
        return response()->json(['success' => true]);
    }
}
