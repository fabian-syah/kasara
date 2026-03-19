<?php

namespace App\Http\Controllers;

use App\Models\Downgrade;
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

class DowngradeController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
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
            'photo_unit' => 'nullable|image|max:5120',
            'photo_customer' => 'nullable|image|max:5120',
            'transaction_pin' => 'nullable|string'
        ]);

        try {
            return DB::transaction(function () use ($request, $user) {
                // Pin Verification (if applicable)
                if ($user->role === 'sales' && $user->pin_enabled && $request->transaction_pin !== $user->transaction_pin) {
                    throw new \Exception('PIN Transaksi Salah');
                }

                // 1. Handle File Uploads
                $photoPathUnit = null;
                $photoPathCustomer = null;

                if ($request->hasFile('photo_unit')) {
                    $photoPathUnit = $request->file('photo_unit')->store('downgrades/units', 'public');
                }
                if ($request->hasFile('photo_customer')) {
                    $photoPathCustomer = $request->file('photo_customer')->store('downgrades/customers', 'public');
                }

                $receiptId = Downgrade::generateReceiptId();

                // 2. Create Downgrade record
                $downgrade = Downgrade::create([
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
                    'photo_unit' => $photoPathUnit ?? 'noimage.png',
                    'photo_customer' => $photoPathCustomer,
                    'user_id' => $user->id,
                    'branch_id' => $user->branch_id,
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

                $placementType = $user->branch_id ? 'branch' : ($user->warehouse_id ? 'warehouse' : 'distributor');
                $placementId = $user->branch_id ?? ($user->warehouse_id ?? $user->distributor_id);

                ProductDetail::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'imei' => $request->incoming_imei,
                    'storage' => $request->incoming_storage,
                    'condition' => $request->incoming_condition,
                    'status' => 'available',
                    'placement_type' => $placementType,
                    'placement_id' => $placementId,
                    'cost_price' => $request->incoming_cost_price,
                    'selling_price' => $incomingProductType->price ?? 0,
                    'supplier_name' => 'Downgrade: ' . $request->customer_name,
                    'downgrade_id' => $downgrade->id,
                    'notes' => 'Masuk dari Downgrade: ' . $receiptId,
                ]);

                // 4. Create StockOut record (This represents the "Omset" part)
                $stockOut = StockOut::create([
                    'receipt_id' => $receiptId,
                    'category' => 'downgrade',
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'customer_wa' => $request->customer_phone,
                    'user_id' => $user->id,
                    'inventory_user_id' => $user->id,
                    'status' => 'received',
                    'notes' => "Downgrade Alasan: " . $request->reason . ($request->notes ? " | Ket: " . $request->notes : ""),
                    'proof_image' => $photoPathUnit,
                    'selling_price' => $request->outgoing_price,
                    'total_amount' => $request->outgoing_price,
                    'payment_method_id' => $request->payment_method_id,
                    'transaction_pin' => $request->transaction_pin,
                ]);

                // Attach outgoing unit
                $stockOut->items()->attach($request->outgoing_product_detail_id, [
                    'selling_price' => $request->outgoing_price,
                    'item_discount' => 0,
                ]);

                // 5. Handle Outgoing Unit (Exit from Inventory)
                $outgoingUnit = ProductDetail::findOrFail($request->outgoing_product_detail_id);
                $outgoingUnit->update([
                    'status' => 'sold',
                    'notes' => ($outgoingUnit->notes ? $outgoingUnit->notes . "\n" : "") . "Keluar melalui Downgrade: " . $receiptId
                ]);

                // 6. Log Movements
                InventoryLog::create([
                    'product_id' => $product->id,
                    'branch_id' => $user->branch_id,
                    'user_id' => $user->id,
                    'type' => 'in',
                    'quantity' => 1,
                    'reference_id' => 'DG IN: ' . $receiptId,
                    'description' => 'Downgrade (Masuk): ' . $incomingProductType->name,
                    'supplier_name' => 'Customer: ' . $request->customer_name,
                ]);

                InventoryLog::create([
                    'product_id' => $outgoingUnit->product_id,
                    'branch_id' => $user->branch_id,
                    'user_id' => $user->id,
                    'type' => 'out',
                    'quantity' => 1,
                    'reference_id' => 'DG OUT: ' . $receiptId,
                    'description' => 'Downgrade (Keluar): ' . ($outgoingUnit->product->name ?? 'Unknown'),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Downgrade berhasil diproses.',
                    'data' => $downgrade->load('incomingProductType.brand')
                ]);
            });
        } catch (\Exception $e) {
            if (isset($photoPathUnit))
                Storage::disk('public')->delete($photoPathUnit);
            if (isset($photoPathCustomer))
                Storage::disk('public')->delete($photoPathCustomer);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses downgrade: ' . $e->getMessage()
            ], 500);
        }
    }
}
