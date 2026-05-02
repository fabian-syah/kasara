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
        $user = Auth::user();

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'source' => 'required|in:pstore,luar_pstore',
            'brand_id' => 'required|exists:brands,id',
            'distributor_id' => 'nullable|exists:distributors,id',
            'product_type_id' => 'required|exists:product_types,id',
            'imeis' => 'nullable|array',
            'imeis.*' => 'nullable|string|max:25',
            'quantity' => 'nullable|integer|min:1',
            'storage' => 'nullable|string|max:50',
            'condition' => 'required|in:new,second,ex_ibox',
            'buy_price' => 'required|numeric|min:0',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'photo_unit' => 'required|image|max:5120',
            'photo_customer' => 'nullable|image|max:5120',
            'transaction_pin' => 'nullable|string|max:10',
            'inventory_user_id' => 'nullable|exists:users,id',
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

                // 2. Resolve Product (Master)
                $productType = \App\Models\ProductType::with('brand')->findOrFail($request->product_type_id);
                $isImei = in_array(strtolower($productType->category), ['imei', 'hp / gadget', 'hp/gadget']);

                // Find or create a Product record that matches this ProductType
                $product = \App\Models\Product::firstOrCreate(
                    ['name' => $productType->name, 'brand' => $productType->brand->name],
                    [
                        'type' => $isImei ? 'hp' : 'non-hp',
                        'has_imei' => $isImei,
                        'is_active' => true,
                        'sku' => ($isImei ? 'HP-' : 'ACC-') . strtoupper(Str::random(8))
                    ]
                );

                // Resolve inventory_user_id and target location
                $inventoryUserId = $request->inventory_user_id;
                if (!$inventoryUserId && $request->sales_account) {
                    $invUser = \App\Models\User::where('name', $request->sales_account)
                                   ->whereHas('roles', function($q) { $q->where('name', 'inventory'); })
                                   ->first();
                    if ($invUser) $inventoryUserId = $invUser->id;
                }
                $inventoryUserId = $inventoryUserId ?? $user->id;
                
                $targetUser = \App\Models\User::find($inventoryUserId);
                $branchId = $targetUser->branch_id ?? $user->branch_id;
                $warehouseId = $targetUser->warehouse_id ?? $user->warehouse_id;
                $onlineShopId = $targetUser->online_shop_id ?? $user->online_shop_id;

                $receiptId = TradeIn::generateReceiptId();
                $processedTradeIns = [];
                $placementType = $branchId ? 'branch' : ($warehouseId ? 'warehouse' : 'distributor');
                $placementId = $branchId ?? ($warehouseId ?? $targetUser->distributor_id);

                if ($isImei && $request->has('imeis')) {
                    foreach ($request->imeis as $imei) {
                        // Check duplicate IMEI in inventory
                        if ($imei && \App\Models\ProductDetail::where('imei', $imei)->exists()) {
                            throw new \Exception("IMEI $imei sudah ada di inventory.");
                        }

                        $tradeIn = TradeIn::create([
                            'receipt_id' => $receiptId,
                            'customer_name' => $request->customer_name,
                            'customer_phone' => $request->customer_phone,
                            'source' => $request->source,
                            'distributor_id' => $request->distributor_id,
                            'product_type_id' => $request->product_type_id,
                            'imei' => $imei,
                            'ram' => $productType->ram,
                            'storage' => $request->storage,
                            'condition' => $request->condition,
                            'buy_price' => $request->buy_price,
                            'quantity' => 1,
                            'payment_method_id' => $request->payment_method_id,
                            'reason' => $request->reason,
                            'notes' => $request->notes,
                            'photo_unit' => $photoLog['unit'] ?? null,
                            'photo_customer' => $photoLog['customer'] ?? null,
                            'user_id' => $user->id,
                            'inventory_user_id' => $inventoryUserId,
                            'branch_id' => $branchId,
                        ]);

                        $pd = ProductDetail::create([
                            'product_id' => $product->id,
                            'user_id' => $inventoryUserId,
                            'imei' => $imei,
                            'ram' => $productType->ram,
                            'storage' => $request->storage,
                            'condition' => $request->condition,
                            'status' => 'available',
                            'placement_type' => $placementType,
                            'placement_id' => $placementId,
                            'cost_price' => $request->buy_price,
                            'selling_price' => $productType->price ?? 0,
                            'supplier_name' => 'Trade-In: ' . $request->customer_name,
                            'distributor_id' => $request->distributor_id,
                            'trade_in_id' => $tradeIn->id,
                            'notes' => $request->notes,
                        ]);

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
                            'notes' => $request->notes,
                        ]);

                        $processedTradeIns[] = $tradeIn;

                        $negBuyPrice = -abs((float)$request->buy_price);
                        // Create StockOut record for reporting
                        StockOut::create([
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
                            'status' => 'received',
                            'notes' => "Angkat Barang Alasan: " . $request->reason . ($request->notes ? " | Ket: " . $request->notes : ""),
                            'proof_image' => $photoLog['unit'] ?? null,
                            'selling_price' => $negBuyPrice,
                            'total_amount' => $negBuyPrice,
                            'paid' => $negBuyPrice,
                            'transaction_pin' => $request->transaction_pin,
                            'payment_method_id' => $request->payment_method_id,
                            'split_payments' => json_encode([
                                [
                                    'payment_method_id' => $request->payment_method_id,
                                    'amount' => $negBuyPrice
                                ]
                            ])
                        ])->items()->attach($pd->id, ['selling_price' => $negBuyPrice]);
                    }
                } else {
                    // Non-HP or fallback quantity based
                    $quantity = $request->quantity ?? 1;
                    $tradeIn = TradeIn::create([
                        'receipt_id' => $receiptId,
                        'customer_name' => $request->customer_name,
                        'customer_phone' => $request->customer_phone,
                        'source' => $request->source,
                        'distributor_id' => $request->distributor_id,
                        'product_type_id' => $request->product_type_id,
                        'imei' => null,
                        'ram' => $productType->ram,
                        'storage' => $request->storage,
                        'condition' => $request->condition,
                        'buy_price' => $request->buy_price,
                        'quantity' => $quantity,
                        'payment_method_id' => $request->payment_method_id,
                        'reason' => $request->reason,
                        'notes' => $request->notes,
                        'photo_unit' => $photoLog['unit'] ?? null,
                        'photo_customer' => $photoLog['customer'] ?? null,
                        'user_id' => $user->id,
                        'inventory_user_id' => $inventoryUserId,
                        'branch_id' => $branchId,
                    ]);

                    $negBuyPriceTotal = -abs((float)$request->buy_price * $quantity);
                    // Create StockOut record for reporting
                    StockOut::create([
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
                        'status' => 'received',
                        'notes' => "Angkat Barang Alasan: " . $request->reason . ($request->notes ? " | Ket: " . $request->notes : ""),
                        'proof_image' => $photoLog['unit'] ?? null,
                        'selling_price' => $negBuyPriceTotal,
                        'total_amount' => $negBuyPriceTotal,
                        'paid' => $negBuyPriceTotal,
                        'transaction_pin' => $request->transaction_pin,
                        'payment_method_id' => $request->payment_method_id,
                        'split_payments' => json_encode([
                            [
                                'payment_method_id' => $request->payment_method_id,
                                'amount' => $negBuyPriceTotal
                            ]
                        ]),
                        'non_hp_items' => [
                            [
                                'product_id' => $product->id,
                                'quantity' => $quantity,
                                'selling_price' => -abs((float)$request->buy_price)
                            ]
                        ]
                    ]);

                    // For non-HP, use Inventory model (quantity based)
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
                        'notes' => $request->notes,
                    ]);

                    $processedTradeIns[] = $tradeIn;
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Barang angkat berhasil diproses dan masuk inventory.',
                    'data' => $processedTradeIns[0]->load('productType.brand', 'paymentMethod'),
                    'count' => count($processedTradeIns)
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
