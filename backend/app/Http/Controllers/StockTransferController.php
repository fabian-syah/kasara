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
use Illuminate\Support\Facades\Log;
use App\Traits\VerifiesPin;

class StockTransferController extends Controller
{
    use VerifiesPin;
    // List Incoming Transfers
    public function indexIncoming(Request $request)
    {
        $user = Auth::user();

        $accessibleBranchIds = $user->getAccessibleBranchIds();
        $accessibleWarehouseIds = $user->getAccessibleWarehouseIds();
        $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();

        $query = StockOut::with(['user', 'items.product.brandRelation', 'nonHpItems.product.brandRelation', 'destination', 'branch', 'onlineShop', 'warehouse'])
            ->where('category', 'pindah_cabang')
            ->where('status', 'pending');

        if (!$user->hasRole('super_admin')) {
            $query->where(function ($q) use ($accessibleBranchIds, $accessibleWarehouseIds, $accessibleOnlineShopIds) {
                if (!empty($accessibleBranchIds)) {
                    $q->orWhere(fn($sub) => $sub->where('destination_type', 'branch')->whereIn('destination_id', $accessibleBranchIds));
                }
                if (!empty($accessibleWarehouseIds)) {
                    $q->orWhere(fn($sub) => $sub->where('destination_type', 'warehouse')->whereIn('destination_id', $accessibleWarehouseIds));
                }
                if (!empty($accessibleOnlineShopIds)) {
                    $q->orWhere(fn($sub) => $sub->where('destination_type', 'online_shop')->whereIn('destination_id', $accessibleOnlineShopIds));
                }

                if (empty($accessibleBranchIds) && empty($accessibleWarehouseIds) && empty($accessibleOnlineShopIds)) {
                    $q->whereRaw('1=0');
                }
            });
        } elseif ($request->branch_id) {
            $query->where('destination_type', 'branch')
                ->where('destination_id', $request->branch_id);
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
        if ($pinError)
            return $pinError;

        DB::beginTransaction();
        try {
            // Refresh relation to be sure
            $stockOut->load(['items', 'nonHpItems']);

            $acceptedImeiIds = $request->accepted_items ?? [];
            $nonHpQuantities = $request->non_hp_quantities ?? [];

            Log::info("DEBUG TRANSFER: Confirming #{$stockOut->receipt_id}", [
                'hp_items_count' => $stockOut->items->count(),
                'accepted_ids' => $acceptedImeiIds,
                'user_id' => $user->id
            ]);

            // 1. Process HP Items
            foreach ($stockOut->items as $item) {
                if (in_array($item->id, $acceptedImeiIds)) {
                    // ACCEPTED
                    $item->status = 'available';
                    $item->created_at = now();

                    Log::info("DEBUG TRANSFER: Accepting HP item {$item->id}");

                    // CREATE INVENTORY LOG AS "IN" FOR THE DESTINATION BRANCH
                    // This ensures the mutation appears on the confirmation date, not the shipping date.
                    InventoryLog::create([
                        'product_id' => $item->product_id,
                        'user_id' => $user->id,
                        'branch_id' => ($stockOut->destination_type == 'branch') ? $stockOut->destination_id : null,
                        'warehouse_id' => ($stockOut->destination_type == 'warehouse') ? $stockOut->destination_id : null,
                        'online_shop_id' => ($stockOut->destination_type == 'online_shop') ? $stockOut->destination_id : null,
                        'type' => 'in',
                        'quantity' => 1,
                        'balance_after' => 1, // Optional for HP
                        'description' => "Pindah Cabang Masuk dari #{$stockOut->receipt_id} (" . ($item->imei ?? $item->p_code) . ")",
                        'reference_id' => (string) $item->id, // Store ProductDetail ID as reference
                    ]);
                } else {
                    // REJECTED / RETURNED
                    $item->status = 'available';
                    $item->created_at = now();
                    Log::info("DEBUG TRANSFER: Rejecting HP item {$item->id}");
                    // Return to SENDER
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

                    // Create log for Return to Sender? 
                    InventoryLog::create([
                        'product_id' => $item->product_id,
                        'user_id' => $sender->id,
                        'branch_id' => $sender->branch_id ?? null,
                        'warehouse_id' => $sender->warehouse_id ?? null,
                        'online_shop_id' => $sender->online_shop_id ?? null,
                        'type' => 'in', // Returned back
                        'quantity' => 1,
                        'description' => "Transfer Ditolak/Retur dari #{$stockOut->receipt_id} (" . ($item->imei ?? $item->p_code) . ")",
                        'reference_id' => (string) $item->id,
                    ]);
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
