<?php

namespace App\Http\Controllers;

use App\Models\OnlineShop;
use Illuminate\Http\Request;

class OnlineShopController extends Controller
{
    public function index(Request $request)
    {
        // FORCED ALL DATA: Same logic that worked for BranchController
        return response()->json([
            'success' => true,
            'debug_message' => 'FORCED ALL ONLINE SHOPS',
            'data' => \App\Models\OnlineShop::latest()->get()
        ]);

        // DEBUG: Log the request to server error log
        error_log("OnlineShop Request: ignore_scope=" . $request->ignore_scope . ", all=" . $request->all);

        $user = $request->user();
        $query = \App\Models\OnlineShop::query();

        // Role-based access control
        if ($request->ignore_scope || $user->hasAnyRole(['super_admin', 'owner', 'admin_produk', 'analist', 'analis'])) {
            // Full access (Global)
        } else if ($user->hasAnyRole(['audit', 'leader', 'toko_online'])) {
            // Assigned access
            $ids = $user->getAccessibleOnlineShopIds();
            $query->whereIn('id', $ids);
        } else {
            // Own assignment access
            if ($user->online_shop_id) {
                $query->where('id', $user->online_shop_id);
            } else {
                $query->whereRaw('1=0');
            }
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
            'platform' => 'required|string',
            'url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
        ]);

        $onlineShop = OnlineShop::create($validated);

        return response()->json([
            'success' => true,
            'data' => $onlineShop
        ], 201);
    }

    public function show(OnlineShop $onlineShop)
    {
        return response()->json(['success' => true, 'data' => $onlineShop]);
    }

    public function update(Request $request, OnlineShop $onlineShop)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'platform' => 'required|string',
            'url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $onlineShop->update($validated);

        return response()->json(['success' => true, 'data' => $onlineShop]);
    }

    public function destroy(OnlineShop $onlineShop)
    {
        $onlineShop->delete();
        return response()->json(['success' => true]);
    }

    // Proxy methods for external API calls (kept from previous implementation logic if needed)
    public function scan(Request $request)
    {
        // Implement scan logic communicating with defined online shop platform
        return response()->json(['message' => 'Scan logic here']);
    }

    public function updateOrder(Request $request)
    {
        return response()->json(['message' => 'Order update logic']);
    }

    public function inventory(Request $request)
    {
        return response()->json(['message' => 'Inventory logic']);
    }

    public function analysis(Request $request)
    {
        return response()->json(['message' => 'Analysis logic']);
    }
}
