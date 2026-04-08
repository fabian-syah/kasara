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
