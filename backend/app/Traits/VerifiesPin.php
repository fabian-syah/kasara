<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

trait VerifiesPin
{
    /**
     * Verify transaction password for the logged-in user.
     *
     * @param Request $request
     * @param int|null $inventoryUserId
     * @return \Illuminate\Http\JsonResponse|null Returns null if verified, or a JsonResponse with error.
     */
    protected function verifyPin(Request $request, $inventoryUserId = null)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // If the logged-in user has the 'inventory' role, they use their PIN.
        if ($user && $user->hasRole('inventory')) {
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

        // If the logged-in user is NOT 'inventory', they must provide the password of the targeted inventory account.
        // The targeted inventory account ID is usually passed in the request as inventory_user_id.
        $targetUserId = $inventoryUserId ?? $request->inventory_user_id;

        if (!$targetUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Inventory tujuan tidak ditemukan.'
            ], 422);
        }

        $targetUser = User::find($targetUserId);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Inventory tujuan tidak valid.'
            ], 422);
        }

        // Verify Password of the target inventory user
        $password = $request->password ?? $request->transaction_pin ?? $request->pin;

        if (!$password || !Hash::check($password, $targetUser->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password Akun Inventory salah atau diperlukan untuk melanjutkan.'
            ], 422);
        }

        return null; // Success
    }
}
