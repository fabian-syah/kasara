<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Warehouse::query();

        // Scope for restricted roles
        if ($user && !$user->hasRole(['super_admin', 'owner'])) {
            if ($user->hasAnyRole(['audit', 'leader', 'gudang', 'inventory'])) {
                $ids = $user->getAccessibleWarehouseIds();
                $query->whereIn('id', $ids);
            } else {
                // Regular staff see their own
                if ($user->warehouse_id) {
                    $query->where('id', $user->warehouse_id);
                } else {
                    $query->whereRaw('1=0');
                }
            }
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%")
                ->orWhere('code', 'ilike', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:warehouses,code',
            'name' => 'required|string',
            'address' => 'nullable|string',
            'timezone' => 'required|in:WIB,WITA,WIT',
            'is_active' => 'boolean',
            'can_accept_returns' => 'boolean',
        ]);

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'data' => $warehouse
        ], 201);
    }

    public function show(Warehouse $warehouse)
    {
        return response()->json(['success' => true, 'data' => $warehouse]);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', Rule::unique('warehouses')->ignore($warehouse->id)],
            'name' => 'required|string',
            'address' => 'nullable|string',
            'timezone' => 'required|in:WIB,WITA,WIT',
            'is_active' => 'boolean',
            'can_accept_returns' => 'boolean',
        ]);

        $warehouse->update($validated);

        return response()->json(['success' => true, 'data' => $warehouse]);
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();
        return response()->json(['success' => true]);
    }

    public function toggleReturn(Warehouse $warehouse)
    {
        $newValue = !$warehouse->can_accept_returns;

        $warehouse->update([
            'can_accept_returns' => $newValue
        ]);

        return response()->json([
            'success' => true,
            'data' => $warehouse,
            'message' => 'Status terima retur gudang berhasil diubah'
        ]);
    }
}
