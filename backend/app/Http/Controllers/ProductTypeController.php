<?php

namespace App\Http\Controllers;

use App\Models\ProductType;
use Illuminate\Http\Request;

class ProductTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductType::with('brand');

        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('category')) {
            $cat = $request->category;
            if ($cat === 'hp' || $cat === 'imei') {
                $query->whereIn('category', ['imei', 'HP / Gadget']);
            } elseif ($cat === 'non-hp' || $cat === 'non_imei') {
                $query->whereNotIn('category', ['imei', 'HP / Gadget']);
            } else {
                $query->where('category', $cat);
            }
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'name' => 'required|string|max:255',
            'category' => 'required|in:imei,non_imei,service',
            'ram' => 'nullable|string',
            'storage' => 'nullable|string',
            'cost_price' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
        ]);

        $productType = ProductType::create($validated);

        return response()->json([
            'success' => true,
            'data' => $productType->load('brand'),
            'message' => 'Product Type created successfully'
        ], 201);
    }

    public function update(Request $request, ProductType $productType)
    {
        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'name' => 'required|string|max:255',
            'category' => 'required|in:imei,non_imei,service',
            'ram' => 'nullable|string',
            'storage' => 'nullable|string',
            'cost_price' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
        ]);

        $productType->update($validated);

        return response()->json([
            'success' => true,
            'data' => $productType->load('brand'),
            'message' => 'Product Type updated successfully'
        ]);
    }

    public function destroy(ProductType $productType)
    {
        $productType->delete();
        return response()->json([
            'success' => true,
            'message' => 'Product Type deleted successfully'
        ]);
    }
}
