<?php

namespace App\Http\Controllers;

use App\Models\TradeIn;
use App\Models\StockOut;
use App\Models\ProductDetail;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Traits\VerifiesPin;

class TradeInController extends Controller
{
    use VerifiesPin;
    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Decode JSON strings from FormData before validation
        if ($request->has('items') && is_string($request->items)) {
            $request->merge(['items' => json_decode($request->items, true)]);
        }
        if ($request->has('non_hp_items') && is_string($request->non_hp_items)) {
            $request->merge(['non_hp_items' => json_decode($request->non_hp_items, true)]);
        }

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'source' => 'required|in:pstore,luar_pstore',
            // Single item (legacy support)
            'brand_id' => 'required_without:items|nullable|exists:brands,id',
            'distributor_id' => 'nullable|exists:distributors,id',
            'product_type_id' => 'required_without:items|nullable|exists:product_types,id',
            'imeis' => 'nullable|array',
            'imeis.*' => 'nullable|string|max:25',
            'quantity' => 'nullable|integer|min:1',
            'storage' => 'nullable|string|max:50',
            'condition' => 'nullable|in:new,second,ex_ibox',
            'buy_price' => 'required_without:items|nullable|numeric|min:0',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'split_payments' => 'nullable',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'photo_unit' => 'required|image|max:20480',
            'photo_customer' => 'nullable|image|max:20480',
            'transaction_pin' => 'nullable|string|max:10',
            'inventory_user_id' => 'nullable|exists:users,id',
            // Multi-item support
            'items' => 'nullable|array',
            'items.*.brand_id' => 'required_with:items|exists:brands,id',
            'items.*.product_type_id' => 'required_with:items|exists:product_types,id',
            'items.*.imeis' => 'nullable|array',
            'items.*.imeis.*' => 'nullable|string|max:25',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.storage' => 'nullable|string|max:50',
            'items.*.condition' => 'nullable|in:new,second,ex_ibox',
            'items.*.buy_price' => 'required_with:items|numeric|min:0',
            'items.*.distributor_id' => 'nullable|exists:distributors,id',
            'items.*.notes' => 'nullable|string',
            // Non-IMEI items support
            'non_hp_items' => 'nullable|array',
            'non_hp_items.*.name' => 'required_with:non_hp_items|string|max:255',
            'non_hp_items.*.quantity' => 'nullable|integer|min:1',
            'non_hp_items.*.price' => 'nullable|numeric|min:0',
        ]);

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError) return $pinError;

        try {
            return DB::transaction(function () use ($request, $user) {
                // 1. Handle File Uploads
                $photoLog = [];
                if ($request->hasFile('photo_unit')) {
                    $pathUnit = $request->file('photo_unit')->store('trade-ins/units', 'public');
                    $photoLog['unit'] = $pathUnit;
                }
                if ($request->hasFile('photo_customer')) {
                    $pathCustomer = $request->file('photo_customer')->store('trade-ins/customers', 'public');
                    $photoLog['customer'] = $pathCustomer;
                }

                // Resolve inventory_user_id and target location
                $inventoryUserId = $request->inventory_user_id;
                if (!$inventoryUserId && $request->sales_account) {
                    $invUser = \App\Models\User::where('name', $request->sales_account)
                        ->where(function($q) use ($user) {
                            if ($user->branch_id) $q->where('branch_id', $user->branch_id);
                            elseif ($user->warehouse_id) $q->where('warehouse_id', $user->warehouse_id);
                            elseif ($user->online_shop_id) $q->where('online_shop_id', $user->online_shop_id);
                        })->first();
                    if (!$invUser) {
                        $invUser = \App\Models\User::where('name', $request->sales_account)->first();
                    }
                    if ($invUser) $inventoryUserId = $invUser->id;
                }
                $inventoryUserId = $inventoryUserId ?? $user->id;

                /** @var \App\Models\User|null $targetUser */
                $targetUser = \App\Models\User::find($inventoryUserId);
                $branchId = $targetUser->branch_id ?? $user->branch_id;
                if (!$branchId) {
                    $branchId = $targetUser?->getAccessibleBranchIds()[0] ?? ($user->getAccessibleBranchIds()[0] ?? null);
                }
                
                $warehouseId = $targetUser->warehouse_id ?? $user->warehouse_id;
                if (!$warehouseId) {
                    $warehouseId = $targetUser?->getAccessibleWarehouseIds()[0] ?? ($user->getAccessibleWarehouseIds()[0] ?? null);
                }
                $onlineShopId = $targetUser->online_shop_id ?? $user->online_shop_id;

                $receiptId = TradeIn::generateReceiptId();
                $placementType = $branchId ? 'branch' : ($warehouseId ? 'warehouse' : 'distributor');
                $placementId = $branchId ?? ($warehouseId ?? $targetUser->distributor_id);

                $rawSplits = $request->filled('split_payments')
                    ? (is_string($request->split_payments) ? json_decode($request->split_payments, true) : $request->split_payments)
                    : null;

                $processedSplits = null;
                if ($rawSplits && is_array($rawSplits)) {
                    $processedSplits = [];
                    foreach ($rawSplits as $sp) {
                        $processedSplits[] = [
                            'payment_method_id' => $sp['payment_method_id'],
                            'amount' => (float)$sp['amount']
                        ];
                    }
                } else if ($request->payment_method_id) {
                    $processedSplits = [
                        ['payment_method_id' => $request->payment_method_id, 'amount' => $request->buy_price ?? 0]
                    ];
                }

                $stockOutSplits = null;
                if ($processedSplits) {
                    $stockOutSplits = [];
                    foreach ($processedSplits as $sp) {
                        $stockOutSplits[] = [
                            'payment_method_id' => $sp['payment_method_id'],
                            'amount' => -abs($sp['amount'])
                        ];
                    }
                }

                // Build items list: multi-item mode or single-item (legacy)
                $itemsList = [];
                if ($request->has('items') && is_array($request->items) && count($request->items) > 0) {
                    $itemsList = $request->items;
                }
                
                if (empty($itemsList)) {
                    // Legacy single-item mode
                    $itemsList = [[
                        'brand_id' => $request->brand_id,
                        'product_type_id' => $request->product_type_id,
                        'imeis' => $request->imeis,
                        'quantity' => $request->quantity,
                        'storage' => $request->storage,
                        'condition' => $request->condition,
                        'buy_price' => $request->buy_price,
                        'distributor_id' => $request->distributor_id,
                        'notes' => $request->notes,
                    ]];
                }

                $processedTradeIns = [];
                $existedImeis = [];
                $allProductDetailIds = [];
                $totalBuyPrice = 0;
                $itemCounter = 0;

                foreach ($itemsList as $itemData) {
                    $productType = \App\Models\ProductType::with('brand')->findOrFail($itemData['product_type_id']);
                    $isImei = in_array(strtolower($productType->category), ['imei', 'hp / gadget', 'hp/gadget']);

                    $product = \App\Models\Product::firstOrCreate(
                        ['name' => $productType->name, 'brand' => $productType->brand->name],
                        [
                            'type' => $isImei ? 'hp' : 'non-hp',
                            'has_imei' => $isImei,
                            'is_active' => true,
                            'sku' => ($isImei ? 'HP-' : 'ACC-') . strtoupper(Str::random(8))
                        ]
                    );

                    $itemBuyPrice = (float)($itemData['buy_price'] ?? 0);
                    $itemDistributorId = $itemData['distributor_id'] ?? $request->distributor_id;
                    $itemStorage = $itemData['storage'] ?? null;
                    $itemCondition = $itemData['condition'] ?? 'second';
                    $itemNotes = $itemData['notes'] ?? $request->notes;

                    if ($isImei && !empty($itemData['imeis'])) {
                        foreach ($itemData['imeis'] as $imei) {
                            if (!$imei) continue;
                            $itemCounter++;

                            $existingPd = ProductDetail::where('imei', $imei)->first();
                            if ($existingPd) {
                                $existedImeis[] = "$imei ({$existingPd->status})";
                            }

                            $itemReceiptId = $itemCounter === 1 ? $receiptId : $receiptId . '-' . chr(64 + $itemCounter);

                            $tradeIn = TradeIn::create([
                                'receipt_id' => $itemReceiptId,
                                'customer_name' => $request->customer_name,
                                'customer_phone' => $request->customer_phone,
                                'source' => $request->source,
                                'distributor_id' => $itemDistributorId,
                                'product_type_id' => $itemData['product_type_id'],
                                'imei' => $imei,
                                'ram' => $productType->ram,
                                'storage' => $itemStorage,
                                'condition' => $itemCondition,
                                'buy_price' => $itemBuyPrice,
                                'quantity' => 1,
                                'payment_method_id' => $request->payment_method_id ?? ($processedSplits[0]['payment_method_id'] ?? null),
                                'split_payments' => $processedSplits,
                                'reason' => $request->reason,
                                'notes' => $itemNotes,
                                'photo_unit' => $photoLog['unit'] ?? null,
                                'photo_customer' => $photoLog['customer'] ?? null,
                                'user_id' => $user->id,
                                'inventory_user_id' => $inventoryUserId,
                                'branch_id' => $branchId,
                            ]);

                            if ($existingPd) {
                                $existingPd->update([
                                    'product_id' => $product->id,
                                    'user_id' => $inventoryUserId,
                                    'ram' => $productType->ram,
                                    'storage' => $itemStorage,
                                    'condition' => $itemCondition,
                                    'status' => 'available',
                                    'placement_type' => $placementType,
                                    'placement_id' => $placementId,
                                    'cost_price' => $itemBuyPrice,
                                    'selling_price' => $productType->price ?? 0,
                                    'supplier_name' => 'Trade-In: ' . $request->customer_name,
                                    'distributor_id' => $itemDistributorId,
                                    'trade_in_id' => $tradeIn->id,
                                    'notes' => $itemNotes,
                                ]);
                                $pd = $existingPd;
                            } else {
                                $pd = ProductDetail::create([
                                    'product_id' => $product->id,
                                    'user_id' => $inventoryUserId,
                                    'imei' => $imei,
                                    'ram' => $productType->ram,
                                    'storage' => $itemStorage,
                                    'condition' => $itemCondition,
                                    'status' => 'available',
                                    'placement_type' => $placementType,
                                    'placement_id' => $placementId,
                                    'cost_price' => $itemBuyPrice,
                                    'selling_price' => $productType->price ?? 0,
                                    'supplier_name' => 'Trade-In: ' . $request->customer_name,
                                    'distributor_id' => $itemDistributorId,
                                    'trade_in_id' => $tradeIn->id,
                                    'notes' => $itemNotes,
                                ]);
                            }

                            InventoryLog::create([
                                'product_id' => $product->id,
                                'branch_id' => $branchId,
                                'warehouse_id' => $warehouseId,
                                'online_shop_id' => $onlineShopId,
                                'user_id' => $inventoryUserId,
                                'type' => 'in',
                                'quantity' => 1,
                                'reference_id' => $pd->id,
                                'balance_after' => 1,
                                'description' => 'ANGKAT BARANG HP: ' . $productType->name . ' (' . $imei . ')',
                                'supplier_name' => 'Trade-In',
                                'notes' => $itemNotes,
                            ]);

                            $allProductDetailIds[] = $pd->id;
                            $totalBuyPrice += $itemBuyPrice;
                            $processedTradeIns[] = $tradeIn;
                        }
                    } else {
                        // Non-HP or quantity based
                        $quantity = (int)($itemData['quantity'] ?? 1);
                        $itemCounter++;

                        $itemReceiptId = $itemCounter === 1 ? $receiptId : $receiptId . '-' . chr(64 + $itemCounter);

                        $tradeIn = TradeIn::create([
                            'receipt_id' => $itemReceiptId,
                            'customer_name' => $request->customer_name,
                            'customer_phone' => $request->customer_phone,
                            'source' => $request->source,
                            'distributor_id' => $itemDistributorId,
                            'product_type_id' => $itemData['product_type_id'],
                            'imei' => null,
                            'ram' => $productType->ram,
                            'storage' => $itemStorage,
                            'condition' => $itemCondition,
                            'buy_price' => $itemBuyPrice,
                            'quantity' => $quantity,
                            'payment_method_id' => $request->payment_method_id ?? ($processedSplits[0]['payment_method_id'] ?? null),
                            'split_payments' => $processedSplits,
                            'reason' => $request->reason,
                            'notes' => $itemNotes,
                            'photo_unit' => $photoLog['unit'] ?? null,
                            'photo_customer' => $photoLog['customer'] ?? null,
                            'user_id' => $user->id,
                            'inventory_user_id' => $inventoryUserId,
                            'branch_id' => $branchId,
                        ]);

                        // Add to Inventory (quantity based)
                        $inventory = \App\Models\Inventory::firstOrCreate(
                            [
                                'product_id' => $product->id,
                                'placement_type' => $placementType,
                                'placement_id' => $placementId,
                                'user_id' => $inventoryUserId
                            ],
                            ['quantity' => 0]
                        );
                        $inventory->increment('quantity', $quantity);

                        InventoryLog::create([
                            'product_id' => $product->id,
                            'branch_id' => $branchId,
                            'warehouse_id' => $warehouseId,
                            'online_shop_id' => $onlineShopId,
                            'user_id' => $inventoryUserId,
                            'type' => 'in',
                            'quantity' => $quantity,
                            'reference_id' => 'Trade-In: ' . $receiptId,
                            'description' => 'ANGKAT BARANG Non-HP: ' . $productType->name,
                            'supplier_name' => 'Trade-In',
                            'notes' => $itemNotes,
                        ]);

                        $totalBuyPrice += ($itemBuyPrice * $quantity);
                        $processedTradeIns[] = $tradeIn;
                    }
                }

                // Process non_hp_items (accessories like case, tempered glass, etc.)
                $nonHpStockOutItems = [];
                $rawNhpItems = $request->non_hp_items;
                if (!empty($rawNhpItems) && is_array($rawNhpItems)) {
                    foreach ($rawNhpItems as $nhpItem) {
                        $nhpName = $nhpItem['name'] ?? 'Accessories';
                        $nhpQty = (int)($nhpItem['quantity'] ?? 1);
                        $nhpPrice = (float)($nhpItem['price'] ?? 0);

                        // Find or create product for this accessory
                        $nhpProduct = \App\Models\Product::firstOrCreate(
                            ['name' => $nhpName, 'type' => 'non-hp'],
                            [
                                'brand' => 'Accessories',
                                'has_imei' => false,
                                'is_active' => true,
                                'sku' => 'ACC-' . strtoupper(Str::random(8))
                            ]
                        );

                        $inventory = \App\Models\Inventory::firstOrCreate(
                            [
                                'product_id' => $nhpProduct->id,
                                'placement_type' => $placementType,
                                'placement_id' => $placementId,
                                'user_id' => $inventoryUserId
                            ],
                            ['quantity' => 0]
                        );
                        $inventory->increment('quantity', $nhpQty);

                        InventoryLog::create([
                            'product_id' => $nhpProduct->id,
                            'branch_id' => $branchId,
                            'warehouse_id' => $warehouseId,
                            'online_shop_id' => $onlineShopId,
                            'user_id' => $inventoryUserId,
                            'type' => 'in',
                            'quantity' => $nhpQty,
                            'reference_id' => 'Trade-In-NHP: ' . $receiptId,
                            'description' => 'ANGKAT BARANG Acc (Trade-In): ' . $nhpName,
                            'supplier_name' => 'Trade-In',
                            'notes' => $request->notes,
                        ]);

                        $nonHpStockOutItems[] = [
                            'product_id' => $nhpProduct->id,
                            'quantity' => $nhpQty,
                            'selling_price' => -abs($nhpPrice),
                        ];

                        $totalBuyPrice += ($nhpPrice * $nhpQty);
                    }
                }

                // Create single StockOut record for the entire trade-in transaction
                $negTotalBuyPrice = -abs($totalBuyPrice);
                $stockOut = StockOut::create([
                    'receipt_id' => $receiptId,
                    'category' => 'angkat_barang',
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'customer_wa' => $request->customer_phone,
                    'user_id' => $user->id,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'online_shop_id' => $onlineShopId,
                    'inventory_user_id' => $inventoryUserId,
                    'sales_account' => $request->sales_account ?? $targetUser?->name,
                    'status' => 'received',
                    'notes' => "Angkat Barang Alasan: " . $request->reason . ($request->notes ? " | Ket: " . $request->notes : ""),
                    'proof_image' => $photoLog['unit'] ?? null,
                    'selling_price' => $negTotalBuyPrice,
                    'total_amount' => $negTotalBuyPrice,
                    'paid' => $negTotalBuyPrice,
                    'transaction_pin' => $request->transaction_pin,
                    'payment_method_id' => $request->payment_method_id ?? ($processedSplits[0]['payment_method_id'] ?? null),
                    'split_payments' => json_encode($stockOutSplits),
                ]);

                // Attach HP items
                if (!empty($allProductDetailIds)) {
                    foreach ($allProductDetailIds as $pdId) {
                        $stockOut->items()->attach($pdId, ['selling_price' => $negTotalBuyPrice / count($allProductDetailIds)]);
                    }
                }

                // Attach Non-HP items
                if (!empty($nonHpStockOutItems)) {
                    foreach ($nonHpStockOutItems as $nhpSoi) {
                        \App\Models\StockOutNonHpItem::create([
                            'stock_out_id' => $stockOut->id,
                            'product_id' => $nhpSoi['product_id'],
                            'quantity' => $nhpSoi['quantity'],
                            'selling_price' => $nhpSoi['selling_price'],
                        ]);
                    }
                }

                $totalQty = 0;
                foreach ($processedTradeIns as $ti) {
                    $totalQty += ($ti->quantity ?? 1);
                }
                $totalQty += collect($request->non_hp_items ?? [])->sum(fn($i) => (int)($i['quantity'] ?? 1));

                $msg = 'Barang angkat berhasil diproses dan masuk inventory.';
                if (!empty($existedImeis)) {
                    $msg .= " (Pemberitahuan: IMEI berikut sudah ada di database sebelumnya: " . implode(', ', $existedImeis) . ")";
                }
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'data' => $processedTradeIns[0]->load('productType.brand', 'paymentMethod', 'distributor'),
                    'count' => $totalQty
                ]);
            });
        } catch (\Exception $e) {
            // Delete uploaded files on failure
            if (isset($pathUnit))
                Storage::disk('public')->delete($pathUnit);
            if (isset($pathCustomer))
                Storage::disk('public')->delete($pathCustomer);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses barang angkat: ' . $e->getMessage()
            ], 500);
        }
    }
}
