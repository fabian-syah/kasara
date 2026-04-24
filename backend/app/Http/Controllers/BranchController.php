<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request)
    // forced update
    {

        $user = $request->user();
        $query = Branch::query();

        // Role-based access control
        if ($request->ignore_scope || $user->hasAnyRole(['super_admin', 'owner', 'admin_produk', 'analist', 'analis'])) {
            // Full access (Global)
            if ($user->hasRole(['analist', 'analis'])) {
                $query->where(function($q) {
                    $hidden = ['trial', 'testing', 'anu', 'huft'];
                    foreach ($hidden as $name) {
                        $q->where('name', 'not ilike', '%' . $name . '%');
                    }
                });
            }
        } else if ($user->hasAnyRole(['audit', 'leader'])) {
            // Assigned access
            $ids = $user->getAccessibleBranchIds();
            $query->whereIn('id', $ids);
        } else {
            // Own assignment access
            if ($user->branch_id) {
                $query->where('id', $user->branch_id);
            } else {
                $query->whereRaw('1=0');
            }
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%')
                ->orWhere('code', 'ilike', '%' . $request->search . '%');
        }

        return response()->json([
            'success' => true,
            'data' => $query->with('paymentMethods')->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:branches,code',
            'name' => 'required|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'warranty_terms' => 'nullable|string',
            'timezone' => 'required|in:WIB,WITA,WIT',
            'type' => 'required|in:physical,online,warehouse',
            'platform' => 'nullable|required_if:type,online|string',
            'url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $branch = Branch::create($validated);

        // Sync payment methods if provided, otherwise sync all active ones as default
        if ($request->has('payment_method_ids')) {
            $branch->paymentMethods()->sync($request->payment_method_ids);
        } else {
            $allMethods = \App\Models\PaymentMethod::where('is_active', true)->pluck('id');
            $branch->paymentMethods()->sync($allMethods);
        }

        return response()->json([
            'success' => true,
            'data' => $branch->load('paymentMethods')
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
            'phone' => 'nullable|string',
            'warranty_terms' => 'nullable|string',
            'timezone' => 'required|in:WIB,WITA,WIT',
            'type' => 'required|in:physical,online,warehouse',
            'platform' => 'nullable|required_if:type,online|string',
            'url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $branch->update($validated);

        if ($request->has('payment_method_ids')) {
            $branch->paymentMethods()->sync($request->payment_method_ids);
        }

        return response()->json(['success' => true, 'data' => $branch->load('paymentMethods')]);
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();
        return response()->json(['success' => true]);
    }

    public function toggleStatus(Branch $branch)
    {
        $newValue = !$branch->is_active;

        $branch->update([
            'is_active' => $newValue
        ]);

        return response()->json([
            'success' => true,
            'data' => $branch,
            'message' => 'Status aktif cabang berhasil diubah'
        ]);
    }
}
