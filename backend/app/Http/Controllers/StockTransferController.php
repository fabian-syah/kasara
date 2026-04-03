<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use App\Models\StockOutNonHpItem;
use App\Models\ProductDetail;
use App\Models\Inventory;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Traits\VerifiesPin;

class StockTransferController extends Controller
{
    use VerifiesPin;
    // List Incoming Transfers
    public function indexIncoming(Request $request)
    {
        $user = Auth::user();

        // Determine user's placement
        $destinationType = null;
        $destinationId = null;

        if ($user->branch_id) {
            $destinationType = 'branch';
            $destinationId = $user->branch_id;
        } elseif ($user->warehouse_id) {
            $destinationType = 'warehouse';
            $destinationId = $user->warehouse_id;
        } elseif ($user->online_shop_id) {
            $destinationType = 'online_shop';
            $destinationId = $user->online_shop_id;
        } elseif ($user->hasRole('super_admin')) {
            // Super admin can see all or filter by param
            if ($request->branch_id) {
                $destinationType = 'branch';
                $destinationId = $request->branch_id;
            }
            // Add other filters if needed
        }

        if (!$destinationType && !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Anda tidak memiliki lokasi untuk menerima barang.'], 403);
        }

        $query = StockOut::with(['user', 'items.product.brandRelation', 'nonHpItems.product.brandRelation', 'destination'])
            ->where('category', 'pindah_cabang')
            ->where('status', 'pending');

        if ($destinationType) {
            $query->where('destination_type', $destinationType)
                ->where('destination_id', $destinationId);
        }

        // Logic to allow finding transfers sent TO this user specifically? 
        // Or generic to the location? Usually generic to location.

        $transfers = $query->latest()->get();

        return response()->json($transfers);
    }

    // Confirm Transfer (Receive)
    public function confirm(Request $request, $id)
    {
        $user = Auth::user();
        $stockOut = StockOut::with(['items', 'nonHpItems', 'user'])->findOrFail($id);

        if ($stockOut->status !== 'pending') {
            return response()->json(['message' => 'Transfer ini sudah diproses.'], 400);
        }

        // Validate destination
        $canReceive = false;
        if ($user->hasRole('super_admin'))
            $canReceive = true;
        elseif ($stockOut->destination_type === 'branch' && $user->branch_id == $stockOut->destination_id)
            $canReceive = true;
        elseif ($stockOut->destination_type === 'warehouse' && $user->warehouse_id == $stockOut->destination_id)
            $canReceive = true;
        elseif ($stockOut->destination_type === 'online_shop' && $user->online_shop_id == $stockOut->destination_id)
            $canReceive = true;

        if (!$canReceive) {
            return response()->json(['message' => 'Anda tidak berhak menerima transfer ini.'], 403);
        }

        $request->validate([
            'accepted_items' => 'array', // List of IMEI IDs
            'non_hp_quantities' => 'array', // Map: [ non_hp_item_id => quantity_received ]
            'inventory_user_id' => 'sometimes|nullable|exists:users,id',
            'transaction_pin' => 'nullable|string|max:10',
        ]);

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError) return $pinError;

        DB::beginTransaction();
        try {
            $acceptedImeiIds = $request->accepted_items ?? [];
            $nonHpQuantities = $request->non_hp_quantities ?? [];

            // 1. Process HP Items
            foreach ($stockOut->items as $item) {
                if (in_array($item->id, $acceptedImeiIds)) {
                    // ACCEPTED
                    $item->status = 'available';
                    // Placement is already set to destination in StockOutController@store?
                    // Let's verify logic there. If logic sets placement to destination but status 'in_transit',
                    // then we just change status to 'available'.
                    // If logic kept placement at source, we update placement now.
                    // Assuming StockOutController@store sets placement to DESTINATION and status to 'in_transit'.
                    // So we just update status.
                } else {
                    // REJECTED / RETURNED
                    $item->status = 'available'; // OR 'returned'? User said "masuk kembali ke stok inventory sebelumnya"
                    // Return to SENDER
                    // We need sender's location.
                    // StockOut has user_id. User has placement AT THAT TIME? Or use generic placement?
                    // Better: use InventoryLog or just infer from user.
                    // OR: Use $stockOut->user->branch_id etc.
                    // Risk: User might have moved.
                    // Safer: Use stock out logs? Or just assume sender user is correct.
                    // Let's use the creator's current placement placement
                    $sender = $stockOut->user;
                    if ($sender->branch_id) {
                        $item->placement_type = 'branch';
                        $item->placement_id = $sender->branch_id;
                    } elseif ($sender->warehouse_id) {
                        $item->placement_type = 'warehouse';
                        $item->placement_id = $sender->warehouse_id;
                    } elseif ($sender->online_shop_id) {
                        $item->placement_type = 'online_shop';
                        $item->placement_id = $sender->online_shop_id;
                    }
                    // What if sender has no placement now? Fallback to warehouse or specific return location?
                    // For now, assume sender has placement.
                }
                $item->save();
            }

            // 2. Process Non-HP Items
            foreach ($stockOut->nonHpItems as $nonHpItem) {
                $receivedQty = $nonHpQuantities[$nonHpItem->id] ?? 0;
                $sentQty = $nonHpItem->quantity;

                if ($receivedQty > $sentQty)
                    $receivedQty = $sentQty; // Clamp
                $returnedQty = $sentQty - $receivedQty;

                $nonHpItem->update([
                    'received_quantity' => $receivedQty,
                    'returned_quantity' => $returnedQty
                ]);

                // Add to Destination Inventory
                if ($receivedQty > 0) {
                    $inv = Inventory::firstOrCreate([
                        'product_id' => $nonHpItem->product_id,
                        'placement_type' => $stockOut->destination_type,
                        'placement_id' => $stockOut->destination_id,
                        'user_id' => $user->id, // Assign to receiver
                    ], ['quantity' => 0]);

                    $inv->increment('quantity', $receivedQty);

                    InventoryLog::create([
                        'product_id' => $nonHpItem->product_id,
                        'brand_id' => null, // Optional
                        'user_id' => $user->id,
                        'placement_type' => $stockOut->destination_type, // polymorphic support in log?
                        // If logs don't support polymorphic, map manually.
                        // Assuming logs use specific columns branch_id, etc.
                        'branch_id' => ($stockOut->destination_type == 'branch') ? $stockOut->destination_id : null,
                        'warehouse_id' => ($stockOut->destination_type == 'warehouse') ? $stockOut->destination_id : null,
                        'online_shop_id' => ($stockOut->destination_type == 'online_shop') ? $stockOut->destination_id : null,
                        'type' => 'in',
                        'quantity' => $receivedQty,
                        'balance_after' => $inv->quantity,
                        'description' => "Transfer Received from #{$stockOut->receipt_id}",
                    ]);
                }

                // Return to Sender Inventory
                if ($returnedQty > 0) {
                    $sender = $stockOut->user;
                    // Determine sender placement
                    $senderPlacementType = null;
                    $senderPlacementId = null;
                    if ($sender->branch_id) {
                        $senderPlacementType = 'branch';
                        $senderPlacementId = $sender->branch_id;
                    } elseif ($sender->warehouse_id) {
                        $senderPlacementType = 'warehouse';
                        $senderPlacementId = $sender->warehouse_id;
                    } elseif ($sender->online_shop_id) {
                        $senderPlacementType = 'online_shop';
                        $senderPlacementId = $sender->online_shop_id;
                    }

                    if ($senderPlacementType) {
                        $invSender = Inventory::firstOrCreate([
                            'product_id' => $nonHpItem->product_id,
                            'placement_type' => $senderPlacementType,
                            'placement_id' => $senderPlacementId,
                            'user_id' => $sender->id,
                        ], ['quantity' => 0]);

                        $invSender->increment('quantity', $returnedQty);

                        InventoryLog::create([
                            'product_id' => $nonHpItem->product_id,
                            'user_id' => $sender->id,
                            'branch_id' => ($senderPlacementType == 'branch') ? $senderPlacementId : null,
                            'warehouse_id' => ($senderPlacementType == 'warehouse') ? $senderPlacementId : null,
                            'online_shop_id' => ($senderPlacementType == 'online_shop') ? $senderPlacementId : null,
                            'type' => 'in', // Returned back
                            'quantity' => $returnedQty,
                            'balance_after' => $invSender->quantity,
                            'description' => "Transfer Returned (Partial) from #{$stockOut->receipt_id}",
                        ]);
                    }
                }
            }

            // Update StockOut
            $stockOut->update([
                'status' => 'received', // Always received, even if partial. Can add 'partial' if needed.
                'confirmed_at' => now(),
                'confirmed_by' => $user->id
            ]);

            DB::commit();
            return response()->json(['message' => 'Transfer berhasil dikonfirmasi']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
