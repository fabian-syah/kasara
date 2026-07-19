<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\FailedStockInput;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\StockOut;
use App\Models\StockOutNonHpItem;
use App\Models\User;
use App\Traits\VerifiesPin;
use App\Utils\SimpleXLSXGen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StockInController extends Controller
{
    use VerifiesPin;

    public function stockIn(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required_if:type,hp|nullable|exists:products,id',
                'distributor_id' => 'nullable|exists:distributors,id',
                'new_distributor_name' => 'nullable|string|max:255',
                'type' => 'required|in:hp,non-hp,HP,NON-HP',
                'transaction_pin' => 'nullable|string|min:4|max:6',
                'placement_type' => 'required|in:branch,warehouse,online_shop,distributor',
                'placement_id' => 'required|integer',
                'inventory_user_id' => 'nullable|integer|exists:users,id',
                'items' => 'required_if:type,non-hp|array',
                'items.*.brand_name' => 'nullable|string',
                'items.*.brand_id' => 'nullable|integer|exists:brands,id',
                'items.*.type_name' => 'required_with:items|string',
                'items.*.quantity' => 'required_with:items|integer|min:1',
                'items.*.cost_price' => 'nullable|numeric|min:0',
                'items.*.selling_price' => 'nullable|numeric|min:0',
                'items.*.notes' => 'nullable|string|max:5000',
                'quantity' => 'required_without:items|nullable|integer|min:1',
                'imeis' => 'required_if:type,hp|array',
                'imeis.*.imei' => ['required_if:type,hp', 'string', 'distinct', 'max:40', 'regex:/^[a-zA-Z0-9]+$/'],
                'imeis.*.ram' => 'nullable|string',
                'imeis.*.storage' => 'nullable|string',
                'storage' => 'nullable|string',
                'imeis.*.condition' => 'required_if:type,hp|in:new,second,ex_ibox',
                'imeis.*.cost_price' => 'nullable|numeric|min:0',
                'imeis.*.selling_price' => 'nullable|numeric|min:0',
                'imeis.*.notes' => 'nullable|string|max:5000',
                'notes' => 'nullable|string|max:5000',
                'category' => 'nullable|string|in:pembelian,retur_customer,pindah_cabang,salah_input,cancel_penjualan',
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            // Log validation failure
            try {
                $product = $request->product_id ? Product::find($request->product_id) : null;
                $imei = null;
                if ($request->type === 'hp' && $request->imeis && count($request->imeis) > 0) {
                    $imei = $request->imeis[0]['imei'] ?? null;
                }
                FailedStockInput::create([
                    'user_id' => Auth::id(),
                    'type' => strtolower($request->type ?? 'hp'),
                    'product_name' => $product?->name ?? $request->type_name ?? null,
                    'product_id' => $request->product_id,
                    'imei' => $imei,
                    'placement_type' => $request->placement_type,
                    'placement_id' => $request->placement_id,
                    'quantity' => $request->quantity ?? ($request->imeis ? count($request->imeis) : null),
                    'error_message' => collect($ve->errors())->flatten()->first() ?? 'Validation failed',
                    'error_type' => 'validation',
                ]);
            } catch (\Exception $logErr) {
                Log::error("Failed to log validation error: " . $logErr->getMessage());
            }
            throw $ve;
        }

        $request->merge(['type' => strtolower($request->type)]);
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $ownerUserId = $user->id;
        if ($request->has('inventory_user_id') && $request->inventory_user_id) {
            $ownerUserId = $request->inventory_user_id;
        }

        $targetUser = User::find($ownerUserId);

        $pinError = $this->verifyPin($request, $ownerUserId);
        if ($pinError)
            return $pinError;

        DB::beginTransaction();

        try {
            $distributorId = $request->distributor_id;
            $supplierName = null;

            if (!$distributorId && $request->new_distributor_name) {
                $supplierName = $request->new_distributor_name;
                $matchingDist = \App\Models\Distributor::where('name', 'ilike', trim($supplierName))->first();
                if ($matchingDist) {
                    $distributorId = $matchingDist->id;
                } else {
                    $distributorId = null;
                }
            }

            if (!$distributorId && !$supplierName) {
                throw new \Exception("Distributor harus dipilih atau diisi manual.");
            }
            $product = null;
            if ($request->product_id) {
                $product = Product::find($request->product_id);
            }

            if (!$product && strtolower($request->type) === 'hp') {
                return response()->json(['message' => 'Produk tidak ditemukan. Pastikan nama Tipe sesuai.'], 404);
            }

            // 1. Handle Non-HP (Quantity Based)
            if ($request->type === 'non-hp') {
                $items = $request->items ?? [
                    [
                        'product_id' => $request->product_id,
                        'quantity' => $request->quantity,
                        'selling_price' => $request->selling_price,
                        'brand_id' => $request->brand_id,
                        'brand_name' => $request->brand_name,
                        'type_name' => $request->type_name,
                    ]
                ];

                $results = [];

                foreach ($items as $item) {
                    $pId = $item['product_id'] ?? null;

                    if (!$pId && !empty($item['type_name'])) {
                        $brandName = $item['brand_name'] ?? 'Unknown';
                        $typeName = $item['type_name'];

                        $foundType = \App\Models\ProductType::where('name', $typeName)
                            ->where('brand_id', $item['brand_id'])
                            ->first();
                        $nonImeiCat = $foundType ? $foundType->non_imei_category : null;

                        $productParams = [
                            'name' => $typeName,
                            'brand' => $brandName,
                            'type' => 'non-hp'
                        ];
                        if (Schema::hasColumn('products', 'non_imei_category')) {
                            $productParams['non_imei_category'] = $nonImeiCat;
                        }

                        $prod = Product::firstOrCreate(
                            $productParams,
                            [
                                'sku' => 'NHP-' . strtoupper(Str::random(8)),
                                'category' => 'NON HP / NON IMEI',
                                'has_imei' => false,
                                'price' => $item['selling_price'] ?? 0,
                                'brand_id' => $item['brand_id'] ?? null
                            ]
                        );

                        if ($prod->wasRecentlyCreated === false && $nonImeiCat) {
                            if (Schema::hasColumn('products', 'non_imei_category') && is_null($prod->non_imei_category)) {
                                $prod->update(['non_imei_category' => $nonImeiCat]);
                            }
                        }
                        $pId = $prod->id;
                    }

                    if (!$pId)
                        continue;

                    $distributorId = $item['distributor_id'] ?? $request->distributor_id;
                    $sellingPrice = floatval($item['selling_price'] ?? 0);
                    $costPrice = floatval($item['cost_price'] ?? $sellingPrice);

                    $itemNote = $item['notes'] ?? $request->notes;

                    $inventory = Inventory::firstOrCreate(
                        [
                            'product_id' => $pId,
                            'placement_type' => $request->placement_type,
                            'placement_id' => $request->placement_id,
                            'distributor_id' => $distributorId,
                            'cost_price' => $costPrice,
                            'user_id' => $ownerUserId
                        ],
                        [
                            'quantity' => 0,
                            'selling_price' => $sellingPrice,
                            'notes' => $itemNote
                        ]
                    );

                    if ($sellingPrice > 0 || $itemNote) {
                        if ($sellingPrice > 0) {
                            $inventory->selling_price = $sellingPrice;
                        }
                        if ($itemNote) {
                            $inventory->notes = $itemNote;
                        }
                        $inventory->save();
                    }

                    $quantity = $item['quantity'] ?? 1;
                    $inventory->increment('quantity', $quantity);

                    $log = InventoryLog::create([
                        'product_id' => $pId,
                        'branch_id' => $request->placement_type === 'branch' ? $request->placement_id : null,
                        'warehouse_id' => $request->placement_type === 'warehouse' ? $request->placement_id : null,
                        'online_shop_id' => $request->placement_type === 'online_shop' ? $request->placement_id : null,
                        'user_id' => $ownerUserId,
                        'distributor_id' => $distributorId,
                        'supplier_name' => $supplierName,
                        'type' => 'in',
                        'quantity' => $quantity,
                        'balance_after' => $inventory->quantity,
                        'description' => "Stock In Batch from " . ($supplierName ?: "Distributor"),
                        'reference_id' => 'STOCK-IN-NHP-' . time() . '-' . $pId,
                        'notes' => $itemNote,
                    ]);

                    $stockOutAudit = StockOut::create([
                        'receipt_id' => 'IN-NHP-' . strtoupper(Str::random(6)),
                        'category' => $request->category ?? 'barang_masuk',
                        'user_id' => Auth::id(),
                        'inventory_user_id' => $ownerUserId,
                        'branch_id' => $request->placement_type === 'branch' ? $request->placement_id : null,
                        'warehouse_id' => $request->placement_type === 'warehouse' ? $request->placement_id : null,
                        'online_shop_id' => $request->placement_type === 'online_shop' ? $request->placement_id : null,
                        'status' => 'received',
                        'notes' => $itemNote,
                    ]);

                    StockOutNonHpItem::create([
                        'stock_out_id' => $stockOutAudit->id,
                        'product_id' => $pId,
                        'quantity' => $quantity,
                        'received_quantity' => $quantity,
                        'selling_price' => $item['selling_price'] ?? 0,
                    ]);

                    $results[] = $inventory->load(['product', 'user']);
                }

                DB::commit();

                foreach ($results as $inv) {
                    try {
                        event(new \App\Events\StockInEvent($inv));
                    } catch (\Exception $e) {
                        Log::error("Event fail: " . $e->getMessage());
                    }
                }
                
                \Illuminate\Support\Facades\Cache::increment('inv_version');

                return response()->json(['message' => 'Multiple stock in successful', 'count' => count($results)], 201);
            }

            // 2. Handle HP (IMEI Based)
            else {
                $details = $request->imeis ?? $validated['details'] ?? [];

                $inserted_count = 0;
                $duplicates = [];
                $newDetails = [];

                foreach ($details as $item) {
                    // Fetch all matching records, prioritize active ones first
                    $existingRecords = ProductDetail::withTrashed()->where('imei', $item['imei'])->get();
                    $existing = $existingRecords->filter(fn($q) => !$q->trashed())->first() 
                                ?? $existingRecords->first();

                    if ($existing) {
                        $activeStatuses = ['available', 'in_transit', 'booking', 'process', 'service', 'transfer'];
                        if (in_array($existing->status, $activeStatuses) && !$existing->trashed()) {
                            // Get location info of existing item
                            $existingLocation = '';
                            if ($existing->placement_type === 'branch') {
                                $existingLocation = \App\Models\Branch::find($existing->placement_id)?->name ?? 'Unknown';
                            } elseif ($existing->placement_type === 'warehouse') {
                                $existingLocation = \App\Models\Warehouse::find($existing->placement_id)?->name ?? 'Unknown';
                            } elseif ($existing->placement_type === 'online_shop') {
                                $existingLocation = \App\Models\OnlineShop::find($existing->placement_id)?->name ?? 'Unknown';
                            }
                            $duplicates[] = [
                                'imei' => $item['imei'],
                                'location' => $existingLocation,
                                'placement_type' => $existing->placement_type,
                            ];
                            continue;
                        }

                        if ($existing->trashed()) {
                            $existing->restore();
                        }

                        $existing->fill([
                            'product_id' => $product->id,
                            'ram' => $request->ram ?? $existing->ram,
                            'storage' => $request->storage ?? $existing->storage,
                            'condition' => $item['condition'],
                            'status' => 'available',
                            'placement_type' => $request->placement_type,
                            'placement_id' => $request->placement_id,
                            'cost_price' => $item['cost_price'] ?? null,
                            'selling_price' => $item['selling_price'] ?? null,
                            'distributor_id' => $distributorId,
                            'supplier_name' => $supplierName,
                            'user_id' => $ownerUserId,
                            'notes' => $item['notes'] ?? $request->notes,
                        ]);

                        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
                        $existing->created_at = $logicalNow;
                        $existing->updated_at = $logicalNow;
                        $existing->save();

                        $newDetails[] = $existing;
                        $inserted_count++;

                        InventoryLog::create([
                            'product_id' => $product->id,
                            'branch_id' => $request->placement_type === 'branch' ? $request->placement_id : null,
                            'warehouse_id' => $request->placement_type === 'warehouse' ? $request->placement_id : null,
                            'online_shop_id' => $request->placement_type === 'online_shop' ? $request->placement_id : null,
                            'user_id' => $ownerUserId,
                            'distributor_id' => $distributorId,
                            'supplier_name' => $supplierName,
                            'type' => 'in',
                            'quantity' => 1,
                            'balance_after' => ProductDetail::where('product_id', $product->id)->where('status', 'available')->count(),
                            'description' => "Stock In: {$product->name} ({$existing->imei}) dari " . ($supplierName ?: "Distributor") . " (Restored)",
                            'reference_id' => (string)$existing->id,
                            'notes' => $item['notes'] ?? $request->notes,
                        ]);
                        continue;
                    }

                    $itemNote = $item['notes'] ?? $request->notes;
                    $detail = ProductDetail::create([
                        'product_id' => $product->id,
                        'imei' => $item['imei'],
                        'ram' => $request->ram ?? null,
                        'storage' => $request->storage ?? null,
                        'condition' => $item['condition'],
                        'status' => 'available',
                        'placement_type' => $request->placement_type,
                        'placement_id' => $request->placement_id,
                        'cost_price' => $item['cost_price'] ?? null,
                        'selling_price' => $item['selling_price'] ?? null,
                        'distributor_id' => $distributorId,
                        'supplier_name' => $supplierName,
                        'user_id' => $ownerUserId,
                        'notes' => $itemNote,
                    ]);

                    $newDetails[] = $detail;
                    $inserted_count++;

                    InventoryLog::create([
                        'product_id' => $product->id,
                        'branch_id' => $request->placement_type === 'branch' ? $request->placement_id : null,
                        'warehouse_id' => $request->placement_type === 'warehouse' ? $request->placement_id : null,
                        'online_shop_id' => $request->placement_type === 'online_shop' ? $request->placement_id : null,
                        'user_id' => $ownerUserId,
                        'distributor_id' => $distributorId,
                        'supplier_name' => $supplierName,
                        'type' => 'in',
                        'quantity' => 1,
                        'balance_after' => ProductDetail::where('product_id', $product->id)->where('status', 'available')->count(),
                        'description' => "Stock In: {$product->name} ({$detail->imei}) dari " . ($supplierName ?: "Distributor"),
                        'reference_id' => (string)$detail->id,
                        'notes' => $itemNote,
                    ]);
                }

                if ($inserted_count > 0) {
                    $stockOutAudit = StockOut::create([
                        'receipt_id' => 'IN-HP-' . strtoupper(Str::random(6)),
                        'category' => $request->category ?? 'barang_masuk',
                        'user_id' => Auth::id(),
                        'inventory_user_id' => $ownerUserId,
                        'status' => 'received',
                        'notes' => $request->notes,
                    ]);

                    $stockOutAudit->items()->attach(collect($newDetails)->pluck('id'));
                }

                if (count($request->imeis) > 0 && isset($request->imeis[0]['selling_price'])) {
                    $product->update(['price' => $request->imeis[0]['selling_price']]);
                }

                DB::commit();

                foreach ($newDetails as $detail) {
                    try {
                        $detail->load(['product', 'distributor', 'user']);
                        event(new \App\Events\StockInEvent($detail));
                    } catch (\Exception $e) {
                        Log::error("Failed to broadcast StockInEvent for HP item: " . $e->getMessage());
                    }
                }

                // Log duplicates as failed inputs
                if (count($duplicates) > 0) {
                    $placementName = null;
                    if ($request->placement_type && $request->placement_id) {
                        if ($request->placement_type === 'branch') {
                            $placementName = \App\Models\Branch::find($request->placement_id)?->name;
                        } elseif ($request->placement_type === 'warehouse') {
                            $placementName = \App\Models\Warehouse::find($request->placement_id)?->name;
                        } elseif ($request->placement_type === 'online_shop') {
                            $placementName = \App\Models\OnlineShop::find($request->placement_id)?->name;
                        }
                    }

                    foreach ($duplicates as $dup) {
                        try {
                            FailedStockInput::create([
                                'user_id' => Auth::id(),
                                'type' => 'hp',
                                'product_name' => $product?->name,
                                'product_id' => $product?->id,
                                'imei' => $dup['imei'],
                                'placement_type' => $request->placement_type,
                                'placement_id' => $request->placement_id,
                                'placement_name' => $placementName,
                                'distributor_name' => $supplierName ?? null,
                                'error_message' => "IMEI sudah ada di " . ($dup['location'] ?: 'inventory'),
                                'error_type' => 'duplicate',
                            ]);
                        } catch (\Exception $logErr) {
                            Log::error("Failed to log duplicate: " . $logErr->getMessage());
                        }
                    }
                }

                return response()->json([
                    'message' => 'Stock in processed',
                    'success' => true,
                    'inserted_count' => $inserted_count,
                    'duplicates' => collect($duplicates)->map(fn($dup) => [
                        'imei' => $dup['imei'],
                        'location' => $dup['location'],
                    ])->toArray()
                ], 201);
            }

            DB::commit();

            if ($request->type === 'non-hp') {
                try {
                    event(new \App\Events\StockInEvent($inventory->load(['product', 'user'])));
                } catch (\Exception $e) {
                    Log::error("Failed to broadcast StockInEvent for Non-HP item: " . $e->getMessage());
                }
            }

            return response()->json(['message' => 'Stock in successful'], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Log failed input
            try {
                $product = $request->product_id ? Product::find($request->product_id) : null;
                $placementName = null;
                if ($request->placement_type && $request->placement_id) {
                    if ($request->placement_type === 'branch') {
                        $placementName = \App\Models\Branch::find($request->placement_id)?->name;
                    } elseif ($request->placement_type === 'warehouse') {
                        $placementName = \App\Models\Warehouse::find($request->placement_id)?->name;
                    } elseif ($request->placement_type === 'online_shop') {
                        $placementName = \App\Models\OnlineShop::find($request->placement_id)?->name;
                    }
                }

                $imei = null;
                if ($request->type === 'hp' && $request->imeis && count($request->imeis) > 0) {
                    $imei = $request->imeis[0]['imei'] ?? null;
                }

                FailedStockInput::create([
                    'user_id' => Auth::id(),
                    'type' => $request->type ?? 'hp',
                    'product_name' => $product?->name ?? $request->type_name ?? null,
                    'product_id' => $request->product_id,
                    'imei' => $imei,
                    'placement_type' => $request->placement_type,
                    'placement_id' => $request->placement_id,
                    'placement_name' => $placementName,
                    'distributor_name' => $request->new_distributor_name ?? null,
                    'condition' => $request->imeis[0]['condition'] ?? null,
                    'cost_price' => $request->imeis[0]['cost_price'] ?? null,
                    'selling_price' => $request->imeis[0]['selling_price'] ?? null,
                    'quantity' => $request->quantity ?? ($request->imeis ? count($request->imeis) : null),
                    'error_message' => $e->getMessage(),
                    'error_type' => 'exception',
                    'request_data' => [
                        'type' => $request->type,
                        'product_id' => $request->product_id,
                        'imeis' => $request->type === 'hp' ? collect($request->imeis)->pluck('imei')->toArray() : null,
                        'placement_type' => $request->placement_type,
                        'placement_id' => $request->placement_id,
                    ],
                ]);
            } catch (\Exception $logError) {
                Log::error("Failed to log failed stock input: " . $logError->getMessage());
            }

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function failedInputHistory(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = FailedStockInput::with(['user', 'product'])
            ->orderBy('created_at', 'desc');

        // Role-based filtering
        $role = strtolower($user->getRoleNames()->first() ?? '');
        $privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
        $isPrivileged = collect($privilegedRoles)->contains(fn($r) => str_contains($role, $r));

        if (!$isPrivileged) {
            $query->where('user_id', $user->id);
        }

        // Type filter
        if ($request->type) {
            $query->where('type', $request->type);
        }

        // Search
        if ($request->search) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(product_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(imei) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(error_message) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(distributor_name) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('user', function ($sq) use ($search) {
                        $sq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        // Date filter
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        } elseif ($request->month && $request->year) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
        }

        return $query->paginate(20);
    }

    public function stockInHistory(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $type = $request->type ?? 'hp';

        $query = InventoryLog::with(['product', 'user', 'distributor', 'productDetail'])
            ->where('type', 'in');

        if ($type === 'hp') {
            $query->whereHas('product', function ($q) {
                $q->where('type', 'hp');
            });
        } else {
            $query->whereHas('product', function ($q) {
                $q->where('type', 'non-hp');
            });
        }

        // SEARCH
        if ($request->search) {
            $search = $request->search;
            $keywords = explode(' ', $search);

            $query->where(function ($q) use ($keywords, $type) {
                foreach ($keywords as $keyword) {
                    $lowKeyword = strtolower($keyword);
                    $q->where(function ($sub) use ($lowKeyword, $type) {
                        $sub->whereHas('product', function ($sq) use ($lowKeyword) {
                            $sq->whereRaw('LOWER(name) LIKE ?', ["%{$lowKeyword}%"])
                                ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$lowKeyword}%"]);
                        })
                        ->orWhereRaw('LOWER(description) LIKE ?', ["%{$lowKeyword}%"]);

                        if ($type === 'hp') {
                            $matchingDetails = ProductDetail::with(['downgrade', 'tukarTambah', 'refund', 'unitExchange'])
                                ->whereRaw('LOWER(imei) LIKE ?', ["%{$lowKeyword}%"])
                                ->get();

                            if ($matchingDetails->isNotEmpty()) {
                                $possibleRefIds = [];
                                foreach ($matchingDetails as $det) {
                                    $possibleRefIds[] = (string)$det->id;
                                    if ($det->downgrade_id && $det->downgrade) {
                                        $possibleRefIds[] = 'DG IN: ' . $det->downgrade->receipt_id;
                                    }
                                    if ($det->tukar_tambah_id && $det->tukarTambah) {
                                        $possibleRefIds[] = 'TT IN: ' . $det->tukarTambah->receipt_id;
                                    }
                                    if ($det->refund_id && $det->refund) {
                                        $possibleRefIds[] = 'Refund: ' . $det->refund->receipt_id;
                                    }
                                    if ($det->unit_exchange_id && $det->unitExchange) {
                                        $possibleRefIds[] = 'Exchange IN: ' . $det->unitExchange->receipt_id;
                                    }
                                }
                                $sub->orWhereIn('reference_id', array_unique($possibleRefIds));
                            }
                        }
                    });
                }
            });
        }

        // PLACEMENT FILTER
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'analist', 'owner'];
        if (!$user->hasRole($unrestrictedRoles)) {
            $query->where(function ($q) use ($user) {
                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $onlineShopIds = $user->getAccessibleOnlineShopIds();
                $distributorIds = $user->getAccessibleDistributorIds();

                $hasC = false;
                if (!empty($branchIds)) {
                    $q->orWhereIn('branch_id', $branchIds);
                    $hasC = true;
                }
                if (!empty($warehouseIds)) {
                    $q->orWhereIn('warehouse_id', $warehouseIds);
                    $hasC = true;
                }
                if (!empty($onlineShopIds)) {
                    $q->orWhereIn('online_shop_id', $onlineShopIds);
                    $hasC = true;
                }
                if (!empty($distributorIds)) {
                    $q->orWhereIn('distributor_id', $distributorIds);
                    $hasC = true;
                }
                if ($user->distributor_id) {
                    $q->orWhere('user_id', $user->id);
                    $hasC = true;
                }
                if (!$hasC) {
                    $q->whereRaw('0 = 1');
                }
            });
        }

        // Analist Exclusion for Stock In History
        if ($user->hasRole('analist') && !$user->hasRole('super_admin')) {
            /** @var array $excludedKeywords */
            $excludedKeywords = config('kasara.excluded_keywords');
            $query->where(function ($q) use ($excludedKeywords) {
                foreach (['branch', 'online_shop', 'warehouse', 'distributor'] as $pType) {
                    $q->whereNot(function ($sq) use ($pType, $excludedKeywords) {
                        $tableName = match ($pType) {
                            'branch' => 'branches',
                            'online_shop' => 'online_shops',
                            'warehouse' => 'warehouses',
                            'distributor' => 'distributors',
                        };
                        $colName = match ($pType) {
                            'branch' => 'branch_id',
                            'online_shop' => 'online_shop_id',
                            'warehouse' => 'warehouse_id',
                            'distributor' => 'distributor_id',
                        };
                        
                        $sq->whereNotNull($colName)
                           ->whereExists(function ($exq) use ($tableName, $colName, $excludedKeywords) {
                               $exq->select(DB::raw(1))->from($tableName)->whereColumn("$tableName.id", "inventory_logs.$colName")
                                   ->where(function ($nq) use ($excludedKeywords) {
                                       foreach ($excludedKeywords as $kw) $nq->orWhere('name', 'ilike', "%$kw%");
                                   });
                           });
                    });
                }
            });
        }

        // AUDIT LOCATION FILTERS
        $auditRoles = ['audit', 'leader', 'super_admin', 'admin_produk', 'analist', 'owner'];
        if ($request->filled('branch_id') && $user->hasAnyRole($auditRoles)) {
            $query->where('branch_id', $request->branch_id);
        } elseif ($request->filled('online_shop_id') && $user->hasAnyRole($auditRoles)) {
            $query->where('online_shop_id', $request->online_shop_id);
        } elseif ($request->filled('warehouse_id') && $user->hasAnyRole($auditRoles)) {
            $query->where('warehouse_id', $request->warehouse_id);
        } elseif ($request->filled('distributor_id') && $user->hasAnyRole($auditRoles)) {
            $query->where('distributor_id', $request->distributor_id);
        }

        // DATE FILTER
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        if ($request->date) {
            $d = $request->date;
            if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
                $today = $logicalNow->toDateString();
                $sevenDaysAgo = $logicalNow->copy()->subDays(7)->toDateString();
                if ($d < $sevenDaysAgo)
                    $d = $today;
            }
            // Pakai reporting_date (shift jam 5 pagi), fallback ke created_at untuk data lama
            $query->where(function ($q) use ($d) {
                $q->where('reporting_date', $d)
                  ->orWhere(function ($sq) use ($d) {
                      // Fallback: data lama yang belum punya reporting_date
                      $sq->whereNull('reporting_date')
                         ->whereDate('created_at', $d);
                  });
            });
        } elseif ($request->month && $request->year) {
            $m = (int) $request->month;
            $y = (int) $request->year;
            $start = \Carbon\Carbon::create($y, $m, 1)->startOfMonth()->startOfDay()->toDateTimeString();
            $end = \Carbon\Carbon::create($y, $m, 1)->endOfMonth()->endOfDay()->toDateTimeString();
            // Pakai reporting_date, fallback ke created_at untuk data lama
            $query->where(function ($q) use ($start, $end) {
                $q->where(function ($sq) use ($start, $end) {
                    $sq->whereBetween('reporting_date', [$start, $end]);
                })->orWhere(function ($sq) use ($start, $end) {
                    $sq->whereNull('reporting_date')
                       ->whereBetween('created_at', [$start, $end]);
                });
            });
        }


        $paginated = $query->latest()->paginate(20);

        if ($type === 'hp') {
            $paginated->getCollection()->transform(function ($log) {
                $detail = $log->productDetail;

                if (!$detail && is_string($log->reference_id) && !is_numeric($log->reference_id)) {
                    $ref = $log->reference_id;
                    if (str_starts_with($ref, 'DG IN: ')) {
                        $rcpt = str_replace('DG IN: ', '', $ref);
                        $txn = \App\Models\Downgrade::where('receipt_id', $rcpt)->first();
                        if ($txn) $detail = ProductDetail::where('downgrade_id', $txn->id)->first();
                    } elseif (str_starts_with($ref, 'TT IN: ')) {
                        $rcpt = str_replace('TT IN: ', '', $ref);
                        $txn = \App\Models\TukarTambah::where('receipt_id', $rcpt)->first();
                        if ($txn) $detail = ProductDetail::where('tukar_tambah_id', $txn->id)->first();
                    } elseif (str_starts_with($ref, 'Refund: ')) {
                        $rcpt = str_replace('Refund: ', '', $ref);
                        $txn = \App\Models\Refund::where('receipt_id', $rcpt)->first();
                        if ($txn) $detail = ProductDetail::where('refund_id', $txn->id)->first();
                    } elseif (str_starts_with($ref, 'Exchange IN: ')) {
                        $rcpt = str_replace('Exchange IN: ', '', $ref);
                        $txn = \App\Models\UnitExchange::where('receipt_id', $rcpt)->first();
                        if ($txn) $detail = ProductDetail::where('unit_exchange_id', $txn->id)->first();
                    }
                }

                return [
                    'id' => $log->id,
                    'created_at' => $log->created_at->toDateTimeString(),
                    'product' => $log->product,
                    'imei' => $detail->imei ?? ($log->reference_id ? $log->description : '-'),
                    'ram' => $detail->ram ?? null,
                    'storage' => $detail->storage ?? null,
                    'condition' => $detail->condition ?? '-',
                    'distributor' => $log->distributor ?? ($detail->distributor ?? null),
                    'supplier_name' => $log->supplier_name ?? ($detail->supplier_name ?? null),
                    'placement_name' => match (true) {
                        !empty($log->branch_id) => \App\Models\Branch::find($log->branch_id)?->name,
                        !empty($log->warehouse_id) => \App\Models\Warehouse::find($log->warehouse_id)?->name,
                        !empty($log->online_shop_id) => \App\Models\OnlineShop::find($log->online_shop_id)?->name,
                        default => $detail ? match ($detail->placement_type) {
                            'branch' => \App\Models\Branch::find($detail->placement_id)?->name,
                            'warehouse' => \App\Models\Warehouse::find($detail->placement_id)?->name,
                            'online_shop' => \App\Models\OnlineShop::find($detail->placement_id)?->name,
                            default => null
                        } : null
                    },
                    'notes' => $log->notes ?: $log->description,
                    'user' => $log->user,
                ];
            });
        }

        return response()->json($paginated);
    }

    public function stockOutHistory(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = InventoryLog::with(['product', 'user', 'distributor'])
            ->where('type', 'out');

        // SEARCH
        if ($request->search) {
            $search = $request->search;
            $keywords = explode(' ', $search);

            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $lowKeyword = strtolower($keyword);
                    $q->where(function ($sub) use ($lowKeyword) {
                        $sub->whereHas('product', function ($sq) use ($lowKeyword) {
                            $sq->whereRaw('LOWER(name) LIKE ?', ["%{$lowKeyword}%"])
                                ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$lowKeyword}%"]);

                            if (Schema::hasColumn('products', 'non_imei_category')) {
                                $sq->orWhereRaw('LOWER(non_imei_category) LIKE ?', ["%{$lowKeyword}%"]);
                            }
                        })
                            ->orWhereRaw('LOWER(description) LIKE ?', ["%{$lowKeyword}%"]);
                    });
                }
            });
        }

        // PLACEMENT FILTER
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'analist', 'owner'];
        if (!$user->hasRole($unrestrictedRoles)) {
            $query->where(function ($q) use ($user) {
                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $onlineShopIds = $user->getAccessibleOnlineShopIds();

                $hasConstraint = false;
                if (!empty($branchIds)) {
                    $q->orWhereIn('branch_id', $branchIds);
                    $hasConstraint = true;
                }
                if (!empty($warehouseIds)) {
                    $q->orWhereIn('warehouse_id', $warehouseIds);
                    $hasConstraint = true;
                }
                if (!empty($onlineShopIds)) {
                    $q->orWhereIn('online_shop_id', $onlineShopIds);
                    $hasConstraint = true;
                }
                if ($user->distributor_id) {
                    $q->orWhere('user_id', $user->id);
                    $hasConstraint = true;
                }

                if (!$hasConstraint) {
                    $q->whereRaw('0 = 1');
                }
            });
        }

        // AUDIT LOCATION FILTERS
        $auditRoles2 = ['audit', 'leader', 'super_admin', 'admin_produk', 'analist', 'owner'];
        if ($request->filled('branch_id') && $user->hasAnyRole($auditRoles2)) {
            $query->where('branch_id', $request->branch_id);
        } elseif ($request->filled('online_shop_id') && $user->hasAnyRole($auditRoles2)) {
            $query->where('online_shop_id', $request->online_shop_id);
        } elseif ($request->filled('warehouse_id') && $user->hasAnyRole($auditRoles2)) {
            $query->where('warehouse_id', $request->warehouse_id);
        } elseif ($request->filled('distributor_id') && $user->hasAnyRole($auditRoles2)) {
            $query->where('distributor_id', $request->distributor_id);
        }

        // Analist Exclusion for Stock Out History
        if ($user->hasRole('analist') && !$user->hasRole('super_admin')) {
            /** @var array $excludedKeywords */
            $excludedKeywords = config('kasara.excluded_keywords');
            $query->where(function ($q) use ($excludedKeywords) {
                foreach (['branch', 'online_shop', 'warehouse', 'distributor'] as $pType) {
                    $q->whereNot(function ($sq) use ($pType, $excludedKeywords) {
                        $tableName = match ($pType) {
                            'branch' => 'branches',
                            'online_shop' => 'online_shops',
                            'warehouse' => 'warehouses',
                            'distributor' => 'distributors',
                        };
                        $colName = match ($pType) {
                            'branch' => 'branch_id',
                            'online_shop' => 'online_shop_id',
                            'warehouse' => 'warehouse_id',
                            'distributor' => 'distributor_id',
                        };
                        
                        $sq->whereNotNull($colName)
                           ->whereExists(function ($exq) use ($tableName, $colName, $excludedKeywords) {
                               $exq->select(DB::raw(1))->from($tableName)->whereColumn("$tableName.id", "inventory_logs.$colName")
                                   ->where(function ($nq) use ($excludedKeywords) {
                                       foreach ($excludedKeywords as $kw) $nq->orWhere('name', 'ilike', "%$kw%");
                                   });
                           });
                    });
                }
            });
        }

        // DATE FILTER
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        if ($request->date) {
            $d = $request->date;
            if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
                $today = $logicalNow->toDateString();
                $yesterday = $logicalNow->copy()->subDay()->toDateString();
                if ($d < $yesterday)
                    $d = $today;
            }
            $query->whereDate('created_at', $d);
        } elseif ($request->month && $request->year) {
            $m = (int) $request->month;
            $y = (int) $request->year;
            $start = \Carbon\Carbon::create($y, $m, 1)->startOfMonth()->startOfDay()->toDateTimeString();
            $end = \Carbon\Carbon::create($y, $m, 1)->endOfMonth()->endOfDay()->toDateTimeString();
            $query->whereBetween('created_at', [$start, $end]);
        }


        return response()->json($query->latest()->paginate(20));
    }

    public function exportStockInHistory(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        // 1. HP STOCK IN (Using InventoryLog for accurate timestamps)
        $hpQuery = InventoryLog::with(['product', 'user', 'distributor', 'branch', 'warehouse', 'onlineShop'])
            ->where('type', 'in')
            ->whereHas('product', fn($q) => $q->where('type', 'hp'));
        $this->applyStockHistoryFilters($hpQuery, $request, 'hp', 'in');
        $hpItems = $hpQuery->latest()->get();

        $refIds = $hpItems->pluck('reference_id')->filter(fn($val) => is_numeric($val))->unique()->toArray();
        $productDetails = ProductDetail::withTrashed()->whereIn('id', $refIds)->get()->keyBy('id');

        $hpSheet = [['No', 'Waktu', 'Merek', 'Produk', 'Kapasitas', 'Kondisi', 'IMEI', 'Lokasi', 'Distributor / Supplier', 'HPP', 'Akun Inventory', 'Catatan']];
        foreach ($hpItems as $idx => $item) {
            $detail = $productDetails->get($item->reference_id);

            $locationName = '-';
            if ($item->branch_id) $locationName = $item->branch?->name ?? ('Cabang #' . $item->branch_id);
            elseif ($item->warehouse_id) $locationName = $item->warehouse?->name ?? ('Gudang #' . $item->warehouse_id);
            elseif ($item->online_shop_id) $locationName = $item->onlineShop?->name ?? ('OS #' . $item->online_shop_id);

            $imei = $detail?->imei ?? '-';
            if ($imei === '-' && $item->description && preg_match('/\(([\d]+)\)/', $item->description, $matches)) {
                $imei = $matches[1];
            }

            $hpSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                $item->product?->brand ?? '-',
                $item->product?->name ?? '-',
                $detail ? implode('/', array_filter([$detail->ram, $detail->storage])) : '-',
                $detail?->condition ?? '-',
                str_replace("'", "", $imei),
                $locationName,
                $item->distributor?->name ?? ($item->supplier_name ?? ($detail?->distributor?->name ?? ($detail?->supplier_name ?? '-'))),
                (float)($detail?->cost_price ?? ($item->cost_price ?? 0)),
                $item->user?->name ?? '-',
                $item->notes ?: ($item->description ?? '-'),
            ];
        }

        // 2. NON-HP STOCK IN (InventoryLog)
        $nonHpQuery = InventoryLog::with(['product', 'user', 'distributor'])->where('type', 'in');
        $this->applyStockHistoryFilters($nonHpQuery, $request, 'non-hp', 'in');
        $nonHpItems = $nonHpQuery->latest()->get();

        $nonHpSheet = [['No', 'Waktu', 'Merek', 'Produk', 'Lokasi', 'Qty Masuk', 'Distributor / Supplier', 'HPP', 'Akun Inventory', 'Catatan']];
        foreach ($nonHpItems as $idx => $item) {
            $locationName = '-';
            if ($item->branch_id) $locationName = \App\Models\Branch::find($item->branch_id)?->name;
            elseif ($item->warehouse_id) $locationName = \App\Models\Warehouse::find($item->warehouse_id)?->name;
            elseif ($item->online_shop_id) $locationName = \App\Models\OnlineShop::find($item->online_shop_id)?->name;

            $nonHpSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                $item->product?->brand ?? '-',
                $item->product?->name ?? '-',
                $locationName,
                (int)$item->quantity,
                $item->distributor?->name ?? ($item->supplier_name ?? '-'),
                (float)($item->cost_price ?? 0),
                $item->user?->name ?? '-',
                $item->notes ?: ($item->description ?? '-'),
            ];
        }

        $dateSuffix = $request->date ? "_{$request->date}" : "_" . now()->format('Y-m-d_H-i');
        $filename = 'RIWAYAT_STOK_MASUK' . $dateSuffix . '.xlsx';
        \App\Models\ExportLog::create([
            'user_id' => $user->id,
            'report_name' => 'Riwayat Stok Masuk',
            'filename' => $filename,
            'params' => $request->all()
        ]);

        $xlsx = SimpleXLSXGen::fromSheets([
            'Riwayat HP' => $hpSheet,
            'Riwayat Non-HP' => $nonHpSheet
        ]);

        return response((string)$xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportStockOutHistory(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Stock Out HP (InventoryLog)
        $hpOutQuery = InventoryLog::with(['product', 'user', 'distributor', 'branch', 'warehouse', 'onlineShop'])
            ->where('type', 'out')
            ->whereHas('product', fn($q) => $q->where('type', 'hp'));
        $this->applyStockHistoryFilters($hpOutQuery, $request, 'hp', 'out');
        $hpOutItems = $hpOutQuery->latest()->get();

        $outImeis = [];
        foreach ($hpOutItems as $itm) {
            if ($itm->description && preg_match('/\(([\d]+)\)/', $itm->description, $matches)) {
                $outImeis[] = $matches[1];
            }
        }
        $outDetails = ProductDetail::withTrashed()->whereIn('imei', $outImeis)->get()->keyBy('imei');

        $hpOutSheet = [['No', 'Waktu', 'Sumber / Kategori Keluar', 'Merek', 'Produk', 'Spec', 'IMEI', 'Lokasi', 'Tujuan / Catatan', 'Akun Inventory']];
        foreach ($hpOutItems as $idx => $item) {
            $locationName = '-';
            if ($item->branch_id) $locationName = $item->branch?->name ?? ('Cabang #' . $item->branch_id);
            elseif ($item->warehouse_id) $locationName = $item->warehouse?->name ?? ('Gudang #' . $item->warehouse_id);
            elseif ($item->online_shop_id) $locationName = $item->onlineShop?->name ?? ('OS #' . $item->online_shop_id);

            $imei = '-';
            $spec = '-';
            if ($item->description && preg_match('/\(([\d]+)\)/', $item->description, $matches)) {
                $imei = $matches[1];
                $det = $outDetails->get($imei);
                if ($det) {
                    $spec = implode('/', array_filter([$det->ram, $det->storage]));
                }
            }

            $outCategory = 'Stock Out';
            if ($item->description) {
                $parts = explode('(', $item->description);
                $trimmedPart = trim($parts[0]);
                if (!empty($trimmedPart)) {
                    $outCategory = $trimmedPart;
                }
            }

            $hpOutSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                strtoupper($outCategory),
                $item->product?->brand ?? '-',
                $item->product?->name ?? '-',
                $spec,
                str_replace("'", "", $imei),
                $locationName,
                $item->description ?? '-',
                $item->user?->name ?? '-',
            ];
        }

        // Stock Out Non-HP (InventoryLog)
        $nonHpOutQuery = InventoryLog::with(['product', 'user', 'distributor', 'branch', 'warehouse', 'onlineShop'])
            ->where('type', 'out')
            ->whereHas('product', fn($q) => $q->where('type', '!=', 'hp'));
        $this->applyStockHistoryFilters($nonHpOutQuery, $request, 'non-hp', 'out');
        $nonHpOutItems = $nonHpOutQuery->latest()->get();

        $nonHpOutSheet = [['No', 'Waktu', 'Sumber / Kategori Keluar', 'Merek', 'Produk', 'Lokasi', 'Qty Keluar', 'Tujuan / Catatan', 'Akun Inventory']];
        foreach ($nonHpOutItems as $idx => $item) {
            $locationName = '-';
            if ($item->branch_id) $locationName = $item->branch?->name ?? ('Cabang #' . $item->branch_id);
            elseif ($item->warehouse_id) $locationName = $item->warehouse?->name ?? ('Gudang #' . $item->warehouse_id);
            elseif ($item->online_shop_id) $locationName = $item->onlineShop?->name ?? ('OS #' . $item->online_shop_id);

            $outCategory = 'Stock Out';
            if ($item->description) {
                $parts = explode('(', $item->description);
                $trimmedPart = trim($parts[0]);
                if (!empty($trimmedPart)) {
                    $outCategory = $trimmedPart;
                }
            }

            $nonHpOutSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                strtoupper($outCategory),
                $item->product?->brand ?? '-',
                $item->product?->name ?? '-',
                $locationName,
                (int)$item->quantity,
                $item->description ?? '-',
                $item->user?->name ?? '-',
            ];
        }

        $dateSuffix = $request->date ? "_{$request->date}" : "_" . now()->format('Y-m-d_H-i');
        $filename = 'RIWAYAT_STOK_KELUAR' . $dateSuffix . '.xlsx';
        \App\Models\ExportLog::create([
            'user_id' => $user->id,
            'report_name' => 'Riwayat Stok Keluar',
            'filename' => $filename,
            'params' => $request->all()
        ]);

        $xlsx = SimpleXLSXGen::fromSheets([
            'Keluar HP' => $hpOutSheet,
            'Keluar Non-HP' => $nonHpOutSheet
        ]);

        return response((string)$xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportStockHistoryCombined(Request $request)
    {
        try {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // --- 1. STOCK IN HP ---
        $hpInQuery = InventoryLog::with(['product', 'user', 'distributor', 'branch', 'warehouse', 'onlineShop'])
            ->where('type', 'in')
            ->whereHas('product', fn($q) => $q->where('type', 'hp'));
        $this->applyStockHistoryFilters($hpInQuery, $request, 'hp', 'in');
        $hpInItems = $hpInQuery->latest()->get();

        $refIds = $hpInItems->pluck('reference_id')->filter(fn($val) => is_numeric($val))->unique()->toArray();
        $productDetails = ProductDetail::withTrashed()->whereIn('id', $refIds)->get()->keyBy('id');

        $hpInSheet = [['No', 'Waktu', 'Sumber Masuk', 'Kategori', 'Merek', 'Produk', 'Spec', 'Kondisi', 'IMEI', 'Lokasi', 'Distributor / Supplier', 'HPP', 'Akun Inventory', 'Catatan']];
        foreach ($hpInItems as $idx => $item) {
            $detail = $productDetails->get($item->reference_id);
            
            $source = 'Masuk Manual';
            $desc = strtolower($item->description ?? '');
            if ($detail) {
                if ($detail->trade_in_id) $source = 'Angkat Barang';
                elseif ($detail->tukar_tambah_id) $source = 'Tukar Tambah';
                elseif ($detail->refund_id) $source = 'Refund';
                elseif ($detail->unit_exchange_id) $source = 'Tukar Unit';
                elseif ($detail->downgrade_id) $source = 'Downgrade';
            } else {
                if (str_contains($desc, 'angkat barang') || str_contains($desc, 'trade-in') || str_contains($desc, 'angkat_barang')) $source = 'Angkat Barang';
                elseif (str_contains($desc, 'tukar tambah') || str_contains($desc, 'tukar_tambah')) $source = 'Tukar Tambah';
                elseif (str_contains($desc, 'refund')) $source = 'Refund';
                elseif (str_contains($desc, 'tukar unit') || str_contains($desc, 'exchange')) $source = 'Tukar Unit';
                elseif (str_contains($desc, 'downgrade')) $source = 'Downgrade';
                elseif (str_contains($desc, 'pindah cabang') || str_contains($desc, 'transfer')) $source = 'Pindah Cabang';
            }

            $category = $item->product?->category ?? '-';
            $locationName = '-';
            if ($item->branch_id) $locationName = $item->branch?->name ?? ('Cabang #' . $item->branch_id);
            elseif ($item->warehouse_id) $locationName = $item->warehouse?->name ?? ('Gudang #' . $item->warehouse_id);
            elseif ($item->online_shop_id) $locationName = $item->onlineShop?->name ?? ('OS #' . $item->online_shop_id);

            $imei = $detail?->imei ?? '-';
            if ($imei === '-' && $item->description && preg_match('/\(([\d]+)\)/', $item->description, $matches)) {
                $imei = $matches[1];
            }

            $hpInSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                $source,
                $category,
                $item->product?->brand ?? '-',
                $item->product?->name ?? '-',
                $detail ? implode('/', array_filter([$detail->ram, $detail->storage])) : '-',
                $detail?->condition ?? '-',
                "\t" . str_replace("'", "", (string)$imei),
                $locationName,
                $item->distributor?->name ?? ($item->supplier_name ?? ($detail?->distributor?->name ?? ($detail?->supplier_name ?? '-'))),
                (float)($detail?->cost_price ?? ($item->cost_price ?? 0)),
                $item->user?->name ?? '-',
                $item->notes ?: ($item->description ?? '-'),
            ];
        }

        // --- 2. STOCK IN NON-HP ---
        $nonHpInQuery = InventoryLog::with(['product', 'user', 'distributor', 'branch', 'warehouse', 'onlineShop'])->where('type', 'in')
            ->whereHas('product', fn($q) => $q->where('type', '!=', 'hp'));
        $this->applyStockHistoryFilters($nonHpInQuery, $request, 'non-hp', 'in');
        $nonHpInItems = $nonHpInQuery->latest()->get();

        $nonHpInSheet = [['No', 'Waktu', 'Sumber Masuk', 'Kategori', 'Merek', 'Produk', 'Lokasi', 'Qty Masuk', 'Distributor / Supplier', 'HPP', 'Akun Inventory', 'Catatan']];
        foreach ($nonHpInItems as $idx => $item) {
            $locationName = '-';
            if ($item->branch_id) $locationName = $item->branch?->name ?? ('Cabang #' . $item->branch_id);
            elseif ($item->warehouse_id) $locationName = $item->warehouse?->name ?? ('Gudang #' . $item->warehouse_id);
            elseif ($item->online_shop_id) $locationName = $item->onlineShop?->name ?? ('OS #' . $item->online_shop_id);

            $source = 'Masuk Manual';
            $desc = strtolower($item->description ?? '');
            if (str_contains($desc, 'angkat barang') || ($item->reference_id && str_contains(strtolower($item->reference_id), 'trade-in'))) $source = 'Angkat Barang';
            elseif (str_contains($desc, 'tukar tambah')) $source = 'Tukar Tambah';
            elseif (str_contains($desc, 'refund')) $source = 'Refund';
            elseif (str_contains($desc, 'tukar unit') || str_contains($desc, 'exchange')) $source = 'Tukar Unit';
            elseif (str_contains($desc, 'downgrade')) $source = 'Downgrade';
            elseif (str_contains($desc, 'pindah cabang') || str_contains($desc, 'transfer')) $source = 'Pindah Cabang';

            $category = $item->product?->category ?? '-';

            $nonHpInSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                $source,
                $category,
                $item->product?->brand ?? '-',
                $item->product?->name ?? '-',
                $locationName,
                (int)$item->quantity,
                $item->distributor?->name ?? ($item->supplier_name ?? '-'),
                (float)($item->cost_price ?? 0),
                $item->user?->name ?? '-',
                $item->notes ?: ($item->description ?? '-'),
            ];
        }

        // --- 3. STOCK OUT HP ---
        $hpOutQuery = InventoryLog::with(['product', 'user', 'distributor', 'branch', 'warehouse', 'onlineShop'])
            ->where('type', 'out')
            ->whereHas('product', fn($q) => $q->where('type', 'hp'));
        $this->applyStockHistoryFilters($hpOutQuery, $request, 'hp', 'out');
        $hpOutItems = $hpOutQuery->latest()->get();
        
        $outImeis = [];
        foreach ($hpOutItems as $itm) {
            if ($itm->description && preg_match('/\(([\d]+)\)/', $itm->description, $matches)) {
                $outImeis[] = $matches[1];
            }
        }
        $outDetails = ProductDetail::withTrashed()->whereIn('imei', $outImeis)->get()->keyBy('imei');

        $hpOutSheet = [['No', 'Waktu', 'Sumber / Kategori Keluar', 'Merek', 'Produk', 'Spec', 'IMEI', 'Lokasi', 'Tujuan / Catatan', 'Akun Inventory']];
        foreach ($hpOutItems as $idx => $item) {
            $locationName = '-';
            if ($item->branch_id) $locationName = $item->branch?->name ?? ('Cabang #' . $item->branch_id);
            elseif ($item->warehouse_id) $locationName = $item->warehouse?->name ?? ('Gudang #' . $item->warehouse_id);
            elseif ($item->online_shop_id) $locationName = $item->onlineShop?->name ?? ('OS #' . $item->online_shop_id);

            $imei = '-';
            $spec = '-';
            if ($item->description && preg_match('/\(([\d]+)\)/', $item->description, $matches)) { 
                $imei = $matches[1];
                $det = $outDetails->get($imei);
                if ($det) {
                    $spec = implode('/', array_filter([$det->ram, $det->storage]));
                }
            }
            
            $outCategory = 'Stock Out';
            if ($item->description) {
                $parts = explode('(', $item->description);
                $trimmedPart = trim($parts[0]);
                if (!empty($trimmedPart)) {
                    $outCategory = $trimmedPart;
                }
            }

            $hpOutSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                strtoupper($outCategory),
                $item->product?->brand ?? '-',
                $item->product?->name ?? '-',
                $spec,
                "\t" . str_replace("'", "", (string)$imei),
                $locationName,
                $item->description ?? '-',
                $item->user?->name ?? '-',
            ];
        }

        // --- 4. STOCK OUT NON-HP ---
        $nonHpOutQuery = InventoryLog::with(['product', 'user', 'distributor', 'branch', 'warehouse', 'onlineShop'])
            ->where('type', 'out')
            ->whereHas('product', fn($q) => $q->where('type', '!=', 'hp'));
        $this->applyStockHistoryFilters($nonHpOutQuery, $request, 'non-hp', 'out');
        $nonHpOutItems = $nonHpOutQuery->latest()->get();

        $nonHpOutSheet = [['No', 'Waktu', 'Sumber / Kategori Keluar', 'Merek', 'Produk', 'Lokasi', 'Qty Keluar', 'Tujuan / Catatan', 'Akun Inventory']];
        foreach ($nonHpOutItems as $idx => $item) {
            $locationName = '-';
            if ($item->branch_id) $locationName = $item->branch?->name ?? ('Cabang #' . $item->branch_id);
            elseif ($item->warehouse_id) $locationName = $item->warehouse?->name ?? ('Gudang #' . $item->warehouse_id);
            elseif ($item->online_shop_id) $locationName = $item->onlineShop?->name ?? ('OS #' . $item->online_shop_id);

            $outCategory = 'Stock Out';
            if ($item->description) {
                $parts = explode('(', $item->description);
                $trimmedPart = trim($parts[0]);
                if (!empty($trimmedPart)) {
                    $outCategory = $trimmedPart;
                }
            }

            $nonHpOutSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                strtoupper($outCategory),
                $item->product?->brand ?? '-',
                $item->product?->name ?? '-',
                $locationName,
                (int)$item->quantity,
                $item->description ?? '-',
                $item->user?->name ?? '-',
            ];
        }

        $dateSuffix = $request->start_date ? "_{$request->start_date}_SD_{$request->end_date}" : "_" . now()->format('Y-m-d');
        $filename = 'RIWAYAT_STOK_MASUK_KELUAR' . $dateSuffix . '.xlsx';
        
        \App\Models\ExportLog::create([
            'user_id' => $user->id,
            'report_name' => 'Riwayat Stok Masuk Keluar Combined',
            'filename' => $filename,
            'params' => $request->all()
        ]);

        $xlsx = SimpleXLSXGen::fromSheets([
            'Laporan Masuk HP' => $hpInSheet,
            'Laporan Masuk Non-HP' => $nonHpInSheet,
            'Laporan Keluar HP' => $hpOutSheet,
            'Laporan Keluar Non-HP' => $nonHpOutSheet
        ]);

        return response((string)$xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Export Stock History Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Export gagal: ' . $e->getMessage(),
                'file' => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
    }

    public function voidStockIn(Request $request, $id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $type = $request->input('type', 'hp');

        DB::beginTransaction();
        try {
            if ($type === 'hp') {
                $item = ProductDetail::findOrFail($id);

                $unrestrictedRoles = ['super_admin', 'owner', 'audit', 'admin_produk', 'analist'];
                if ($item->user_id !== $user->id && !$user->hasRole($unrestrictedRoles)) {
                    throw new \Exception('Anda tidak memiliki izin untuk menghapus item ini.');
                }

                if ($item->status !== 'available') {
                    throw new \Exception('Hanya barang dengan status "Available" yang dapat dihapus.');
                }

                $usageCount = $item->stockOuts()->where('category', '!=', 'barang_masuk')->count();
                if ($usageCount > 0) {
                    throw new \Exception('Barang ini sudah memiliki riwayat transaksi lain dan tidak dapat dihapus.');
                }

                $auditStockOut = $item->stockOuts()->where('category', 'barang_masuk')->first();
                if ($auditStockOut) {
                    $auditStockOut->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $user->id]);
                }

                $item->delete();
            } else {
                $log = InventoryLog::findOrFail($id);

                if ($log->type !== 'in') {
                    throw new \Exception('Hanya log "Stock In" yang dapat dihapus melalui menu ini.');
                }

                $unrestrictedRoles = ['super_admin', 'owner', 'audit', 'admin_produk', 'analist'];
                if ($log->user_id !== $user->id && !$user->hasRole($unrestrictedRoles)) {
                    throw new \Exception('Anda tidak memiliki izin untuk menghapus log ini.');
                }

                $invQuery = Inventory::where('product_id', $log->product_id)
                    ->where('user_id', $log->user_id);

                if ($log->branch_id)
                    $invQuery->where('placement_type', 'branch')->where('placement_id', $log->branch_id);
                elseif ($log->warehouse_id)
                    $invQuery->where('placement_type', 'warehouse')->where('placement_id', $log->warehouse_id);
                elseif ($log->online_shop_id)
                    $invQuery->where('placement_type', 'online_shop')->where('placement_id', $log->online_shop_id);

                $inventory = $invQuery->first();

                if (!$inventory || $inventory->quantity < $log->quantity) {
                    throw new \Exception('Stok saat ini tidak mencukupi untuk melakukan pembatalan (barang mungkin sudah terjual/keluar).');
                }

                $inventory->decrement('quantity', $log->quantity);

                InventoryLog::create([
                    'product_id' => $log->product_id,
                    'type' => 'out',
                    'quantity' => $log->quantity,
                    'balance_after' => $inventory->quantity,
                    'description' => "Void Stock In (Ref Log ID: {$log->id})",
                    'reference_id' => 'VOID-IN-' . time(),
                    'user_id' => $user->id,
                    'branch_id' => $log->branch_id,
                    'warehouse_id' => $log->warehouse_id,
                    'online_shop_id' => $log->online_shop_id,
                    'notes' => 'Pembatalan stok masuk'
                ]);

                $log->delete();
            }

            DB::commit();
            return response()->json(['message' => 'Stok masuk berhasil dibatalkan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function applyStockHistoryFilters($query, $request, $type, $mode)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        if ($request->branch_id) {
            if ($query->getModel() instanceof ProductDetail) {
                $query->where('placement_type', 'branch')->where('placement_id', $request->branch_id);
            } else {
                $query->where('branch_id', $request->branch_id);
            }
        }
        if ($request->online_shop_id) {
            if ($query->getModel() instanceof ProductDetail) {
                $query->where('placement_type', 'online_shop')->where('placement_id', $request->online_shop_id);
            } else {
                $query->where('online_shop_id', $request->online_shop_id);
            }
        }
        if ($request->warehouse_id) {
            if ($query->getModel() instanceof ProductDetail) {
                $query->where('placement_type', 'warehouse')->where('placement_id', $request->warehouse_id);
            } else {
                $query->where('warehouse_id', $request->warehouse_id);
            }
        }

        if ($request->start_date && $request->end_date) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('reporting_date', [$request->start_date, $request->end_date])
                  ->orWhere(function ($sq) use ($request) {
                      // Fallback untuk data lama tanpa reporting_date
                      $start = $request->start_date . ' 05:00:00';
                      $end = date('Y-m-d', strtotime($request->end_date . ' +1 day')) . ' 04:59:59';
                      $sq->whereNull('reporting_date')
                         ->whereBetween('created_at', [$start, $end]);
                  });
            });
        } elseif ($request->date) {
            $query->where(function ($q) use ($request) {
                $q->where('reporting_date', $request->date)
                  ->orWhere(function ($sq) use ($request) {
                      $start = $request->date . ' 05:00:00';
                      $end = date('Y-m-d', strtotime($request->date . ' +1 day')) . ' 04:59:59';
                      $sq->whereNull('reporting_date')
                         ->whereBetween('created_at', [$start, $end]);
                  });
            });
        } elseif ($request->month && $request->year) {
            $m = (int) $request->month;
            $y = (int) $request->year;
            $start = \Carbon\Carbon::create($y, $m, 1)->startOfMonth()->startOfDay()->toDateTimeString();
            $end = \Carbon\Carbon::create($y, $m, 1)->endOfMonth()->endOfDay()->toDateTimeString();
            $query->where(function ($q) use ($start, $end) {
                $q->whereBetween('reporting_date', [$start, $end])
                  ->orWhere(function ($sq) use ($start, $end) {
                      $sq->whereNull('reporting_date')
                         ->whereBetween('created_at', [$start, $end]);
                  });
            });
        }

        $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner', 'analist'];
        if (!$user->hasRole($unrestrictedRoles)) {
            // Add access scoping if needed
        }
    }
}
