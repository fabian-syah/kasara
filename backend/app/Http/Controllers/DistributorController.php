<?php

namespace App\Http\Controllers;

use App\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DistributorController extends Controller
{
    public function index(Request $request)
    {
        $query = Distributor::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%")
                ->orWhere('code', 'ilike', "%{$search}%")
                ->orWhere('contact_person', 'ilike', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'code' => 'nullable|string|unique:distributors,code',
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
        ]);

        $distributor = Distributor::create($validated);

        return response()->json([
            'success' => true,
            'data' => $distributor
        ], 201);
    }

    public function show(Distributor $distributor)
    {
        return response()->json(['success' => true, 'data' => $distributor]);
    }

    public function update(Request $request, Distributor $distributor)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'code' => ['nullable', 'string', Rule::unique('distributors')->ignore($distributor->id)],
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $distributor->update($validated);

        return response()->json(['success' => true, 'data' => $distributor]);
    }

    public function destroy(Distributor $distributor)
    {
        $distributor->delete();
        return response()->json(['success' => true]);
    }
}
