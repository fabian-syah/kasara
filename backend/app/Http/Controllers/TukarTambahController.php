<?php

namespace App\Http\Controllers;

use App\Models\TukarTambah;
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

class TukarTambahController extends Controller
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

            // Incoming (Barang Masuk) - made nullable to support items JSON
            'incoming_product_type_id' => 'nullable|exists:product_types,id',
            'incoming_imei' => 'nullable|string|max:25',
            'incoming_quantity' => 'nullable|integer|min:1',
            'incoming_storage' => 'nullable|string|max:20',
            'incoming_condition' => 'nullable|in:new,second,ex_ibox',
            'incoming_cost_price' => 'nullable|numeric|min:0',

            // Outgoing (Barang Keluar) - made nullable to support outgoing_items JSON
            'outgoing_product_detail_id' => 'nullable|exists:product_details,id',
            'outgoing_quantity' => 'nullable|integer|min:1',
            'outgoing_price' => 'nullable|numeric|min:0',

            // Multi items
            'items' => 'nullable|string', // JSON array of incoming
            'outgoing_items' => 'nullable|string', // JSON array of outgoing

            // Financials
            'price_difference' => 'required|numeric',
            'payment_method_id' => 'required|exists:payment_methods,id',

            'reason' => 'required|string',
            'notes' => 'nullable|string',
            'photo_unit' => 'required|image|max:20480',
            'photo_customer' => 'nullable|image|max:20480',
            'payment_proof_image' => 'nullable|image|max:20480',
            'transaction_pin' => 'nullable|string',
            'inventory_user_id' => 'nullable|exists:users,id',
            'split_payments' => 'nullable',
        ]);

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError) return $pinError;

        try {
            return DB::transaction(function () use ($request, $user) {
                // 1. Handle File Uploads
                $photoPathUnit = null;
                $photoPathCustomer = null;
                $paymentProofImagePath = null;

                if ($request->hasFile('photo_unit')) {
                    $photoPathUnit = $request->file('photo_unit')->store('tukar-tambah/units', 'public');
                }
                if ($request->hasFile('photo_customer')) {
                    $photoPathCustomer = $request->file('photo_customer')->store('tukar-tambah/customers', 'public');
                }
                if ($request->hasFile('payment_proof_image')) {
                    $paymentProofImagePath = $request->file('payment_proof_image')->store('tukar-tambah/payment-proofs', 'public');
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

                $diff = (float)$request->price_difference;
                $negDiff = -abs($diff);

                $rawSplits = $request->filled('split_payments')
                    ? (is_string($request->split_payments) ? json_decode($request->split_payments, true) : $request->split_payments)
                    : null;

                $processedSplits = null;
                if ($rawSplits && is_array($rawSplits)) {
                    $processedSplits = [];
                    foreach ($rawSplits as $sp) {
                        $processedSplits[] = [
                            'payment_method_id' => $sp['payment_method_id'],
                            'amount' => -abs((float)$sp['amount'])
                        ];
                    }
                } else {
                    $processedSplits = [
                        [
                            'payment_method_id' => $request->payment_method_id,
                            'amount' => $negDiff
                        ]
                    ];
                }

                $receiptId = TukarTambah::generateReceiptId();

                // Decode incoming and outgoing items
                $incomingItemsRaw = $request->items ? json_decode($request->items, true) : [];
                $outgoingItemsRaw = $request->outgoing_items ? json_decode($request->outgoing_items, true) : [];

                // Fallback to scalar inputs if arrays are empty
                if (empty($incomingItemsRaw) && $request->incoming_product_type_id) {
                    $incomingItemsRaw[] = [
                        'product_type_id' => $request->incoming_product_type_id,
                        'imeis' => $request->incoming_imei ? [$request->incoming_imei] : [],
                        'storage' => $request->incoming_storage,
                        'condition' => $request->incoming_condition,
                        'buy_price' => $request->incoming_cost_price,
                        'quantity' => $request->incoming_quantity ?? 1,
                        'distributor_id' => $request->distributor_id,
                    ];
                }

                if (empty($outgoingItemsRaw) && $request->outgoing_product_detail_id) {
                    $outgoingItemsRaw[] = [
                        'product_detail_id' => $request->outgoing_product_detail_id,
                        'quantity' => $request->outgoing_quantity ?? 1,
                        'price' => $request->outgoing_price,
                    ];
                }

                // Prepare scalar fallback for database
                $firstIn = $incomingItemsRaw[0] ?? null;
                $firstOut = $outgoingItemsRaw[0] ?? null;

                // 2. Create Tukar Tambah record
                $tukarTambah = TukarTambah::create([
                    'receipt_id' => $receiptId,
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'incoming_source' => $request->incoming_source,
                    
                    // Legacy scalar fallback
                    'incoming_product_type_id' => $firstIn['product_type_id'] ?? $request->incoming_product_type_id,
                    'incoming_imei' => $firstIn['imeis'][0] ?? $request->incoming_imei,
                    'incoming_storage' => $firstIn['storage'] ?? $request->incoming_storage,
                    'incoming_condition' => $firstIn['condition'] ?? $request->incoming_condition ?? 'new',
                    'incoming_cost_price' => collect($incomingItemsRaw)->sum(fn($i) => ($i['buy_price'] ?? 0) * ($i['quantity'] ?? 1)),
                    
                    'outgoing_product_detail_id' => $firstOut['product_detail_id'] ?? $request->outgoing_product_detail_id,
                    'outgoing_price' => collect($outgoingItemsRaw)->sum(fn($o) => ($o['price'] ?? 0) * ($o['quantity'] ?? 1)),
                    
                    // New JSON columns
                    'incoming_items' => $incomingItemsRaw,
                    'outgoing_items' => $outgoingItemsRaw,

                    'price_difference' => $request->price_difference,
                    'payment_method_id' => $request->payment_method_id,
                    'split_payments' => $processedSplits,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                    'photo_unit' => $photoPathUnit,
                    'photo_customer' => $photoPathCustomer,
                    'user_id' => $user->id,
                    'inventory_user_id' => $inventoryUserId,
                    'distributor_id' => $request->distributor_id,
                    'branch_id' => $branchId,
                    'incoming_quantity' => collect($incomingItemsRaw)->sum('quantity'),
                    'outgoing_quantity' => collect($outgoingItemsRaw)->sum('quantity'),
                ]);

                $placementType = $branchId ? 'branch' : ($warehouseId ? 'warehouse' : 'distributor');
                $placementId = $branchId ?? ($warehouseId ?? $targetUser->distributor_id);

                $imeiExistedAny = false;

                // PROCESS INCOMING ITEMS
                foreach ($incomingItemsRaw as $inItem) {
                    $inQty = $inItem['quantity'] ?? 1;
                    $incomingProductType = ProductType::with('brand')->findOrFail($inItem['product_type_id']);
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

                    $distId = $inItem['distributor_id'] ?? $request->distributor_id;

                    if ($isImei) {
                        $imeis = !empty($inItem['imeis']) ? $inItem['imeis'] : [null]; // fallback if empty
                        foreach ($imeis as $imei) {
                            $existingPd = $imei ? ProductDetail::where('imei', $imei)->first() : null;
                            if ($existingPd) {
                                $imeiExistedAny = true;
                                $existingPd->update([
                                    'product_id' => $product->id,
                                    'user_id' => $inventoryUserId,
                                    'storage' => $inItem['storage'] ?? null,
                                    'condition' => $inItem['condition'] ?? 'new',
                                    'status' => 'available',
                                    'placement_type' => $placementType,
                                    'placement_id' => $placementId,
                                    'cost_price' => $inItem['buy_price'] ?? 0,
                                    'selling_price' => $incomingProductType->price ?? 0,
                                    'supplier_name' => 'Tukar Tambah: ' . $request->customer_name,
                                    'distributor_id' => $distId,
                                    'tukar_tambah_id' => $tukarTambah->id,
                                    'notes' => 'Masuk dari Tukar Tambah (Update): ' . $receiptId,
                                ]);
                                $pd = $existingPd;
                            } else {
                                $pd = ProductDetail::create([
                                    'product_id' => $product->id,
                                    'user_id' => $inventoryUserId,
                                    'imei' => $imei,
                                    'storage' => $inItem['storage'] ?? null,
                                    'condition' => $inItem['condition'] ?? 'new',
                                    'status' => 'available',
                                    'placement_type' => $placementType,
                                    'placement_id' => $placementId,
                                    'cost_price' => $inItem['buy_price'] ?? 0,
                                    'selling_price' => $incomingProductType->price ?? 0,
                                    'supplier_name' => 'Tukar Tambah: ' . $request->customer_name,
                                    'distributor_id' => $distId,
                                    'tukar_tambah_id' => $tukarTambah->id,
                                    'notes' => 'Masuk dari Tukar Tambah: ' . $receiptId,
                                ]);
                            }

                            // Log Movement
                            InventoryLog::create([
                                'product_id' => $product->id,
                                'branch_id' => $branchId,
                                'warehouse_id' => $warehouseId,
                                'user_id' => $inventoryUserId,
                                'type' => 'in',
                                'quantity' => 1,
                                'reference_id' => (string)$pd->id,
                                'description' => 'Tukar Tambah (Masuk): ' . $incomingProductType->name . ($imei ? ' (' . $imei . ')' : ''),
                                'supplier_name' => 'Customer: ' . $request->customer_name,
                                'distributor_id' => $distId,
                                'notes' => 'Tukar Tambah IN: ' . $receiptId,
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

                        // Log Movement
                        InventoryLog::create([
                            'product_id' => $product->id,
                            'branch_id' => $branchId,
                            'warehouse_id' => $warehouseId,
                            'user_id' => $inventoryUserId,
                            'type' => 'in',
                            'quantity' => $inQty,
                            'reference_id' => 'TT IN: ' . $receiptId,
                            'description' => 'Tukar Tambah (Masuk): ' . $incomingProductType->name,
                            'supplier_name' => 'Customer: ' . $request->customer_name,
                            'distributor_id' => $distId,
                            'notes' => 'Tukar Tambah IN: ' . $receiptId,
                        ]);
                    }
                }


                // 4. Create StockOut record (This represents the "Omset" part)
                $stockOut = StockOut::create([
                    'receipt_id' => $receiptId,
                    'category' => 'tukar_tambah',
                    'reporting_date' => now()->hour < 5 ? now()->subDay()->toDateString() : now()->toDateString(),
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'customer_wa' => $request->customer_phone,
                    'user_id' => $user->id,
                    'inventory_user_id' => $inventoryUserId,
                    'sales_account' => $request->sales_account ?? $targetUser?->name,
                    'status' => 'received',
                    'notes' => "Alasan: " . $request->reason . ($request->notes ? " | Ket: " . $request->notes : ""),
                    'proof_image' => $photoPathUnit,
                    'payment_proof_image' => $paymentProofImagePath,
                    'selling_price' => $negDiff,
                    'total_amount' => $negDiff,
                    'paid' => $negDiff,
                    'payment_method_id' => $request->payment_method_id,
                    'transaction_pin' => $request->transaction_pin,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'online_shop_id' => $targetUser->online_shop_id ?? $user->online_shop_id,
                    'split_payments' => $processedSplits
                ]);

                // PROCESS OUTGOING ITEMS
                foreach ($outgoingItemsRaw as $outItem) {
                    $outQty = $outItem['quantity'] ?? 1;
                    $isHp = isset($outItem['is_hp']) ? $outItem['is_hp'] : true;
                    
                    if ($isHp) {
                        $outgoingUnit = ProductDetail::findOrFail($outItem['product_detail_id']);
                        
                        $stockOut->items()->attach($outItem['product_detail_id'], [
                            'selling_price' => $outItem['price'],
                            'item_discount' => 0,
                        ]);
                        $outgoingUnit->update([
                            'status' => 'sold',
                            'notes' => ($outgoingUnit->notes ? $outgoingUnit->notes . "\n" : "") . "Keluar melalui Tukar Tambah: " . $receiptId
                        ]);

                        // Log Movement
                        InventoryLog::create([
                            'product_id' => $outgoingUnit->product_id,
                            'branch_id' => $branchId,
                            'warehouse_id' => $warehouseId,
                            'user_id' => $inventoryUserId,
                            'type' => 'out',
                            'quantity' => $outQty,
                            'reference_id' => $receiptId,
                            'description' => 'Tukar Tambah (Keluar): ' . ($outgoingUnit->product->name ?? 'Unknown') . ($outgoingUnit->imei ? ' (' . $outgoingUnit->imei . ')' : ''),
                            'distributor_id' => $outgoingUnit->distributor_id,
                        ]);
                    } else {
                        $inventoryOut = \App\Models\Inventory::with('product')->findOrFail($outItem['product_detail_id']);
                        
                        \App\Models\StockOutNonHpItem::create([
                            'stock_out_id' => $stockOut->id,
                            'product_id' => $inventoryOut->product_id,
                            'quantity' => $outQty,
                            'selling_price' => $outItem['price'],
                            'distributor_id' => $inventoryOut->distributor_id
                        ]);
                        
                        // Decrement from Inventory
                        $inventoryOut->decrement('quantity', $outQty);

                        // Log Movement
                        InventoryLog::create([
                            'product_id' => $inventoryOut->product_id,
                            'branch_id' => $branchId,
                            'warehouse_id' => $warehouseId,
                            'user_id' => $inventoryUserId,
                            'type' => 'out',
                            'quantity' => $outQty,
                            'reference_id' => $receiptId,
                            'description' => 'Tukar Tambah (Keluar): ' . ($inventoryOut->product->name ?? 'Unknown'),
                            'distributor_id' => $inventoryOut->distributor_id,
                        ]);
                    }
                }

                $msg = 'Tukar tambah berhasil diproses.';
                if ($imeiExistedAny) {
                    $msg .= " (Pemberitahuan: Beberapa IMEI sudah ada di database sebelumnya, dan datanya telah diupdate.)";
                }
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'data' => $tukarTambah->load('incomingProductType.brand', 'distributor')
                ]);
            });
        } catch (\Exception $e) {
            if (isset($photoPathUnit))
                Storage::disk('public')->delete($photoPathUnit);
            if (isset($photoPathCustomer))
                Storage::disk('public')->delete($photoPathCustomer);
            if (isset($paymentProofImagePath))
                Storage::disk('public')->delete($paymentProofImagePath);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses tukar tambah: ' . $e->getMessage()
            ], 500);
        }
    }
}
