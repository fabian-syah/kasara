<?php

namespace App\Http\Controllers;

use App\Models\UnitExchange;
use App\Models\StockOut;
use App\Models\ProductDetail;
use App\Models\InventoryLog;
use App\Models\ProductType;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Traits\VerifiesPin;

class UnitExchangeController extends Controller
{
    use VerifiesPin;
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'distributor_id' => 'nullable|exists:distributors,id',
            'incoming_source' => 'required|in:ex_pstore,luar_pstore',

            // Incoming
            'incoming_product_type_id' => 'required|exists:product_types,id',
            'incoming_imei' => 'nullable|string|max:25',
            'incoming_quantity' => 'nullable|integer|min:1',
            'incoming_storage' => 'nullable|string|max:20',
            'incoming_condition' => 'nullable|in:new,second,ex_ibox',
            'incoming_cost_price' => 'required|numeric|min:0',

            // Outgoing
            'outgoing_product_detail_id' => 'required|exists:product_details,id',
            'outgoing_quantity' => 'nullable|integer|min:1',
            'outgoing_price' => 'nullable|numeric|min:0',

            'reason' => 'required|string',
            'notes' => 'nullable|string',
            'photo_unit' => 'required|image|max:20480',
            'photo_customer' => 'nullable|image|max:20480',
            'transaction_pin' => 'nullable|string|max:10',
            'inventory_user_id' => 'nullable|exists:users,id',
            'split_payments' => 'nullable',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
        ]);

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError) return $pinError;

        try {
            return DB::transaction(function () use ($request, $user) {
                // 1. Handle File Uploads
                $photoPathUnit = null;
                $photoPathCustomer = null;

                if ($request->hasFile('photo_unit')) {
                    $photoPathUnit = $request->file('photo_unit')->store('exchanges/units', 'public');
                }
                if ($request->hasFile('photo_customer')) {
                    $photoPathCustomer = $request->file('photo_customer')->store('exchanges/customers', 'public');
                }

                // Resolve inventory_user_id and target location
                $inventoryUserId = $request->inventory_user_id;
                if (!$inventoryUserId && $request->sales_account) {
                    $invUser = \App\Models\User::where('name', $request->sales_account)->first();
                    if ($invUser) $inventoryUserId = $invUser->id;
                }
                $inventoryUserId = $inventoryUserId ?? $user->id;
                
                $targetUser = \App\Models\User::find($inventoryUserId);
                $branchId = $targetUser->branch_id ?? $user->branch_id;
                $warehouseId = $targetUser->warehouse_id ?? $user->warehouse_id;

                $receiptId = UnitExchange::generateReceiptId();

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
                        [
                            'payment_method_id' => $request->payment_method_id,
                            'amount' => $request->outgoing_price ?? 0
                        ]
                    ];
                }

                // 2. Create Unit Exchange record
                $exchange = UnitExchange::create([
                    'receipt_id' => $receiptId,
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'incoming_source' => $request->incoming_source,
                    'incoming_product_type_id' => $request->incoming_product_type_id,
                    'incoming_imei' => $request->incoming_imei,
                    'incoming_storage' => $request->incoming_storage,
                    'incoming_condition' => $request->incoming_condition ?? 'new',
                    'incoming_cost_price' => $request->incoming_cost_price,
                    'outgoing_product_detail_id' => $request->outgoing_product_detail_id,
                    'split_payments' => $processedSplits,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                    'photo_unit' => $photoPathUnit,
                    'photo_customer' => $photoPathCustomer,
                    'user_id' => $user->id,
                    'inventory_user_id' => $inventoryUserId,
                    'distributor_id' => $request->distributor_id,
                    'branch_id' => $branchId,
                    'incoming_quantity' => $request->incoming_quantity ?? 1,
                    'outgoing_quantity' => $request->outgoing_quantity ?? 1,
                ]);

                // 3. Handle Incoming Unit (Entry to Inventory)
                $incomingProductType = ProductType::with('brand')->findOrFail($request->incoming_product_type_id);
                $isImei = in_array(strtolower($incomingProductType->category), ['imei', 'hp / gadget', 'hp/gadget']);

                // Find or create a Product record for mapping
                $product = Product::firstOrCreate(
                    ['name' => $incomingProductType->name, 'brand' => $incomingProductType->brand->name],
                    [
                        'type' => $isImei ? 'hp' : 'non-hp',
                        'has_imei' => $isImei,
                        'is_active' => true,
                        'sku' => ($isImei ? 'HP-' : 'ACC-') . strtoupper(Str::random(8))
                    ]
                );

                $placementType = $branchId ? 'branch' : ($warehouseId ? 'warehouse' : 'distributor');
                $placementId = $branchId ?? ($warehouseId ?? $targetUser->distributor_id);

                $inQty = $request->incoming_quantity ?? 1;
                $imeiExisted = false;
                $imeiStatus = null;
                $productDetail = null;
                if ($isImei) {
                    $existingPd = ProductDetail::where('imei', $request->incoming_imei)->first();
                    if ($existingPd) {
                        $imeiExisted = true;
                        $imeiStatus = $existingPd->status;
                        $existingPd->update([
                            'product_id' => $product->id,
                            'user_id' => $inventoryUserId,
                            'storage' => $request->incoming_storage,
                            'condition' => $request->incoming_condition ?? 'new',
                            'status' => 'available',
                            'placement_type' => $placementType,
                            'placement_id' => $placementId,
                            'cost_price' => $request->incoming_cost_price,
                            'selling_price' => $incomingProductType->price ?? 0,
                            'supplier_name' => 'Exchange: ' . $request->customer_name,
                            'distributor_id' => $request->distributor_id,
                            'unit_exchange_id' => $exchange->id,
                            'notes' => 'Masuk dari Tukar Unit (Update): ' . $receiptId,
                        ]);
                        $productDetail = $existingPd;
                    } else {
                        $productDetail = ProductDetail::create([
                            'product_id' => $product->id,
                            'user_id' => $inventoryUserId,
                            'imei' => $request->incoming_imei,
                            'storage' => $request->incoming_storage,
                            'condition' => $request->incoming_condition ?? 'new',
                            'status' => 'available',
                            'placement_type' => $placementType,
                            'placement_id' => $placementId,
                            'cost_price' => $request->incoming_cost_price,
                            'selling_price' => $incomingProductType->price ?? 0,
                            'supplier_name' => 'Exchange: ' . $request->customer_name,
                            'distributor_id' => $request->distributor_id,
                            'unit_exchange_id' => $exchange->id,
                            'notes' => 'Masuk dari Tukar Unit: ' . $receiptId,
                        ]);
                    }
                } else {
                    $inventoryIn = \App\Models\Inventory::firstOrCreate(
                        [
                            'product_id' => $product->id,
                            'placement_type' => $placementType,
                            'placement_id' => $placementId,
                            'user_id' => $inventoryUserId
                        ],
                        ['quantity' => 0]
                    );
                    $inventoryIn->increment('quantity', $inQty);
                }

                // 4. Create StockOut record for reporting and tracking visibility
                $outgoingUnit = ProductDetail::findOrFail($request->outgoing_product_detail_id);
                $outPrice = $request->outgoing_price ?? ($outgoingUnit->selling_price ?? 0);

                $stockOut = StockOut::create([
                    'receipt_id' => $receiptId,
                    'category' => 'tukar_unit',
                    'reporting_date' => now()->hour < 5 ? now()->subDay()->toDateString() : now()->toDateString(),
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'customer_wa' => $request->customer_wa ?? $request->customer_phone,
                    'user_id' => $user->id,
                    'inventory_user_id' => $inventoryUserId,
                    'sales_account' => $request->sales_account ?? $targetUser?->name,
                    'status' => 'received', // Mark as completed
                    'notes' => "Alasan: " . $request->reason . ($request->notes ? " | Ket: " . $request->notes : ""),
                    'proof_image' => $photoPathUnit,
                    'selling_price' => $outPrice,
                    'total_amount' => $outPrice,
                    'transaction_pin' => $request->transaction_pin,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'online_shop_id' => $targetUser->online_shop_id ?? $user->online_shop_id,
                    'payment_method_id' => $request->payment_method_id,
                    'split_payments' => $processedSplits,
                ]);

                // Attach outgoing unit
                $outQty = $request->outgoing_quantity ?? 1;
                if ($outgoingUnit->imei) {
                    $stockOut->items()->attach($request->outgoing_product_detail_id, [
                        'selling_price' => $outPrice,
                        'item_discount' => 0,
                    ]);
                    $outgoingUnit->update([
                        'status' => 'sold',
                        'notes' => ($outgoingUnit->notes ? $outgoingUnit->notes . "\n" : "") . "Keluar melalui Tukar Unit: " . $receiptId
                    ]);
                } else {
                    \App\Models\StockOutNonHpItem::create([
                        'stock_out_id' => $stockOut->id,
                        'product_id' => $outgoingUnit->product_id,
                        'quantity' => $outQty,
                        'selling_price' => $outPrice,
                        'distributor_id' => $outgoingUnit->distributor_id
                    ]);
                    // Decrement from Inventory
                    $inventoryOut = \App\Models\Inventory::where([
                        'product_id' => $outgoingUnit->product_id,
                        'placement_type' => $outgoingUnit->placement_type,
                        'placement_id' => $outgoingUnit->placement_id,
                        'user_id' => $inventoryUserId
                    ])->first();
                    if ($inventoryOut) $inventoryOut->decrement('quantity', $outQty);
                }

                // 6. Log both movements
                // In Log
                InventoryLog::create([
                    'product_id' => $product->id,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'user_id' => $inventoryUserId,
                    'type' => 'in',
                    'quantity' => $inQty,
                    'reference_id' => $isImei && isset($productDetail) ? (string)$productDetail->id : ('Exchange IN: ' . $receiptId),
                    'description' => 'Tukar Unit (Masuk): ' . $incomingProductType->name . ($request->incoming_imei ? ' (' . $request->incoming_imei . ')' : ''),
                    'supplier_name' => 'Exchange Customer',
                    'distributor_id' => $request->distributor_id,
                    'notes' => 'Exchange IN: ' . $receiptId . ($request->notes ? ' | ' . $request->notes : ''),
                ]);

                // Out Log
                InventoryLog::create([
                    'product_id' => $outgoingUnit->product_id,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'user_id' => $inventoryUserId,
                    'type' => 'out',
                    'quantity' => $outQty,
                    'reference_id' => $receiptId,
                    'description' => 'Tukar Unit (Keluar): ' . ($outgoingUnit->product->name ?? 'Unknown') . ($outgoingUnit->imei ? ' (' . $outgoingUnit->imei . ')' : ''),
                    'distributor_id' => $outgoingUnit->distributor_id,
                ]);

                $msg = 'Tukar unit berhasil diproses.';
                if ($imeiExisted) {
                    $msg .= " (Pemberitahuan: IMEI sudah ada di database sebelumnya dengan status: {$imeiStatus})";
                }
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'data' => $exchange->load('incomingProductType.brand', 'distributor')
                ]);
            });
        } catch (\Exception $e) {
            // Delete uploaded files on failure
            if (isset($photoPathUnit))
                Storage::disk('public')->delete($photoPathUnit);
            if (isset($photoPathCustomer))
                Storage::disk('public')->delete($photoPathCustomer);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses tukar unit: ' . $e->getMessage()
            ], 500);
        }
    }
}
