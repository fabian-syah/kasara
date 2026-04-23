<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        // Jalankan request-nya dulu sampai selesai
        $response = $next($request);

        // Baru setelah itu update last_seen secara "silent" (tanpa trigger events)
        if (Auth::check()) {
            $user = Auth::user();

            // 1. Update main user
            $user->timestamps = false;
            $user->last_seen = now();
            $user->save();

            // 2. Update sub-account if inventory_user_id is present and a valid positive integer
            $subId = $request->input('inventory_user_id');
            if ($subId && is_numeric($subId) && (int)$subId > 0) {
                $subAccount = \App\Models\User::find((int)$subId);
                if ($subAccount) {
                    $subAccount->timestamps = false;
                    $subAccount->last_seen = now();
                    $subAccount->save();
                }
            }
        }


        return $response;
    }
}