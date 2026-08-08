<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $query = Brand::query();

        if ($request->filled('search')) {
            $search = strtolower((string) $request->search);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"]);
        }

        return response()->json([
            'success' => true,
            'data' => $query->withCount('productTypes')->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'description' => 'nullable|string',
            'category' => 'required|in:imei,non-imei',
        ]);

        $brand = Brand::create($validated);

        return response()->json([
            'success' => true,
            'data' => $brand,
            'message' => 'Brand created successfully'
        ], 201);
    }

    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,' . $brand->id,
            'description' => 'nullable|string',
            'category' => 'required|in:imei,non-imei',
        ]);

        $brand->update($validated);

        return response()->json([
            'success' => true,
            'data' => $brand,
            'message' => 'Brand updated successfully'
        ]);
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();
        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully'
        ]);
    }
}
