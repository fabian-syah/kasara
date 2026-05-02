<?php

namespace App\Http\Controllers;

use App\Models\Refund;
use App\Models\StockOut;
use App\Models\ProductDetail;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Traits\VerifiesPin;

class RefundController extends Controller
{
    use VerifiesPin;
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'distributor_id' => 'nullable|exists:distributors,id',
            'brand_id' => 'required|exists:brands,id',
            'product_type_id' => 'required|exists:product_types,id',
            'imei' => 'nullable|string|max:25',
            'quantity' => 'nullable|integer|min:1',
            'storage' => 'nullable|string|max:20',
            'condition' => 'required|in:new,second,ex_ibox',
            'refund_price' => 'required|numeric|min:0',
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
                $photoLog = [];
                if ($request->hasFile('photo_unit')) {
                    $pathUnit = $request->file('photo_unit')->store('refunds/units', 'public');
                    $photoLog['unit'] = $pathUnit;
                }
                if ($request->hasFile('photo_customer')) {
                    $pathCustomer = $request->file('photo_customer')->store('refunds/customers', 'public');
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
                    $invUser = \App\Models\User::where('name', $request->sales_account)->first();
                    if ($invUser) $inventoryUserId = $invUser->id;
                }
                $inventoryUserId = $inventoryUserId ?? $user->id;
                
                $targetUser = \App\Models\User::find($inventoryUserId);
                $branchId = $targetUser->branch_id ?? $user->branch_id;
                $warehouseId = $targetUser->warehouse_id ?? $user->warehouse_id;
                $onlineShopId = $targetUser->online_shop_id ?? $user->online_shop_id;

                $receiptId = Refund::generateReceiptId();
                $placementType = $branchId ? 'branch' : ($warehouseId ? 'warehouse' : 'distributor');
                $placementId = $branchId ?? ($warehouseId ?? $targetUser->distributor_id);

                // Create Refund record
                $refund = Refund::create([
                    'receipt_id' => $receiptId,
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'product_type_id' => $request->product_type_id,
                    'imei' => $request->imei,
                    'storage' => $request->storage,
                    'condition' => $request->condition,
                    'refund_price' => $request->refund_price,
                    'payment_method_id' => $request->payment_method_id,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                    'photo_unit' => $photoLog['unit'] ?? null,
                    'photo_customer' => $photoLog['customer'] ?? null,
                    'user_id' => $user->id,
                    'inventory_user_id' => $inventoryUserId,
                    'distributor_id' => $request->distributor_id,
                    'branch_id' => $branchId,
                    'quantity' => $request->quantity ?? 1,
                ]);

                // 3. Add to Inventory
                if ($isImei) {
                    // Check duplicate IMEI in inventory
                    if ($request->imei && \App\Models\ProductDetail::where('imei', $request->imei)->exists()) {
                        throw new \Exception("IMEI " . $request->imei . " sudah ada di inventory.");
                    }

                    $productDetail = ProductDetail::create([
                        'product_id' => $product->id,
                        'user_id' => $inventoryUserId,
                        'imei' => $request->imei,
                        'storage' => $request->storage,
                        'condition' => $request->condition,
                        'status' => 'available',
                        'placement_type' => $placementType,
                        'placement_id' => $placementId,
                        'cost_price' => $request->refund_price,
                        'selling_price' => $productType->price ?? 0,
                        'supplier_name' => 'Refund: ' . $request->customer_name,
                        'distributor_id' => $request->distributor_id,
                        'refund_id' => $refund->id,
                        'notes' => $request->notes,
                    ]);
                } else {
                    // For non-HP, use Inventory model
                    $inventory = \App\Models\Inventory::firstOrCreate(
                        [
                            'product_id' => $product->id,
                            'placement_type' => $placementType,
                            'placement_id' => $placementId,
                            'user_id' => $inventoryUserId
                        ],
                        ['quantity' => 0]
                    );
                    $inventory->increment('quantity', $request->quantity ?? 1);
                }

                // 4. Log the Inventory Entry
                $qty = $request->quantity ?? 1;
                InventoryLog::create([
                    'product_id' => $product->id,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'online_shop_id' => $onlineShopId,
                    'user_id' => $inventoryUserId,
                    'type' => 'in',
                    'quantity' => $qty,
                    'reference_id' => 'Refund: ' . $receiptId,
                    'description' => 'Refund Barang: ' . $productType->name . ($request->imei ? ' (' . $request->imei . ')' : ''),
                    'supplier_name' => 'Refund Customer',
                    'distributor_id' => $request->distributor_id,
                    'notes' => $request->notes,
                ]);

                // 5. Create StockOut record to ensure visibility in Cek Penjualan
                $negRefund = -abs((float)$request->refund_price * $qty);
                $stockOut = StockOut::create([
                    'receipt_id' => $receiptId,
                    'category' => 'refund',
                    'reporting_date' => now()->hour < 5 ? now()->subDay()->toDateString() : now()->toDateString(),
                    'customer_name' => $request->customer_name,
                    'customer_phone' => $request->customer_phone,
                    'customer_wa' => $request->customer_phone,
                    'user_id' => $user->id,
                    'inventory_user_id' => $inventoryUserId,
                    'sales_account' => $request->sales_account ?? $targetUser?->name,
                    'status' => 'received',
                    'notes' => "Refund Alasan: " . $request->reason . ($request->notes ? " | Ket: " . $request->notes : ""),
                    'proof_image' => $photoLog['unit'] ?? null,
                    'selling_price' => $negRefund,
                    'total_amount' => $negRefund,
                    'paid' => $negRefund,
                    'transaction_pin' => $request->transaction_pin,
                    'payment_method_id' => $request->payment_method_id,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'online_shop_id' => $onlineShopId,
                    'split_payments' => json_encode([
                        [
                            'payment_method_id' => $request->payment_method_id,
                            'amount' => $negRefund
                        ]
                    ])
                ]);

                if ($isImei) {
                    // Attach the HP detail to StockOut
                    $stockOut->items()->attach($productDetail->id, [
                        'selling_price' => $request->refund_price,
                        'distributor_id' => $request->distributor_id
                    ]);
                } else {
                    // Create relational non-hp detail
                    \App\Models\StockOutNonHpItem::create([
                        'stock_out_id' => $stockOut->id,
                        'product_id' => $product->id,
                        'quantity' => $request->quantity ?? 1,
                        'selling_price' => $request->refund_price,
                        'distributor_id' => $request->distributor_id
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Refund berhasil diproses dan barang kembali ke inventory.',
                    'data' => $refund->load('productType.brand', 'paymentMethod')
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
                'message' => 'Gagal memproses refund: ' . $e->getMessage()
            ], 500);
        }
    }
}
