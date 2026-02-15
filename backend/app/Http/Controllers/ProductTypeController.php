<?php

namespace App\Http\Controllers;

use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        if ($request->filled('search')) {
            $search = $request->search;
            $cleanSearch = preg_replace('/[^a-zA-Z0-9]/', '', $search);

            $query->where(function ($q) use ($search, $cleanSearch) {
                // Try normal ILIKE first
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhereRaw("REGEXP_REPLACE(name, '[^a-zA-Z0-9]', '', 'g') ilike ?", ["%{$cleanSearch}%"])
                    // Case-insensitive search for Brand Name
                    ->orWhereHas('brand', function ($qBrand) use ($search, $cleanSearch) {
                        $qBrand->where('name', 'ilike', "%{$search}%")
                            ->orWhereRaw("REGEXP_REPLACE(name, '[^a-zA-Z0-9]', '', 'g') ilike ?", ["%{$cleanSearch}%"]);
                    });
            });
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_types')->where(function ($query) use ($request) {
                    return $query->where('brand_id', $request->brand_id);
                }),
            ],
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_types')->ignore($productType->id)->where(function ($query) use ($request) {
                    return $query->where('brand_id', $request->brand_id);
                }),
            ],
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
