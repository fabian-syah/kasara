<?php

namespace App\Http\Controllers;

use App\Models\DpRefund;
use App\Models\StockOut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Traits\VerifiesPin;

class DpRefundController extends Controller
{
    use VerifiesPin;

    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate([
            'stock_out_id' => 'required|exists:stock_outs,id',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'split_payments' => 'nullable',
            'transaction_pin' => 'nullable|string',
            'inventory_user_id' => 'nullable|exists:users,id',
        ]);

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError) return $pinError;

        try {
            return DB::transaction(function () use ($request, $user) {
                // 1. Find the original DP transaction
                $dpTransaction = StockOut::where('id', $request->stock_out_id)
                    ->where('category', 'dp')
                    ->where('is_dp_settled', false)
                    ->lockForUpdate()
                    ->first();

                if (!$dpTransaction) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Transaksi DP tidak ditemukan atau sudah diselesaikan/di-refund.'
                    ], 422);
                }

                // 2. Calculate refund amount (full refund of DP paid amount)
                $refundAmount = abs($dpTransaction->dp_amount ?: $dpTransaction->selling_price);
                if ($refundAmount <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nominal DP tidak valid untuk di-refund.'
                    ], 422);
                }

                // 3. Resolve inventory user and location
                $inventoryUserId = $request->inventory_user_id ?? $user->id;
                /** @var \App\Models\User|null $targetUser */
                $targetUser = \App\Models\User::find($inventoryUserId);
                $branchId = $targetUser->branch_id ?? $user->branch_id;
                if (!$branchId) {
                    $branchId = $targetUser?->getAccessibleBranchIds()[0] ?? ($user->getAccessibleBranchIds()[0] ?? null);
                }
                $warehouseId = $targetUser->warehouse_id ?? $user->warehouse_id;
                $onlineShopId = $targetUser->online_shop_id ?? $user->online_shop_id;

                // 4. Process split payments
                $negRefund = -abs($refundAmount);
                $rawSplits = $request->filled('split_payments')
                    ? (is_string($request->split_payments) ? json_decode($request->split_payments, true) : $request->split_payments)
                    : null;

                $processedSplits = null;
                if ($rawSplits && is_array($rawSplits)) {
                    $processedSplits = [];
                    foreach ($rawSplits as $sp) {
                        $processedSplits[] = [
                            'payment_method_id' => $sp['payment_method_id'],
                            'amount' => -abs((float) $sp['amount'])
                        ];
                    }
                } else {
                    $processedSplits = [
                        [
                            'payment_method_id' => $request->payment_method_id,
                            'amount' => $negRefund
                        ]
                    ];
                }

                // 5. Generate receipt ID
                $receiptId = DpRefund::generateReceiptId();

                // 6. Create DpRefund record
                $dpRefund = DpRefund::create([
                    'receipt_id' => $receiptId,
                    'stock_out_id' => $dpTransaction->id,
                    'customer_name' => $dpTransaction->customer_name ?? $dpTransaction->customer_wa ?? '-',
                    'customer_phone' => $dpTransaction->customer_wa ?? $dpTransaction->customer_phone,
                    'refund_amount' => $refundAmount,
                    'payment_method_id' => $request->payment_method_id,
                    'split_payments' => $processedSplits,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                    'user_id' => $user->id,
                    'inventory_user_id' => $inventoryUserId,
                    'branch_id' => $branchId,
                ]);

                // 7. Create StockOut record for visibility in Cek Penjualan (negative sale)
                $stockOut = StockOut::create([
                    'receipt_id' => $receiptId,
                    'category' => 'refund_dp',
                    'reporting_date' => now()->hour < 5 ? now()->subDay()->toDateString() : now()->toDateString(),
                    'customer_name' => $dpTransaction->customer_name ?? $dpTransaction->customer_wa,
                    'customer_phone' => $dpTransaction->customer_wa ?? $dpTransaction->customer_phone,
                    'customer_wa' => $dpTransaction->customer_wa ?? $dpTransaction->customer_phone,
                    'user_id' => $user->id,
                    'inventory_user_id' => $inventoryUserId,
                    'sales_account' => $request->sales_account ?? $targetUser?->name,
                    'status' => 'received',
                    'notes' => "Refund DP dari nota {$dpTransaction->receipt_id}. Alasan: {$request->reason}" . ($request->notes ? " | Ket: {$request->notes}" : ""),
                    'selling_price' => $negRefund,
                    'paid_amount' => $negRefund,
                    'transaction_pin' => $request->transaction_pin,
                    'payment_method_id' => $request->payment_method_id,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'online_shop_id' => $onlineShopId,
                    'split_payments' => $processedSplits,
                    'parent_dp_id' => $dpTransaction->id,
                ]);

                // 8. Mark original DP as settled
                $dpTransaction->update([
                    'is_dp_settled' => true,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Refund DP berhasil diproses. Uang DP dikembalikan ke customer.',
                    'data' => $dpRefund->load('stockOut', 'paymentMethod')
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses refund DP: ' . $e->getMessage()
            ], 500);
        }
    }
}
