<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

trait VerifiesPin
{
    /**
     * Verify transaction PIN for either the logged-in user or a specific inventory account.
     *
     * @param Request $request
     * @param int|null $inventoryUserId
     * @return \Illuminate\Http\JsonResponse|null Returns null if verified, or a JsonResponse with error.
     */
    protected function verifyPin(Request $request, $inventoryUserId = null)
    {
        $user = Auth::user();
        
        // Target can be a specific inventory account or the current user
        $targetUserId = $inventoryUserId ?: $request->inventory_user_id ?: $user->id;
        
        $targetUser = null;
        if (is_numeric($targetUserId)) {
            $targetUser = User::find($targetUserId);
        }

        if ($targetUser) {
            // Check for password or transaction_pin (for transition compatibility)
            $password = $request->password ?? $request->transaction_pin;

            if (!$password || !Hash::check($password, $targetUser->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kata sandi salah atau diperlukan untuk akun ' . $targetUser->name
                ], 422);
            }
        }

        return null; // Success
    }
}
