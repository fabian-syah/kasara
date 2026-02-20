<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index()
    {
        // Simple list, ordered by active then name
        return response()->json(
            PaymentMethod::orderBy('is_active', 'desc')
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
