<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $query = Branch::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%')
                ->orWhere('code', 'ilike', '%' . $request->search . '%');
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:branches,code',
            'name' => 'required|string',
            'address' => 'nullable|string',
            'timezone' => 'required|in:WIB,WITA,WIT',
            'type' => 'required|in:physical,online,warehouse',
            'platform' => 'nullable|required_if:type,online|string',
            'url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
        ]);

        $branch = Branch::create($validated);

        return response()->json([
            'success' => true,
            'data' => $branch
        ], 201);
    }

    public function show(Branch $branch)
    {
        return response()->json(['success' => true, 'data' => $branch]);
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', Rule::unique('branches')->ignore($branch->id)],
            'name' => 'required|string',
            'address' => 'nullable|string',
            'timezone' => 'required|in:WIB,WITA,WIT',
            'type' => 'required|in:physical,online,warehouse',
            'platform' => 'nullable|required_if:type,online|string',
            'url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $branch->update($validated);

        return response()->json(['success' => true, 'data' => $branch]);
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();
        return response()->json(['success' => true]);
    }

    public function toggleReturn(Branch $branch)
    {
        $newValue = !$branch->can_accept_returns;

        $branch->update([
            'can_accept_returns' => $newValue
        ]);

        // If Super Admin, apply purely universal (all branches follow the toggle)
        $user = request()->user();
        if ($user && $user->hasRole('super_admin')) {
            Branch::query()->update(['can_accept_returns' => $newValue]);
        }

        return response()->json([
            'success' => true,
            'data' => $branch,
            'message' => 'Status terima retur berhasil diubah' . ($user->hasRole('super_admin') ? ' untuk semua cabang' : '')
        ]);
    }
}
