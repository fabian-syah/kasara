<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

trait VerifiesPin
{
    /**
     * Verify transaction password for the logged-in user or selected inventory account.
     *
     * @param Request $request
     * @param int|null $inventoryUserId
     * @return \Illuminate\Http\JsonResponse|null Returns null if verified, or a JsonResponse with error.
     */
    protected function verifyPin(Request $request, $inventoryUserId = null)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        $isInventoryRole = $user && $user->hasRole('inventory');

        // Resolve targeted inventory user if not explicitly passed
        if (!$inventoryUserId) {
            $inventoryUserId = $request->inventory_user_id;
            if (!$inventoryUserId && $request->sales_account) {
                 $invUser = \App\Models\User::where('name', $request->sales_account)->first();
                 if ($invUser) $inventoryUserId = $invUser->id;
            }
        }

        // 1. If the logged-in user has the 'inventory' role
        if ($isInventoryRole) {
            // "transaksi penjualan via akun inventory gak butuh masukin password"
            $salesCategories = ['penjualan_store', 'shopee', 'orderan_online', 'penjualan_offline', 'dp', 'pelunasan_dp'];
            if ($request->category && in_array($request->category, $salesCategories)) {
                return null; // Skip verification for sales transactions
            }

            // If they don't have a PIN enabled, bypass
            if (!$user->pin_enabled) {
                return null;
            }

            // Verify PIN
            $pin = $request->transaction_pin ?? $request->pin ?? $request->password;
            if (!$pin || !Hash::check($pin, $user->transaction_pin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN Keamanan salah atau diperlukan untuk melanjutkan.'
                ], 422);
            }

            return null; // Success
        }

        // 2. If logged in as offline store (non-inventory), selecting an inventory user for a transaction
        // "dia pakai password akun inventory 1 bukan pin ya"
        if ($inventoryUserId && $inventoryUserId != $user->id) {
            $inventoryUser = \App\Models\User::find($inventoryUserId);
            if ($inventoryUser) {
                if (empty($inventoryUser->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Akun Inventory ' . $inventoryUser->name . ' belum memasang password. Silakan atur password terlebih dahulu.'
                    ], 422);
                }

                $password = $request->password ?? $request->transaction_pin ?? $request->pin;
                if (!$password || !Hash::check($password, $inventoryUser->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Password Inventory (' . $inventoryUser->name . ') salah atau diperlukan untuk melanjutkan.'
                    ], 422);
                }
                return null; // Success
            }
        }

        // 3. Fallback: Verify Password of the logged-in user (e.g. Toko Offline password if no target inventory user)
        $password = $request->password ?? $request->transaction_pin ?? $request->pin;

        if (!$password || !Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah atau diperlukan untuk melanjutkan.'
            ], 422);
        }

        return null; // Success
    }
}
