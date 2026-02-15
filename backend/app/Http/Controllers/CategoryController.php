<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
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
            'type' => 'required|in:product,transaction',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'data' => $category
        ], 201);
    }

    public function show(Category $category)
    {
        return response()->json(['success' => true, 'data' => $category]);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:product,transaction',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return response()->json(['success' => true, 'data' => $category]);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(['success' => true]);
    }
}
