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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImeiCorrectionController extends Controller
{
    /**
     * Search device by IMEI and retrieve current specs & past corrections.
     */
    public function search(Request $request)
    {
        try {
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

            $correctionHistories = [];
            if (Schema::hasTable('imei_correction_histories')) {
                try {
                    $correctionHistories = ImeiCorrectionHistory::where('imei', $query)
                        ->with(['creator', 'oldProduct', 'newProduct'])
                        ->latest()
                        ->get();
                } catch (\Exception $eHist) {
                    Log::warning("Could not load imei histories: " . $eHist->getMessage());
                }
            }

            $brands = [];
            try {
                $brands = Brand::orderBy('name')->get(['id', 'name']);
            } catch (\Exception $eBrand) {
                Log::warning("Could not load brands: " . $eBrand->getMessage());
            }

            $brandId = $productDetail->product?->brand_id;
            $brandName = $productDetail->product?->brand ?? $productDetail->product?->brandRelation?->name ?? '-';
            $productName = $productDetail->product?->name ?? '';

            // Robust auto-resolve brand_id if missing or unmapped
            if (count($brands) > 0) {
                $cleanStr = fn($s) => trim(preg_replace('/[^a-zA-Z0-9]/', '', strtolower($s ?? '')));
                $searchClean = $cleanStr($brandName);
                $prodClean = $cleanStr($productName);

                if ($brandId) {
                    $found = collect($brands)->firstWhere('id', $brandId);
                    if ($found) {
                        $brandName = $found->name;
                    } else {
                        $brandId = null;
                    }
                }

                if (!$brandId && $searchClean && $searchClean !== '-') {
                    $matched = collect($brands)->first(function ($b) use ($searchClean, $cleanStr) {
                        $bClean = $cleanStr($b->name);
                        return $bClean === $searchClean || str_contains($bClean, $searchClean) || str_contains($searchClean, $bClean);
                    });

                    // iPhone / Apple alias matching
                    if (!$matched && (str_contains($searchClean, 'iphone') || str_contains($searchClean, 'apple') || str_contains($searchClean, 'ibox'))) {
                        $matched = collect($brands)->first(function ($b) use ($cleanStr) {
                            $bClean = $cleanStr($b->name);
                            return str_contains($bClean, 'iphone') || str_contains($bClean, 'apple');
                        });
                    }

                    if ($matched) {
                        $brandId = $matched->id;
                        $brandName = $matched->name;
                    }
                }

                // Fallback by productName (e.g. "IP 14", "iPhone 13", "13")
                if (!$brandId && $prodClean) {
                    if (str_starts_with($prodClean, 'ip') || str_contains($prodClean, 'iphone') || str_contains($prodClean, 'apple')) {
                        $matched = collect($brands)->first(function ($b) use ($cleanStr) {
                            $bClean = $cleanStr($b->name);
                            return str_contains($bClean, 'iphone') || str_contains($bClean, 'apple');
                        });
                        if ($matched) {
                            $brandId = $matched->id;
                            $brandName = $matched->name;
                        }
                    }
                }
            }

            $storagePresets = ['16GB', '32GB', '64GB', '128GB', '256GB', '512GB', '1TB'];

            return response()->json([
                'found' => true,
                'data' => [
                    'detail_id' => $productDetail->id,
                    'imei' => $productDetail->imei,
                    'product_id' => $productDetail->product_id,
                    'product_name' => $productDetail->product?->name ?? 'Unknown',
                    'brand_id' => $brandId,
                    'brand_name' => $brandName,
                    'storage' => $productDetail->storage ?? '-',
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
        } catch (\Exception $e) {
            Log::error("Error in ImeiCorrection search: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of product models/types for a brand.
     */
    public function getTypes(Request $request)
    {
        try {
            $brandId = $request->brand_id;
            $brandName = $request->brand_name;

            // Resolve brandId by brandName if not provided
            if (!$brandId && $brandName && $brandName !== '-') {
                $cleanStr = fn($s) => trim(preg_replace('/[^a-zA-Z0-9]/', '', strtolower($s ?? '')));
                $cleanB = $cleanStr($brandName);
                $allB = Brand::all();
                $b = $allB->first(function ($item) use ($cleanB, $cleanStr) {
                    $c = $cleanStr($item->name);
                    return $c === $cleanB || str_contains($c, $cleanB) || str_contains($cleanB, $c);
                });
                if (!$b && (str_contains($cleanB, 'iphone') || str_contains($cleanB, 'apple') || str_contains($cleanB, 'ibox'))) {
                    $b = $allB->first(function ($item) use ($cleanStr) {
                        $c = $cleanStr($item->name);
                        return str_contains($c, 'iphone') || str_contains($c, 'apple');
                    });
                }
                $brandId = $b?->id;
            }

            $types = collect();
            $products = collect();

            if ($brandId || $brandName) {
                // 1. Fetch from ProductType
                if (class_exists(ProductType::class) && Schema::hasTable('product_types')) {
                    $query = ProductType::query();
                    if ($brandId && Schema::hasColumn('product_types', 'brand_id')) {
                        $query->where('brand_id', $brandId);
                    }
                    
                    $hasPrices = class_exists(\App\Models\ProductPrice::class) && Schema::hasTable('product_prices');

                    $types = $query->orderBy('name')->get()->map(function ($t) use ($hasPrices) {
                        $capacities = collect();

                        // Capacities from ProductPrice
                        if ($hasPrices) {
                            $pCaps = \App\Models\ProductPrice::where('product_type_id', $t->id)
                                ->pluck('storage')
                                ->filter()
                                ->map(fn($s) => trim($s));
                            $capacities = $capacities->concat($pCaps);
                        }

                        // Capacities from ProductType storage field
                        if (!empty($t->storage)) {
                            $parts = collect(preg_split('/[,|\/]/', $t->storage))
                                ->map(fn($s) => trim($s))
                                ->filter();
                            $capacities = $capacities->concat($parts);
                        }

                        // Fallback from ProductDetail with same name
                        if ($capacities->isEmpty()) {
                            $prodIds = Product::where('name', 'ilike', $t->name)->pluck('id');
                            if ($prodIds->isNotEmpty()) {
                                $dCaps = \App\Models\ProductDetail::whereIn('product_id', $prodIds)
                                    ->whereNotNull('storage')
                                    ->where('storage', '!=', '-')
                                    ->distinct()
                                    ->pluck('storage')
                                    ->map(fn($s) => trim($s))
                                    ->filter();
                                $capacities = $capacities->concat($dCaps);
                            }
                        }

                        $cleanCapacities = $capacities->filter(fn($c) => !empty($c) && $c !== '-')->unique()->values()->all();

                        // Natural sort capacities: 16GB < 32GB < 64GB < 128GB < 256GB < 512GB < 1TB
                        usort($cleanCapacities, function ($a, $b) {
                            $numA = (int) preg_replace('/\D/', '', $a);
                            if (stripos($a, 'TB') !== false) $numA *= 1024;
                            $numB = (int) preg_replace('/\D/', '', $b);
                            if (stripos($b, 'TB') !== false) $numB *= 1024;
                            return $numA <=> $numB;
                        });

                        return [
                            'id' => $t->id,
                            'name' => $t->name,
                            'brand_id' => $t->brand_id,
                            'category' => $t->category,
                            'capacities' => $cleanCapacities,
                        ];
                    });
                }

                // 2. Fetch from Product (for units created directly under products)
                if (class_exists(Product::class) && Schema::hasTable('products')) {
                    $productQuery = Product::where('type', 'hp');
                    if (Schema::hasColumn('products', 'has_imei')) {
                        $productQuery->where('has_imei', true);
                    }
                    if ($brandId && Schema::hasColumn('products', 'brand_id')) {
                        $productQuery->where('brand_id', $brandId);
                    } elseif ($brandName && Schema::hasColumn('products', 'brand')) {
                        $productQuery->where('brand', 'ilike', '%' . $brandName . '%');
                    }
                    $products = $productQuery->orderBy('name')->limit(300)->get(['id', 'name', 'brand', 'brand_id'])->map(function ($p) {
                        $caps = \App\Models\ProductDetail::where('product_id', $p->id)
                            ->whereNotNull('storage')
                            ->where('storage', '!=', '-')
                            ->distinct()
                            ->pluck('storage')
                            ->map(fn($s) => trim($s))
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();

                        usort($caps, function ($a, $b) {
                            $numA = (int) preg_replace('/\D/', '', $a);
                            if (stripos($a, 'TB') !== false) $numA *= 1024;
                            $numB = (int) preg_replace('/\D/', '', $b);
                            if (stripos($b, 'TB') !== false) $numB *= 1024;
                            return $numA <=> $numB;
                        });

                        return [
                            'id' => $p->id,
                            'name' => $p->name,
                            'brand' => $p->brand,
                            'brand_id' => $p->brand_id,
                            'capacities' => $caps,
                        ];
                    });
                }
            }

            return response()->json([
                'types' => $types,
                'products' => $products
            ]);
        } catch (\Exception $e) {
            Log::error("Error in ImeiCorrection getTypes: " . $e->getMessage());
            return response()->json([
                'types' => [],
                'products' => []
            ]);
        }
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

        if (!Schema::hasTable('imei_correction_histories')) {
            return response()->json([
                'message' => 'Tabel database imei_correction_histories belum dibuat. Silakan jalankan "php artisan migrate" di server.'
            ], 422);
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
            // Brand is strictly locked to unit's original brand to preserve distributor data integrity
            $brandId = $oldBrandId ?: ($validated['brand_id'] ?? null);
            $brandName = $oldBrandName ?: ($validated['brand_name'] ?? null);
            $cleanStr = fn($s) => trim(preg_replace('/[^a-zA-Z0-9]/', '', strtolower($s ?? '')));

            if ($brandId && !$brandName) {
                $b = Brand::find($brandId);
                $brandName = $b?->name;
            } elseif ($brandName && !$brandId) {
                $cleanB = $cleanStr($brandName);
                $allB = Brand::all();
                $b = $allB->first(function ($item) use ($cleanB, $cleanStr) {
                    $c = $cleanStr($item->name);
                    return $c === $cleanB || str_contains($c, $cleanB) || str_contains($cleanB, $c);
                });
                if (!$b && (str_contains($cleanB, 'iphone') || str_contains($cleanB, 'apple') || str_contains($cleanB, 'ibox'))) {
                    $b = $allB->first(function ($item) use ($cleanStr) {
                        $c = $cleanStr($item->name);
                        return str_contains($c, 'iphone') || str_contains($c, 'apple');
                    });
                }
                $brandId = $b?->id;
                if ($b) $brandName = $b->name;
            }

            // Resolve NEW Product
            $targetProduct = null;
            if (!empty($validated['product_id'])) {
                $candidate = Product::find($validated['product_id']);
                // Verify candidate belongs to the same locked brand
                if ($candidate) {
                    if ($brandId && Schema::hasColumn('products', 'brand_id') && $candidate->brand_id && $candidate->brand_id !== $brandId) {
                        $targetProduct = null;
                    } else {
                        $targetProduct = $candidate;
                    }
                }
            }

            $cleanTypeName = trim($validated['type_name']);
            if (!$targetProduct) {
                $queryP = Product::where('type', 'hp')->where('name', 'ilike', $cleanTypeName);
                if ($brandId && Schema::hasColumn('products', 'brand_id')) {
                    $queryP->where('brand_id', $brandId);
                }
                $targetProduct = $queryP->first();
            }

            // Create new Product if it doesn't exist yet
            if (!$targetProduct) {
                $productParams = [
                    'name' => $cleanTypeName,
                    'brand' => $brandName ?: 'Unknown',
                    'type' => 'hp',
                    'has_imei' => true,
                    'sku' => 'HP-' . strtoupper(Str::random(8)),
                    'category' => 'HP',
                    'price' => $productDetail->selling_price ?? 0,
                    'is_active' => true,
                ];
                if (Schema::hasColumn('products', 'brand_id')) {
                    $productParams['brand_id'] = $brandId;
                }
                $targetProduct = Product::create($productParams);
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

            // Clean up any stray InventoryLog from previous corrections so they never show up as fake stock_in
            try {
                InventoryLog::where('description', 'like', '%Koreksi Tipe HP%')->delete();
            } catch (\Exception $eClean) {
                Log::warning("Could not delete stray correction logs: " . $eClean->getMessage());
            }

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
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if (!$user || !$user->hasRole('super_admin')) {
                return response()->json(['message' => 'Akses ditolak.'], 403);
            }

            if (!Schema::hasTable('imei_correction_histories')) {
                return response()->json([
                    'current_page' => 1,
                    'data' => [],
                    'total' => 0,
                    'message' => 'Tabel riwayat belum dimigrasi. Silakan jalankan "php artisan migrate".'
                ]);
            }

            $search = trim($request->q ?? '');
            $query = ImeiCorrectionHistory::with(['creator', 'oldProduct', 'newProduct'])->latest();

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
        } catch (\Exception $e) {
            Log::error("Error in ImeiCorrection history: " . $e->getMessage());
            return response()->json([
                'current_page' => 1,
                'data' => [],
                'total' => 0,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}
