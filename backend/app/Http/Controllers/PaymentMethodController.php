<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class PaymentMethodController extends Controller
{
    private function ensureOnlineStoreColumnExists()
    {
        if (!Schema::hasColumn('payment_methods', 'is_online_store')) {
            try {
                Schema::table('payment_methods', function (Blueprint $table) {
                    $table->boolean('is_online_store')->default(false);
                });
            } catch (\Throwable $e) {
                // Ignore if concurrent migration or error
            }
        }
    }

    public function index(Request $request)
    {
        $this->ensureOnlineStoreColumnExists();

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

        // If 'all' or 'include_inactive' is true (e.g. from Settings/PaymentMethods), show all methods
        if (!$request->boolean('all') && !$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        // Optional filter by is_online_store
        if ($request->has('is_online_store')) {
            $query->where('is_online_store', $request->boolean('is_online_store'));
        }

        return response()->json(
            $query->orderBy('name', 'asc')->get()
        );
    }

    public function store(Request $request)
    {
        $this->ensureOnlineStoreColumnExists();

        $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'category' => 'required|string|in:cash,edc,transfer',
            'is_active' => 'boolean',
            'is_online_store' => 'nullable|boolean',
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
        $this->ensureOnlineStoreColumnExists();

        $pm = PaymentMethod::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'category' => 'required|string|in:cash,edc,transfer',
            'is_active' => 'boolean',
            'is_online_store' => 'nullable|boolean',
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
