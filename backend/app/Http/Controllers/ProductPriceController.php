<?php

namespace App\Http\Controllers;

use App\Models\ProductPrice;
use Illuminate\Http\Request;

class ProductPriceController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductPrice::with(['productType.brand']);

        if ($request->has('product_type_id')) {
            $query->where('product_type_id', $request->product_type_id);
        }

        if ($request->has('condition')) {
            $query->where('condition', $request->condition);
        }

        // Search by type name or brand name
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('productType', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('brand', function ($b) use ($search) {
                        $b->where('name', 'like', "%{$search}%");
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
            'product_type_id' => 'required|exists:product_types,id',
            'condition' => 'required|in:new,second',
            'cost_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        // Check uniqueness
        $exists = ProductPrice::where('product_type_id', $request->product_type_id)
            ->where('condition', $request->condition)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Harga untuk tipe dan kondisi ini sudah ada.'
            ], 422);
        }

        $price = ProductPrice::create($validated);

        return response()->json([
            'success' => true,
            'data' => $price->load('productType.brand'),
            'message' => 'Harga berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, ProductPrice $productPrice)
    {
        $validated = $request->validate([
            'cost_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        $productPrice->update($validated);

        return response()->json([
            'success' => true,
            'data' => $productPrice->load('productType.brand'),
            'message' => 'Harga berhasil diperbarui'
        ]);
    }

    public function destroy(ProductPrice $productPrice)
    {
        $productPrice->delete();
        return response()->json([
            'success' => true,
            'message' => 'Harga berhasil dihapus'
        ]);
    }

    // Lookup API for StockIn
    public function lookup(Request $request)
    {
        $request->validate([
            'product_type_id' => 'required|exists:product_types,id',
            'condition' => 'required|in:new,second',
        ]);

        $price = ProductPrice::where('product_type_id', $request->product_type_id)
            ->where('condition', $request->condition)
            ->first();

        if (!$price) {
            return response()->json([
                'found' => false,
                'cost_price' => 0,
                'price' => 0
            ]);
        }

        return response()->json([
            'found' => true,
            'cost_price' => $price->cost_price,
            'price' => $price->price
        ]);
    }
}
