<?php

namespace App\Http\Controllers;

use App\Models\ReceiptSetting;
use Illuminate\Http\Request;

class ReceiptSettingController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $branchId = $request->query('branch_id') ?: $user->branch_id;
        $onlineShopId = $request->query('online_shop_id') ?: $user->online_shop_id;
        
        // Permission guard for choosing specific IDs
        $isPrivileged = $user->hasRole(['super_admin', 'owner', 'audit', 'analist']);
        if (!$isPrivileged) {
            $branchId = $user->branch_id;
            $onlineShopId = $user->online_shop_id;
        }

        if (!$branchId && !$onlineShopId) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih cabang atau toko online terlebih dahulu.',
                'data' => null
            ]);
        }

        $query = ReceiptSetting::query();
        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->where('online_shop_id', $onlineShopId);
        }

        $setting = $query->first();

        return response()->json([
            'success' => true,
            'data' => $setting
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        
        // Allow super admin/privileged to target explicit IDs
        $branchId = $request->input('branch_id') ?: $user->branch_id;
        $onlineShopId = $request->input('online_shop_id') ?: $user->online_shop_id;

        $isPrivileged = $user->hasRole(['super_admin', 'owner', 'audit', 'analist']);
        if (!$isPrivileged) {
            $branchId = $user->branch_id;
            $onlineShopId = $user->online_shop_id;
        }

        if (!$branchId && !$onlineShopId) {
            return response()->json([
                'success' => false,
                'message' => 'ID Cabang atau Toko Online tidak ditemukan.'
            ], 422);
        }

        $validated = $request->validate([
            'store_address' => 'nullable|string',
            'whatsapp_number' => 'nullable|string',
            'instagram' => 'nullable|string',
            'tiktok' => 'nullable|string',
            'warranty_terms' => 'nullable|string',
        ]);

        $matchAttributes = [];
        if ($branchId) {
            $matchAttributes['branch_id'] = $branchId;
        } else {
            $matchAttributes['online_shop_id'] = $onlineShopId;
        }

        $setting = ReceiptSetting::updateOrCreate(
            $matchAttributes,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Setting nota berhasil diperbarui!',
            'data' => $setting
        ]);
    }
}
