<?php

namespace App\Http\Controllers;

use App\Models\TradeIn;
use App\Models\ProductDetail;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TradeInController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'source' => 'required|in:pstore,luar_pstore',
            'brand_id' => 'required|exists:brands,id',
            'product_type_id' => 'required|exists:product_types,id',
            'imei' => 'required|string|max:20|unique:product_details,imei',
            'storage' => 'required|string|max:50',
            'condition' => 'required|in:new,second,ex_ibox',
            'buy_price' => 'required|numeric|min:0',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'photo_unit' => 'required|image|max:5120',
            'photo_customer' => 'nullable|image|max:5120',
        ]);

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
                // Find or create a Product record that matches this ProductType
                $product = \App\Models\Product::firstOrCreate(
                    ['name' => $productType->name, 'brand' => $productType->brand->name],
                    [
                        'type' => 'hp',
                        'has_imei' => true,
                        'is_active' => true,
                        'sku' => 'HP-' . strtoupper(Str::random(8))
                    ]
                );

                // 2. Create TradeIn Record
                $tradeIn = TradeIn::create([
                    'receipt_id' => TradeIn::generateReceiptId(),
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'source' => $request->source,
                    'product_type_id' => $request->product_type_id,
                    'imei' => $request->imei,
                    'ram' => $productType->ram,
                    'storage' => $request->storage,
                    'condition' => $request->condition,
                    'buy_price' => $request->buy_price,
                    'payment_method_id' => $request->payment_method_id,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                    'photo_unit' => $photoLog['unit'] ?? null,
                    'photo_customer' => $photoLog['customer'] ?? null,
                    'user_id' => $user->id,
                    'branch_id' => $user->branch_id,
                ]);

                // 3. Create ProductDetail (Inventory)
                $productDetail = ProductDetail::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'imei' => $request->imei,
                    'ram' => $productType->ram,
                    'storage' => $request->storage,
                    'condition' => $request->condition,
                    'status' => 'available',
                    'placement_type' => $user->branch_id ? 'branch' : ($user->warehouse_id ? 'warehouse' : 'distributor'),
                    'placement_id' => $user->branch_id ?? ($user->warehouse_id ?? $user->distributor_id),
                    'cost_price' => $request->buy_price,
                    'selling_price' => 0,
                    'supplier_name' => 'Trade-In: ' . $request->customer_name,
                    'trade_in_id' => $tradeIn->id,
                    'notes' => $request->notes,
                ]);

                // 4. Create Inventory Log
                InventoryLog::create([
                    'product_id' => $product->id,
                    'branch_id' => $user->branch_id,
                    'warehouse_id' => $user->warehouse_id,
                    'online_shop_id' => $user->online_shop_id,
                    'user_id' => $user->id,
                    'type' => 'in',
                    'quantity' => 1,
                    'reference_id' => 'Trade-In: ' . $tradeIn->receipt_id,
                    'description' => 'Trade-In from ' . $request->customer_name,
                    'supplier_name' => 'Trade-In',
                    'notes' => $request->notes,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Barang angkat berhasil diproses dan masuk inventory.',
                    'data' => $tradeIn->load('productType.brand', 'paymentMethod')
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
