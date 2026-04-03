<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use App\Models\ProductDetail;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\StockOutNonHpItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FailedTransferController extends Controller
{
    use \App\Traits\VerifiesPin;

    /**
     * Get outgoing transfers with returning items (rejected by receiver)
     */
    public function indexFailed()
    {
        $user = Auth::user();
        if (!$user) return response()->json(['data' => []]);

        // Find transfers where items are rejected in the pivot table
        // AND the user is the sender
        $query = StockOut::with(['items.product.brandRelation', 'nonHpItems.product.brandRelation', 'user', 'destination', 'items' => function($q) {
                $q->withPivot('status', 'notes');
            }])
            ->where('user_id', $user->id)
            ->where('category', 'pindah_cabang')
            ->where(function($query) {
                $query->whereHas('items', function($q) {
                    $q->where('stock_out_items.status', 'rejected');
                })
                ->orWhereHas('nonHpItems', function($q) {
                    $q->where('received_quantity', '<', DB::raw('quantity'));
                });
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

        // Verify PIN against confirming user (current user or selected inventory account)
        $pinError = $this->verifyPin($request);
        if ($pinError) return $pinError;

        $confirmingUserId = $user->id;
        if ($request->has('inventory_user_id') && $request->inventory_user_id) {
            $confirmingUserId = $request->inventory_user_id;
        }

        DB::beginTransaction();
        try {
            // Calculate Return Location (Original Sender's Location)
            $placementType = 'branch';
            $placementId = $user->branch_id;
            
            if ($user->warehouse_id) {
                $placementType = 'warehouse';
                $placementId = $user->warehouse_id;
            } elseif ($user->online_shop_id) {
                $placementType = 'online_shop';
                $placementId = $user->online_shop_id;
            }

            // 1. Restore HP items
            foreach ($stockOut->items as $item) {
                // Check pivot status for 'rejected'
                $isRejected = DB::table('stock_out_items')
                    ->where('stock_out_id', $stockOut->id)
                    ->where('product_detail_id', $item->id)
                    ->where('status', 'rejected')
                    ->exists();

                if ($isRejected || $item->status === 'returning') {
                    $item->update([
                        'status' => 'available',
                        'placement_type' => $placementType,
                        'placement_id' => $placementId,
                        'user_id' => $confirmingUserId
                    ]);

                    // Update pivot to 'returned' so it's not processed twice
                    DB::table('stock_out_items')
                        ->where('stock_out_id', $stockOut->id)
                        ->where('product_detail_id', $item->id)
                        ->update(['status' => 'returned']);
                }
            }

            // 2. Restore Non-HP items
            foreach ($stockOut->nonHpItems as $record) {
                if ($record->returned_quantity > 0) {
                    // Add back to Sender's Inventory
                    $inventory = \App\Models\Inventory::firstOrCreate(
                        [
                            'product_id' => $record->product_id,
                            'placement_type' => $placementType,
                            'placement_id' => $placementId,
                            'user_id' => $confirmingUserId,
                        ],
                        ['quantity' => 0]
                    );
                    $inventory->increment('quantity', $record->returned_quantity);

                    // Log Return
                    \App\Models\InventoryLog::create([
                        'product_id' => $record->product_id,
                        'type' => 'in',
                        'quantity' => $record->returned_quantity,
                        'balance_after' => $inventory->quantity,
                        'description' => "Terima Balik Transfer: {$stockOut->receipt_id}",
                        'reference_id' => $stockOut->receipt_id,
                        'user_id' => $confirmingUserId,
                        'branch_id' => $user->branch_id ?? null,
                        'warehouse_id' => $user->warehouse_id ?? null,
                        'online_shop_id' => $user->online_shop_id ?? null,
                    ]);

                    // Reset returned_quantity to avoid duplicate processing
                    $record->update(['returned_quantity' => 0]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Items successfully returned to inventory']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error processing return: ' . $e->getMessage()], 500);
        }
    }
}
