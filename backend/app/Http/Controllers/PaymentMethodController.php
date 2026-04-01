<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentMethod::query();
        $branchId = $request->branch_id;

        // If no branch_id in request, check if user is assigned to a branch
        if (!$branchId && auth()->check() && auth()->user()->branch_id) {
            $branchId = auth()->user()->branch_id;
        }

        if ($branchId) {
            // Check if this branch has ANY payment methods assigned
            $hasSpecificMethods = \Illuminate\Support\Facades\DB::table('branch_payment_method')
                ->where('branch_id', $branchId)
                ->exists();

            if ($hasSpecificMethods) {
                $query->whereHas('branches', function ($q) use ($branchId) {
                    $q->where('branches.id', $branchId);
                });
            }
        }

        return response()->json(
            $query->where('is_active', true)
                ->orderBy('name', 'asc')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'category' => 'required|string|in:cash,edc,transfer',
            'is_active' => 'boolean',
        ]);

        $pm = PaymentMethod::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Payment Method created.',
            'data' => $pm
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $pm = PaymentMethod::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'category' => 'required|string|in:cash,edc,transfer',
            'is_active' => 'boolean',
        ]);

        $pm->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Payment Method updated.',
            'data' => $pm
        ]);
    }

    public function destroy($id)
    {
        $pm = PaymentMethod::findOrFail($id);
        $pm->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment Method deleted.'
        ]);
    }
}
