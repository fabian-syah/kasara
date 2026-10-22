<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\ImeiCorrectionHistory;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\ProductType;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\OnlineShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImeiCorrectionController extends Controller
{
    /**
     * Search device by IMEI and retrieve current specs & past corrections.
     */
    public function search(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Akses ditolak. Fitur ini khusus Super Admin.'], 403);
        }

        $query = trim($request->imei ?? $request->q ?? '');
        if (strlen($query) < 3) {
            return response()->json(['message' => 'Masukkan minimal 3 karakter IMEI'], 422);
        }

        $productDetail = ProductDetail::withTrashed()
            ->with(['product.brandRelation', 'user', 'distributor'])
            ->where('imei', $query)
            ->latest('id')
            ->first();

        if (!$productDetail) {
            return response()->json([
                'found' => false,
                'message' => "Data IMEI {$query} tidak ditemukan di database unit."
            ], 404);
        }

        $placementName = match ($productDetail->placement_type) {
            'branch' => Branch::find($productDetail->placement_id)?->name ?? 'Cabang #' . $productDetail->placement_id,
            'warehouse' => Warehouse::find($productDetail->placement_id)?->name ?? 'Gudang #' . $productDetail->placement_id,
            'online_shop' => OnlineShop::find($productDetail->placement_id)?->name ?? 'Toko Online #' . $productDetail->placement_id,
            default => '-'
        };

        $correctionHistories = ImeiCorrectionHistory::where('imei', $query)
            ->with(['creator', 'oldProduct', 'newProduct', 'oldBrand', 'newBrand'])
            ->latest()
            ->get();

        $brands = Brand::orderBy('name')->get(['id', 'name']);
        $storagePresets = ['16GB', '32GB', '64GB', '128GB', '256GB', '512GB', '1TB'];

        return response()->json([
            'found' => true,
            'data' => [
                'detail_id' => $productDetail->id,
                'imei' => $productDetail->imei,
                'product_id' => $productDetail->product_id,
                'product_name' => $productDetail->product?->name ?? 'Unknown',
                'brand_id' => $productDetail->product?->brand_id,
                'brand_name' => $productDetail->product?->brand ?? $productDetail->product?->brandRelation?->name ?? '-',
                'storage' => $productDetail->storage ?? '-',
                'ram' => $productDetail->ram ?? '-',
                'color' => $productDetail->color ?? '-',
                'condition' => $productDetail->condition ?? 'second',
                'status' => $productDetail->status ?? 'available',
                'is_deleted' => $productDetail->trashed(),
                'placement_type' => $productDetail->placement_type,
                'placement_id' => $productDetail->placement_id,
                'placement_name' => $placementName,
                'selling_price' => $productDetail->selling_price,
                'distributor_name' => $productDetail->distributor?->name ?? $productDetail->supplier_name ?? '-',
                'input_by' => $productDetail->user?->name ?? '-',
                'registered_at' => $productDetail->created_at?->toDateTimeString(),
            ],
            'histories' => $correctionHistories,
            'brands' => $brands,
            'storage_presets' => $storagePresets,
        ]);
    }

    /**
     * Get list of product models/types for a brand.
     */
    public function getTypes(Request $request)
    {
        $brandId = $request->brand_id;
        $query = ProductType::query();

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        $types = $query->orderBy('name')->get(['id', 'brand_id', 'name', 'ram', 'storage']);

        // Also fetch known Products of type hp for this brand
        $productQuery = Product::where('type', 'hp')->where('has_imei', true);
        if ($brandId) {
            $productQuery->where('brand_id', $brandId);
        }
        $products = $productQuery->orderBy('name')->get(['id', 'name', 'brand', 'brand_id']);

        return response()->json([
            'types' => $types,
            'products' => $products
        ]);
    }

    /**
     * Execute IMEI specification correction.
     */
    public function correct(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Akses ditolak. Fitur ini khusus Super Admin.'], 403);
        }

        $validated = $request->validate([
            'imei' => 'required|string',
            'cs_name_mistake' => 'required|string|min:2',
            'type_name' => 'required|string|min:2',
            'brand_id' => 'nullable|integer',
            'brand_name' => 'nullable|string',
            'product_id' => 'nullable|integer',
            'storage' => 'required|string',
            'color' => 'nullable|string',
            'ram' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
        ], [
            'cs_name_mistake.required' => 'Nama CS yang salah input wajib diisi.',
            'cs_name_mistake.min' => 'Nama CS yang salah input minimal 2 karakter.',
            'type_name.required' => 'Tipe HP tujuan wajib diisi.',
            'storage.required' => 'Storage (GB) wajib dipilih atau diisi.',
        ]);

        $imei = trim($validated['imei']);
        $productDetail = ProductDetail::withTrashed()
            ->with(['product.brandRelation'])
            ->where('imei', $imei)
            ->latest('id')
            ->first();

        if (!$productDetail) {
            return response()->json(['message' => "Unit dengan IMEI {$imei} tidak ditemukan."], 404);
        }

        DB::beginTransaction();
        try {
            // Snapshot OLD data
            $oldProductId = $productDetail->product_id;
            $oldProductName = $productDetail->product?->name ?? 'Unknown';
            $oldBrandName = $productDetail->product?->brand ?? $productDetail->product?->brandRelation?->name ?? null;
            $oldBrandId = $productDetail->product?->brand_id;
            $oldStorage = $productDetail->storage;
            $oldColor = $productDetail->color;
            $oldRam = $productDetail->ram;

            // Resolve target Brand
            $brandId = $validated['brand_id'] ?? null;
            $brandName = $validated['brand_name'] ?? null;
            if ($brandId && !$brandName) {
                $b = Brand::find($brandId);
                $brandName = $b?->name;
            } elseif ($brandName && !$brandId) {
                $b = Brand::where('name', 'ilike', trim($brandName))->first();
                $brandId = $b?->id;
            }

            // Resolve NEW Product
            $targetProduct = null;
            if (!empty($validated['product_id'])) {
                $targetProduct = Product::find($validated['product_id']);
            }

            $cleanTypeName = trim($validated['type_name']);
            if (!$targetProduct) {
                // Find existing Product with matching name and brand
                $queryP = Product::where('type', 'hp')->where('name', 'ilike', $cleanTypeName);
                if ($brandId) {
                    $queryP->where('brand_id', $brandId);
                }
                $targetProduct = $queryP->first();
            }

            // Create new Product if it doesn't exist yet
            if (!$targetProduct) {
                $targetProduct = Product::create([
                    'name' => $cleanTypeName,
                    'brand' => $brandName ?: 'Unknown',
                    'brand_id' => $brandId,
                    'type' => 'hp',
                    'has_imei' => true,
                    'sku' => 'HP-' . strtoupper(Str::random(8)),
                    'category' => 'HP',
                    'price' => $productDetail->selling_price ?? 0,
                    'is_active' => true,
                ]);
            }

            // Update ProductDetail
            $newStorage = trim($validated['storage']);
            $newColor = isset($validated['color']) ? trim($validated['color']) : $productDetail->color;
            $newRam = isset($validated['ram']) ? trim($validated['ram']) : $productDetail->ram;

            $productDetail->product_id = $targetProduct->id;
            $productDetail->storage = $newStorage;
            $productDetail->color = $newColor;
            $productDetail->ram = $newRam;
            $productDetail->save();

            // Record in ImeiCorrectionHistory
            $history = ImeiCorrectionHistory::create([
                'product_detail_id' => $productDetail->id,
                'imei' => $imei,
                'old_product_id' => $oldProductId,
                'old_product_name' => $oldProductName,
                'old_brand' => $oldBrandName,
                'old_brand_id' => $oldBrandId,
                'old_storage' => $oldStorage,
                'old_color' => $oldColor,
                'old_ram' => $oldRam,
                'new_product_id' => $targetProduct->id,
                'new_product_name' => $targetProduct->name,
                'new_brand' => $targetProduct->brand ?: $brandName,
                'new_brand_id' => $targetProduct->brand_id ?: $brandId,
                'new_storage' => $newStorage,
                'new_color' => $newColor,
                'new_ram' => $newRam,
                'cs_name_mistake' => trim($validated['cs_name_mistake']),
                'notes' => $validated['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            // Record in InventoryLog for audit trail
            $csMistake = trim($validated['cs_name_mistake']);
            InventoryLog::create([
                'product_id' => $targetProduct->id,
                'branch_id' => $productDetail->placement_type === 'branch' ? $productDetail->placement_id : null,
                'warehouse_id' => $productDetail->placement_type === 'warehouse' ? $productDetail->placement_id : null,
                'online_shop_id' => $productDetail->placement_type === 'online_shop' ? $productDetail->placement_id : null,
                'user_id' => $user->id,
                'distributor_id' => $productDetail->distributor_id,
                'supplier_name' => $productDetail->supplier_name,
                'type' => 'in',
                'quantity' => 0,
                'description' => "Koreksi Tipe HP oleh Super Admin: {$oldProductName} ({$oldStorage}) -> {$targetProduct->name} ({$newStorage}) [CS Salah: {$csMistake}]",
                'reference_id' => (string) $productDetail->id,
                'notes' => $validated['notes'] ?? "Koreksi spesifikasi unit IMEI {$imei}. CS salah input: {$csMistake}",
            ]);

            DB::commit();

            Cache::increment('inv_version');

            return response()->json([
                'success' => true,
                'message' => "Berhasil mengoreksi spesifikasi IMEI {$imei} menjadi {$targetProduct->name} ({$newStorage}).",
                'history' => $history->load(['creator', 'oldProduct', 'newProduct'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to correct IMEI specification: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Gagal menyimpan perubahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get global list of all IMEI corrections for audit overview.
     */
    public function history(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $search = trim($request->q ?? '');
        $query = ImeiCorrectionHistory::with(['creator', 'oldProduct', 'newProduct', 'oldBrand', 'newBrand'])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('imei', 'like', "%{$search}%")
                    ->orWhere('cs_name_mistake', 'ilike', "%{$search}%")
                    ->orWhere('old_product_name', 'ilike', "%{$search}%")
                    ->orWhere('new_product_name', 'ilike', "%{$search}%");
            });
        }

        $histories = $query->paginate(20);

        return response()->json($histories);
    }
}
