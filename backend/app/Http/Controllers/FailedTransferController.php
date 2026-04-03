<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use App\Models\ProductDetail;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FailedTransferController extends Controller
{
    /**
     * Get outgoing transfers with returning items (rejected by receiver)
     */
    public function indexFailed()
    {
        $user = Auth::user();
        if (!$user) return response()->json(['data' => []]);

        // Find transfers where items are in 'returning' status
        // AND the user is the sender
        $query = StockOut::with(['items.product.brandRelation', 'nonHpItems.product.brandRelation', 'user', 'destination'])
            ->where('user_id', $user->id)
            ->where('category', 'pindah_cabang')
            ->whereHas('items', function($q) {
                $q->where('status', 'returning');
            });

        $transfers = $query->latest()->get();

        return response()->json(['data' => $transfers]);
    }

    /**
     * Confirm receipt of returned items
     */
    public function confirmReturn(Request $request, $id)
    {
        $user = Auth::user();
        $stockOut = StockOut::findOrFail($id);

        if ($stockOut->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            // Restore HP items
            foreach ($stockOut->items as $item) {
                if ($item->status === 'returning') {
                    // Decide where it should go back. 
                    // Usually to the sender's current location or recorded source.
                    $placementType = 'branch';
                    $placementId = $user->branch_id;
                    
                    if ($user->warehouse_id) {
                        $placementType = 'warehouse';
                        $placementId = $user->warehouse_id;
                    } elseif ($user->online_shop_id) {
                        $placementType = 'online_shop';
                        $placementId = $user->online_shop_id;
                    }

                    $item->update([
                        'status' => 'available',
                        'placement_type' => $placementType,
                        'placement_id' => $placementId,
                        'user_id' => $user->id
                    ]);
                }
            }

            // Note: Non-HP items are currently handled immediately in confirm() by returning to inventory.
            // If we want OTW-back for them, we'd need more complex tracking.

            DB::commit();
            return response()->json(['message' => 'Items successfully returned to inventory']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
