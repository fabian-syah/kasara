<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use App\Models\ProductDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Traits\VerifiesPin;

class TransferController extends Controller
{
    use VerifiesPin;
    /**
     * Get pending incoming transfers for the current user's branch
     * Returns stock_outs with category=pindah_cabang where destination_branch_id = user's branch
     * and items still have status = in_transit
     */
    public function pending(Request $request)
    {
        $user = Auth::user();
        $branchId = $user->branch_id;

        if (!$branchId) {
            return response()->json([
                'message' => 'User tidak memiliki cabang terkait',
                'data' => []
            ], 200);
        }

        // Get transfers where this branch is the destination and status is pending (transfer_status is null or pending)
        $transfers = StockOut::with(['items.product', 'user', 'sourceBranch'])
            ->where('category', 'pindah_cabang')
            ->where('destination_branch_id', $branchId)
            ->whereNull('confirmed_at') // Not yet confirmed
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'count' => $transfers->count(),
            'data' => $transfers->map(function ($transfer) {
                return [
                    'id' => $transfer->id,
                    'receipt_id' => $transfer->receipt_id,
                    'items' => $transfer->items->map(fn($item) => [
                        'id' => $item->id,
                        'imei' => $item->imei,
                        'product_name' => $item->product?->name,
                        'product_brand' => $item->product?->brand,
                        'status' => $item->status,
                    ]),
                    'items_count' => $transfer->items->count(),
                    'sender_name' => $transfer->user?->name ?? $transfer->user?->username,
                    'receiver_name' => $transfer->receiver_name,
                    'notes' => $transfer->notes,
                    'created_at' => $transfer->created_at,
                ];
            })
        ]);
    }

    /**
     * Confirm receipt of a transfer
     * Updates item status from in_transit to available
     * Updates item placement to the destination branch
     */
    public function confirm(Request $request, $id)
    {
        $user = Auth::user();
        $branchId = $user->branch_id;

        $stockOut = StockOut::with('items')
            ->where('id', $id)
            ->where('category', 'pindah_cabang')
            ->where('destination_branch_id', $branchId)
            ->whereNull('confirmed_at')
            ->first();

        if (!$stockOut) {
            return response()->json([
                'message' => 'Transfer tidak ditemukan atau sudah dikonfirmasi'
            ], 404);
        }

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError) return $pinError;

        /** @var \App\Models\StockOut $stockOut */
        DB::beginTransaction();
        try {
            // Update stock out record
            $stockOut->confirmed_at = now();
            $stockOut->confirmed_by = $user->id;
            $stockOut->save();

            // Update each item's status and placement
            foreach ($stockOut->items as $item) {
                $item->status = 'available';
                $item->placement_type = 'branch';
                $item->placement_id = $branchId;
                $item->created_at = now();
                $item->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Transfer berhasil dikonfirmasi',
                'receipt_id' => $stockOut->receipt_id,
                'items_confirmed' => $stockOut->items->count()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mengkonfirmasi transfer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transfer history (both sent and received)
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        $branchId = $user->branch_id;
        $type = $request->query('type'); // 'incoming' or 'outgoing'

        $transfers = StockOut::with(['items.product', 'user', 'destinationBranch', 'confirmedBy'])
            ->where('category', 'pindah_cabang')
            ->where(function ($q) use ($branchId, $user, $type) {
                if ($type === 'incoming') {
                    $q->where('destination_branch_id', $branchId);
                } elseif ($type === 'outgoing') {
                    $q->where('user_id', $user->id);
                } else {
                    // Show transfers sent by this user or received by this branch
                    $q->where('user_id', $user->id)
                        ->orWhere('destination_branch_id', $branchId);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'data' => collect($transfers->items())->map(function ($t) use ($branchId) {
                return [
                    'id' => $t->id,
                    'receipt_id' => $t->receipt_id,
                    'type' => $t->destination_branch_id === $branchId ? 'incoming' : 'outgoing',
                    'items_count' => $t->items->count(),
                    'destination_branch' => $t->destinationBranch?->name,
                    'sender' => $t->user?->name,
                    'receiver_name' => $t->receiver_name,
                    'status' => $t->confirmed_at ? 'confirmed' : 'pending',
                    'confirmed_at' => $t->confirmed_at,
                    'confirmed_by' => $t->confirmedBy?->name,
                    'created_at' => $t->created_at,
                ];
            }),
            'current_page' => $transfers->currentPage(),
            'last_page' => $transfers->lastPage(),
            'total' => $transfers->total()
        ]);
    }
}
