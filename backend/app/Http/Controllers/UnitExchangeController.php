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
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'distributor_id' => 'nullable|exists:distributors,id',
            'incoming_source' => 'required|in:ex_pstore,luar_pstore',

            // Single incoming (legacy)
            'incoming_product_type_id' => 'required_without:incoming_items|nullable|exists:product_types,id',
            'incoming_imei' => 'nullable|string|max:25',
            'incoming_quantity' => 'nullable|integer|min:1',
            'incoming_storage' => 'nullable|string|max:20',
            'incoming_condition' => 'nullable|in:new,second,ex_ibox',
            'incoming_cost_price' => 'required_without:incoming_items|nullable|numeric|min:0',

            // Multi incoming items
            'incoming_items' => 'nullable|array',
            'incoming_items.*.product_type_id' => 'required_with:incoming_items|exists:product_types,id',
            'incoming_items.*.imei' => 'nullable|string|max:25',
            'incoming_items.*.quantity' => 'nullable|integer|min:1',
            'incoming_items.*.storage' => 'nullable|string|max:20',
            'incoming_items.*.condition' => 'nullable|in:new,second,ex_ibox',
            'incoming_items.*.cost_price' => 'required_with:incoming_items|numeric|min:0',
            'incoming_items.*.distributor_id' => 'nullable|exists:distributors,id',

            // Single outgoing (legacy)
            'outgoing_product_detail_id' => 'required_without:outgoing_product_detail_ids|nullable|exists:product_details,id',
            'outgoing_quantity' => 'nullable|integer|min:1',
            'outgoing_price' => 'nullable|numeric|min:0',

            // Multi outgoing items
            'outgoing_product_detail_ids' => 'nullable|array',
            'outgoing_product_detail_ids.*' => 'required_with:outgoing_product_detail_ids|exists:product_details,id',
            'outgoing_prices' => 'nullable|array',
            'outgoing_prices.*' => 'nullable|numeric|min:0',

            'reason' => 'required|string',
            'notes' => 'nullable|string',
            'photo_unit' => 'required|image|max:20480',
            'photo_customer' => 'nullable|image|max:20480',
            'transaction_pin' => 'nullable|string',
            'inventory_user_id' => 'nullable|exists:users,id',
            'split_payments' => 'nullable',
            'payment_method_id' => 'nullable|exists:payment_methods,id',

            // Non-HP items support
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
                    'incoming_product_type_id' => $request->incoming_product_type_id ?? ($request->incoming_items[0]['product_type_id'] ?? null),
                    'incoming_imei' => $request->incoming_imei ?? ($request->incoming_items[0]['imei'] ?? null),
                    'incoming_storage' => $request->incoming_storage ?? ($request->incoming_items[0]['storage'] ?? null),
                    'incoming_condition' => $request->incoming_condition ?? ($request->incoming_items[0]['condition'] ?? 'new'),
                    'incoming_cost_price' => $request->incoming_cost_price ?? ($request->incoming_items[0]['cost_price'] ?? 0),
                    'outgoing_product_detail_id' => $request->outgoing_product_detail_id ?? ($request->outgoing_product_detail_ids[0] ?? null),
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

                // Build incoming items list
                $incomingList = [];
                if ($request->has('incoming_items') && is_array($request->incoming_items) && count($request->incoming_items) > 0) {
                    $incomingList = $request->incoming_items;
                } else {
                    $incomingList = [[
                        'product_type_id' => $request->incoming_product_type_id,
                        'imei' => $request->incoming_imei,
                        'quantity' => $request->incoming_quantity ?? 1,
                        'storage' => $request->incoming_storage,
                        'condition' => $request->incoming_condition ?? 'new',
                        'cost_price' => $request->incoming_cost_price,
                        'distributor_id' => $request->distributor_id,
                    ]];
                }

                // Build outgoing items list
                $outgoingIds = [];
                $outgoingPrices = [];
                if ($request->has('outgoing_product_detail_ids') && is_array($request->outgoing_product_detail_ids)) {
                    $outgoingIds = $request->outgoing_product_detail_ids;
                    $outgoingPrices = $request->outgoing_prices ?? [];
                } else if ($request->outgoing_product_detail_id) {
                    $outgoingIds = [$request->outgoing_product_detail_id];
                    $outgoingPrices = [$request->outgoing_price ?? 0];
                }

                $placementType = $branchId ? 'branch' : ($warehouseId ? 'warehouse' : 'distributor');
                $placementId = $branchId ?? ($warehouseId ?? $targetUser->distributor_id);

                // 3. Process all INCOMING items
                $allIncomingPdIds = [];
                $imeiWarnings = [];
                $totalOutPrice = 0;

                foreach ($incomingList as $incItem) {
                    $incomingProductType = ProductType::with('brand')->findOrFail($incItem['product_type_id']);
                    $isImei = in_array(strtolower($incomingProductType->category), ['imei', 'hp / gadget', 'hp/gadget']);

                    $product = Product::firstOrCreate(
                        ['name' => $incomingProductType->name, 'brand' => $incomingProductType->brand->name],
                        [
                            'type' => $isImei ? 'hp' : 'non-hp',
                            'has_imei' => $isImei,
                            'is_active' => true,
                            'sku' => ($isImei ? 'HP-' : 'ACC-') . strtoupper(Str::random(8))
                        ]
                    );

                    $incImei = $incItem['imei'] ?? null;
                    $incStorage = $incItem['storage'] ?? null;
                    $incCondition = $incItem['condition'] ?? 'new';
                    $incCostPrice = (float)($incItem['cost_price'] ?? 0);
                    $incDistributorId = $incItem['distributor_id'] ?? $request->distributor_id;
                    $inQty = (int)($incItem['quantity'] ?? 1);

                    if ($isImei && $incImei) {
                        $existingPd = ProductDetail::where('imei', $incImei)->first();
                        if ($existingPd) {
                            $imeiWarnings[] = "$incImei ({$existingPd->status})";
                            $existingPd->update([
                                'product_id' => $product->id,
                                'user_id' => $inventoryUserId,
                                'storage' => $incStorage,
                                'condition' => $incCondition,
                                'status' => 'available',
                                'placement_type' => $placementType,
                                'placement_id' => $placementId,
                                'cost_price' => $incCostPrice,
                                'selling_price' => $incomingProductType->price ?? 0,
                                'supplier_name' => 'Exchange: ' . $request->customer_name,
                                'distributor_id' => $incDistributorId,
                                'unit_exchange_id' => $exchange->id,
                                'notes' => 'Masuk dari Tukar Unit (Update): ' . $receiptId,
                            ]);
                            $productDetail = $existingPd;
                        } else {
                            $productDetail = ProductDetail::create([
                                'product_id' => $product->id,
                                'user_id' => $inventoryUserId,
                                'imei' => $incImei,
                                'storage' => $incStorage,
                                'condition' => $incCondition,
                                'status' => 'available',
                                'placement_type' => $placementType,
                                'placement_id' => $placementId,
                                'cost_price' => $incCostPrice,
                                'selling_price' => $incomingProductType->price ?? 0,
                                'supplier_name' => 'Exchange: ' . $request->customer_name,
                                'distributor_id' => $incDistributorId,
                                'unit_exchange_id' => $exchange->id,
                                'notes' => 'Masuk dari Tukar Unit: ' . $receiptId,
                            ]);
                        }
                        $allIncomingPdIds[] = $productDetail->id;

                        InventoryLog::create([
                            'product_id' => $product->id,
                            'branch_id' => $branchId,
                            'warehouse_id' => $warehouseId,
                            'user_id' => $inventoryUserId,
                            'type' => 'in',
                            'quantity' => 1,
                            'reference_id' => (string)$productDetail->id,
                            'description' => 'Tukar Unit (Masuk): ' . $incomingProductType->name . ' (' . $incImei . ')',
                            'supplier_name' => 'Exchange Customer',
                            'distributor_id' => $incDistributorId,
                            'notes' => 'Exchange IN: ' . $receiptId,
                        ]);
                    } else {
                        // Non-HP incoming
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

                        InventoryLog::create([
                            'product_id' => $product->id,
                            'branch_id' => $branchId,
                            'warehouse_id' => $warehouseId,
                            'user_id' => $inventoryUserId,
                            'type' => 'in',
                            'quantity' => $inQty,
                            'reference_id' => 'Exchange IN: ' . $receiptId,
                            'description' => 'Tukar Unit (Masuk Non-HP): ' . $incomingProductType->name,
                            'supplier_name' => 'Exchange Customer',
                            'distributor_id' => $incDistributorId,
                            'notes' => 'Exchange IN: ' . $receiptId,
                        ]);
                    }
                }

                // Process non_hp_items (accessories)
                if ($request->has('non_hp_items') && is_array($request->non_hp_items)) {
                    foreach ($request->non_hp_items as $nhpItem) {
                        $nhpName = $nhpItem['name'] ?? 'Accessories';
                        $nhpQty = (int)($nhpItem['quantity'] ?? 1);

                        $nhpProduct = Product::firstOrCreate(
                            ['name' => $nhpName, 'type' => 'non-hp'],
                            ['brand' => 'Accessories', 'has_imei' => false, 'is_active' => true, 'sku' => 'ACC-' . strtoupper(Str::random(8))]
                        );

                        $inventory = \App\Models\Inventory::firstOrCreate(
                            ['product_id' => $nhpProduct->id, 'placement_type' => $placementType, 'placement_id' => $placementId, 'user_id' => $inventoryUserId],
                            ['quantity' => 0]
                        );
                        $inventory->increment('quantity', $nhpQty);

                        InventoryLog::create([
                            'product_id' => $nhpProduct->id,
                            'branch_id' => $branchId,
                            'warehouse_id' => $warehouseId,
                            'user_id' => $inventoryUserId,
                            'type' => 'in',
                            'quantity' => $nhpQty,
                            'reference_id' => 'Exchange-NHP: ' . $receiptId,
                            'description' => 'Tukar Unit Acc (Masuk): ' . $nhpName,
                            'supplier_name' => 'Exchange Customer',
                            'notes' => 'Exchange IN: ' . $receiptId,
                        ]);
                    }
                }

                // 4. Process all OUTGOING items + Create StockOut
                $firstOutgoing = !empty($outgoingIds) ? ProductDetail::findOrFail($outgoingIds[0]) : null;
                $defaultOutPrice = $firstOutgoing?->selling_price ?? 0;

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
                    'status' => 'received',
                    'notes' => "Alasan: " . $request->reason . ($request->notes ? " | Ket: " . $request->notes : ""),
                    'proof_image' => $photoPathUnit,
                    'selling_price' => 0, // Will be updated below
                    'total_amount' => 0,
                    'transaction_pin' => $request->transaction_pin,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'online_shop_id' => $targetUser->online_shop_id ?? $user->online_shop_id,
                    'payment_method_id' => $request->payment_method_id,
                    'split_payments' => $processedSplits,
                ]);

                foreach ($outgoingIds as $idx => $outPdId) {
                    $outgoingUnit = ProductDetail::findOrFail($outPdId);
                    $outPrice = (float)($outgoingPrices[$idx] ?? $outgoingUnit->selling_price ?? 0);
                    $totalOutPrice += $outPrice;

                    $stockOut->items()->attach($outPdId, [
                        'selling_price' => $outPrice,
                        'item_discount' => 0,
                    ]);

                    $outgoingUnit->update([
                        'status' => 'sold',
                        'notes' => ($outgoingUnit->notes ? $outgoingUnit->notes . "\n" : "") . "Keluar melalui Tukar Unit: " . $receiptId
                    ]);

                    InventoryLog::create([
                        'product_id' => $outgoingUnit->product_id,
                        'branch_id' => $branchId,
                        'warehouse_id' => $warehouseId,
                        'user_id' => $inventoryUserId,
                        'type' => 'out',
                        'quantity' => 1,
                        'reference_id' => $receiptId,
                        'description' => 'Tukar Unit (Keluar): ' . ($outgoingUnit->product->name ?? 'Unknown') . ($outgoingUnit->imei ? ' (' . $outgoingUnit->imei . ')' : ''),
                        'distributor_id' => $outgoingUnit->distributor_id,
                    ]);
                }

                // Update StockOut total
                $stockOut->update(['selling_price' => $totalOutPrice, 'total_amount' => $totalOutPrice]);

                $msg = 'Tukar unit berhasil diproses.';
                if (!empty($imeiWarnings)) {
                    $msg .= " (Pemberitahuan: IMEI berikut sudah ada sebelumnya: " . implode(', ', $imeiWarnings) . ")";
                }
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'data' => $exchange->load('incomingProductType.brand', 'distributor', 'outgoingProductDetail.product')
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
