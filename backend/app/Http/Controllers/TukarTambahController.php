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
        $user = Auth::user();

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'distributor_id' => 'nullable|exists:distributors,id',
            'incoming_source' => 'required|in:ex_pstore,luar_pstore',

            // Incoming (Barang Masuk)
            'incoming_product_type_id' => 'required|exists:product_types,id',
            'incoming_imei' => 'nullable|string|max:25',
            'incoming_storage' => 'nullable|string|max:20',
            'incoming_condition' => 'required|in:new,second,ex_ibox',
            'incoming_cost_price' => 'required|numeric|min:0',

            // Outgoing (Barang Keluar)
            'outgoing_product_detail_id' => 'required|exists:product_details,id',
            'outgoing_price' => 'required|numeric|min:0',

            // Financials
            'price_difference' => 'required|numeric',
            'payment_method_id' => 'required|exists:payment_methods,id',

            'reason' => 'required|string',
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
                $photoPathUnit = null;
                $photoPathCustomer = null;

                if ($request->hasFile('photo_unit')) {
                    $photoPathUnit = $request->file('photo_unit')->store('tukar-tambah/units', 'public');
                }
                if ($request->hasFile('photo_customer')) {
                    $photoPathCustomer = $request->file('photo_customer')->store('tukar-tambah/customers', 'public');
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

                $receiptId = TukarTambah::generateReceiptId();

                // 2. Create Tukar Tambah record
                $tukarTambah = TukarTambah::create([
                    'receipt_id' => $receiptId,
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'incoming_source' => $request->incoming_source,
                    'incoming_product_type_id' => $request->incoming_product_type_id,
                    'incoming_imei' => $request->incoming_imei,
                    'incoming_storage' => $request->incoming_storage,
                    'incoming_condition' => $request->incoming_condition,
                    'incoming_cost_price' => $request->incoming_cost_price,
                    'outgoing_product_detail_id' => $request->outgoing_product_detail_id,
                    'outgoing_price' => $request->outgoing_price,
                    'price_difference' => $request->price_difference,
                    'payment_method_id' => $request->payment_method_id,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                    'photo_unit' => $photoPathUnit,
                    'photo_customer' => $photoPathCustomer,
                    'user_id' => $user->id,
                    'inventory_user_id' => $inventoryUserId,
                    'distributor_id' => $request->distributor_id,
                    'branch_id' => $branchId,
                ]);

                // 3. Handle Incoming Unit (Entry to Inventory)
                $incomingProductType = ProductType::with('brand')->findOrFail($request->incoming_product_type_id);
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

                $placementType = $branchId ? 'branch' : ($warehouseId ? 'warehouse' : 'distributor');
                $placementId = $branchId ?? ($warehouseId ?? $targetUser->distributor_id);

                ProductDetail::create([
                    'product_id' => $product->id,
                    'user_id' => $inventoryUserId,
                    'imei' => $request->incoming_imei,
                    'storage' => $request->incoming_storage,
                    'condition' => $request->incoming_condition,
                    'status' => 'available',
                    'placement_type' => $placementType,
                    'placement_id' => $placementId,
                    'cost_price' => $request->incoming_cost_price,
                    'selling_price' => $incomingProductType->price ?? 0,
                    'supplier_name' => 'Tukar Tambah: ' . $request->customer_name,
                    'distributor_id' => $request->distributor_id,
                    'tukar_tambah_id' => $tukarTambah->id,
                    'notes' => 'Masuk dari Tukar Tambah: ' . $receiptId,
                ]);

                // 4. Create StockOut record (This represents the "Omset" part)
                $diff = (float)$request->price_difference;
                $negDiff = -abs($diff);

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
                    'selling_price' => $negDiff,
                    'total_amount' => $negDiff,
                    'paid' => $negDiff,
                    'payment_method_id' => $request->payment_method_id,
                    'transaction_pin' => $request->transaction_pin,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'online_shop_id' => $targetUser->online_shop_id ?? $user->online_shop_id,
                    'split_payments' => json_encode([
                        [
                            'payment_method_id' => $request->payment_method_id,
                            'amount' => $negDiff
                        ]
                    ])
                ]);

                // Attach the outgoing unit to the StockOut record
                $stockOut->items()->attach($request->outgoing_product_detail_id, [
                    'selling_price' => $request->outgoing_price,
                    'item_discount' => 0,
                ]);

                // 5. Handle Outgoing Unit (Exit from Inventory)
                $outgoingUnit = ProductDetail::findOrFail($request->outgoing_product_detail_id);
                $outgoingUnit->update([
                    'status' => 'sold',
                    'notes' => ($outgoingUnit->notes ? $outgoingUnit->notes . "\n" : "") . "Keluar melalui Tukar Tambah: " . $receiptId
                ]);

                // 6. Log Movements
                InventoryLog::create([
                    'product_id' => $product->id,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'user_id' => $inventoryUserId,
                    'type' => 'in',
                    'quantity' => 1,
                    'reference_id' => 'TT IN: ' . $receiptId,
                    'description' => 'Tukar Tambah (Masuk): ' . $incomingProductType->name,
                    'supplier_name' => 'Customer: ' . $request->customer_name,
                    'distributor_id' => $request->distributor_id,
                ]);

                InventoryLog::create([
                    'product_id' => $outgoingUnit->product_id,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'user_id' => $inventoryUserId,
                    'type' => 'out',
                    'quantity' => 1,
                    'reference_id' => 'TT OUT: ' . $receiptId,
                    'description' => 'Tukar Tambah (Keluar): ' . ($outgoingUnit->product->name ?? 'Unknown'),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Tukar tambah berhasil diproses.',
                    'data' => $tukarTambah->load('incomingProductType.brand')
                ]);
            });
        } catch (\Exception $e) {
            if (isset($photoPathUnit))
                Storage::disk('public')->delete($photoPathUnit);
            if (isset($photoPathCustomer))
                Storage::disk('public')->delete($photoPathCustomer);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses tukar tambah: ' . $e->getMessage()
            ], 500);
        }
    }
}
