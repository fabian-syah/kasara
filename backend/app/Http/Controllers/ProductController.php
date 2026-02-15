<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->search) {
            $search = $request->search;
            $cleanSearch = preg_replace('/[^a-zA-Z0-9]/', '', $search);

            $query->where(function ($q) use ($search, $cleanSearch) {
                $q->where('name', 'ilike', '%' . $search . '%')
                    ->orWhereRaw("REGEXP_REPLACE(name, '[^a-zA-Z0-9]', '', 'g') ilike ?", ["%{$cleanSearch}%"])
                    ->orWhere('sku', 'ilike', '%' . $search . '%');
            });
        }

        if ($request->brand) {
            $query->where('brand', $request->brand);
        }

        return $query->latest()->paginate(20);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:255', // Expecting Brand Name string
            'type' => 'required|in:hp,non-hp',
            'category' => 'nullable|string',
            'price' => 'nullable|numeric',
            'min_stock' => 'nullable|integer',
            'has_imei' => 'boolean'
        ]);

        // Auto Generate SKU if not provided
        $sku = $request->sku;
        if (!$sku) {
            // Generate simple SKU: BRAND-RANDOM OR just UUID
            // Let's use simplified SKU: HP-TIMESTAMP-RAND
            $prefix = $request->type === 'hp' ? 'HP' : 'ACC';
            $sku = $prefix . '-' . strtoupper(Str::random(8));
        }

        $product = Product::create([
            'name' => $request->name,
            'sku' => $sku,
            'barcode' => $request->barcode,
            'type' => $request->type,
            'brand' => $request->brand,
            'category' => $request->category,
            'price' => $request->price ?? 0,
            'min_stock' => $request->min_stock ?? 0,
            'has_imei' => $request->has_imei ?? ($request->type === 'hp'),
            'description' => $request->description,
            'is_active' => true
        ]);

        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Product::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);
        $product->update($request->all());
        return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Product::destroy($id);
        return response()->json(null, 204);
    }
}
