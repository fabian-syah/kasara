<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Debugging: Log all request data without leaking password
        \Illuminate\Support\Facades\Log::info('Login Request Data:', $request->except(['password']));

        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $rawInput = trim($credentials['username']);
        $cleanUsername = strtolower($rawInput);
        // Base username stripping any auto-disambiguation suffixes like .cs, .123, .cs45, or email domain
        $baseUsername = preg_replace('/(\.cs\d*|\.\d+|@[a-z0-9\.\-]+)+$/i', '', $cleanUsername);
        $password = $credentials['password'];

        // Find candidate users:
        // 1. Exact username match (case-insensitive)
        // 2. Suffixed username (e.g. annisa.cs, annisa.12)
        // 3. Underscore suffix
        // 4. Base username (if input was suffixed or email)
        // 5. Email match
        $candidates = User::where(function ($q) use ($cleanUsername, $baseUsername, $rawInput) {
            $q->where('username', $rawInput)
              ->orWhereRaw('LOWER(TRIM(username)) = ?', [$cleanUsername])
              ->orWhereRaw('LOWER(TRIM(username)) LIKE ?', [$cleanUsername . '.%'])
              ->orWhereRaw('LOWER(TRIM(username)) LIKE ?', [$cleanUsername . '_%'])
              ->orWhereRaw('LOWER(TRIM(email)) = ?', [$cleanUsername])
              ->orWhereRaw('LOWER(TRIM(email)) LIKE ?', [$cleanUsername . '@%']);

            if (!empty($baseUsername) && $baseUsername !== $cleanUsername && strlen($baseUsername) >= 2) {
                $q->orWhereRaw('LOWER(TRIM(username)) = ?', [$baseUsername])
                  ->orWhereRaw('LOWER(TRIM(username)) LIKE ?', [$baseUsername . '.%'])
                  ->orWhereRaw('LOWER(TRIM(username)) LIKE ?', [$baseUsername . '_%']);
            }
        })
        ->orderBy('is_active', 'desc')
        ->orderByRaw("CASE 
            WHEN LOWER(TRIM(username)) = ? THEN 0 
            WHEN LOWER(TRIM(username)) = ? THEN 1 
            ELSE 2 END", [$cleanUsername, $baseUsername])
        ->orderBy('id', 'desc')
        ->get();

        $matchedUser = null;
        foreach ($candidates as $cand) {
            if (Hash::check($password, $cand->password) 
                || $cand->password === $password 
                || $cand->password === md5($password)) {
                
                // Upgrade plaintext or md5 passwords to standard bcrypt hash if applicable
                if ($cand->password === $password || $cand->password === md5($password)) {
                    $cand->password = Hash::make($password);
                    $cand->save();
                }

                $matchedUser = $cand;
                break;
            }
        }

        // Fallback: If no candidate matched above (e.g. standard Auth attempt fallback)
        if (!$matchedUser) {
            if (Auth::attempt(['username' => $rawInput, 'password' => $password]) ||
                Auth::attempt(['username' => $cleanUsername, 'password' => $password])) {
                $matchedUser = Auth::user();
            }
        }

        if ($matchedUser) {
            if (!$matchedUser->is_active) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda dinonaktifkan.',
                ], 403);
            }

            // Heal username if it was renamed to .cs or .<id> by prior auto-fix
            $matchedClean = strtolower(trim($matchedUser->username));
            $targetUsername = (!empty($baseUsername) && strlen($baseUsername) >= 2) ? $baseUsername : $cleanUsername;
            if ($matchedClean !== $targetUsername && (str_starts_with($matchedClean, $targetUsername . '.') || str_starts_with($matchedClean, $targetUsername . '_'))) {
                $otherActiveExists = User::whereRaw('LOWER(TRIM(username)) = ?', [$targetUsername])
                    ->where('id', '!=', $matchedUser->id)
                    ->where('is_active', true)
                    ->exists();
                if (!$otherActiveExists) {
                    $matchedUser->username = $targetUsername;
                    if (str_ends_with($matchedUser->email ?? '', '@apex-inventory.com') || str_ends_with($matchedUser->email ?? '', '@apexpos.com')) {
                        $matchedUser->email = $targetUsername . ($matchedUser->hasRole('inventory') ? '@apex-inventory.com' : '@apexpos.com');
                    }
                    $matchedUser->save();
                    \Illuminate\Support\Facades\Log::info("Restored user ID {$matchedUser->id} username back to {$targetUsername}");
                }
            }

            Auth::login($matchedUser);
            /** @var \App\Models\User $user */
            $user = $matchedUser;

            // Log successful login attempt
            \Illuminate\Support\Facades\Log::info('Login Success for: ' . $user->username);

            if (!$user->is_active) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda dinonaktifkan.',
                ], 403);
            }

            // Handle Remember Me
            $remember = $request->boolean('remember_me');

            if ($remember) {
                $token = $user->createToken('auth_token')->plainTextToken;
            } else {
                $expiration = now()->addHours(8);
                $token = $user->createToken('auth_token', ['*'], $expiration)->plainTextToken;
            }

            $user->load('branch', 'roles', 'warehouse', 'onlineShop', 'placements', 'createdBy.roles');

            // Get current league for user's branch
            $league = null;
            if ($user->branch_id) {
                $leagueRecord = \App\Models\BranchLeague::where('branch_id', $user->branch_id)
                    ->where('month', now()->month)
                    ->where('year', now()->year)
                    ->first();
                if ($leagueRecord) {
                    $league = [
                        'key' => $leagueRecord->league,
                        'label' => \App\Models\BranchLeague::LEAGUES[$leagueRecord->league] ?? $leagueRecord->league,
                        'month' => $leagueRecord->month,
                        'year' => $leagueRecord->year,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => array_merge($user->toArray(), [
                    'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                    'roles' => $user->roles->map(fn($r) => ['name' => $r->name])->toArray(),
                    'league' => $league,
                ]),
                'theme_color' => $user->theme_color,
            ]);
        }

        // Log failed login
        \Illuminate\Support\Facades\Log::warning('Login Failed for: ' . $credentials['username']);

        // Record failed login for security monitoring
        SystemStatusController::recordFailedLogin(
            $request->ip(),
            $credentials['username']
        );

        return response()->json([
            'success' => false,
            'message' => 'Login gagal. Username atau password salah.',
        ], 401);
    }

    public function logout(Request $request)
    {
        // Safely revoke the token
        try {
            if ($user = $request->user()) {
                $token = $user->currentAccessToken();
                if ($token && !($token instanceof \Laravel\Sanctum\TransientToken)) {
                    $token->delete();
                }
            }
        } catch (\Throwable $e) {
            // Watch out for ANY error (Exception or Error)
            // Log it but allow the frontend to proceed as "logged out"
            \Illuminate\Support\Facades\Log::error('Logout error: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    public function me(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->load(['roles', 'branch', 'warehouse', 'onlineShop', 'placements', 'createdBy.roles']);

        // Get current league for user's branch
        $league = null;
        if ($user->branch_id) {
            $leagueRecord = \App\Models\BranchLeague::where('branch_id', $user->branch_id)
                ->where('month', now()->month)
                ->where('year', now()->year)
                ->first();
            if ($leagueRecord) {
                $league = [
                    'key' => $leagueRecord->league,
                    'label' => \App\Models\BranchLeague::LEAGUES[$leagueRecord->league] ?? $leagueRecord->league,
                    'month' => $leagueRecord->month,
                    'year' => $leagueRecord->year,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'user' => array_merge($user->toArray(), [
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'roles' => $user->roles->map(fn($r) => ['name' => $r->name])->toArray(),
                'league' => $league,
                'creator_role' => $user->createdBy ? ($user->createdBy->roles->first()->name ?? null) : null,
            ]),
        ]);
    }

    public function verifyPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'inventory_user_id' => 'nullable|integer|exists:users,id',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $targetUser = $request->user();
        $targetId = $request->inventory_user_id ?? $request->user_id;

        if ($targetId && $targetId != $request->user()->id) {
            $found = User::find($targetId);
            if ($found) {
                if (!$found->has_password) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Akun CS (' . $found->name . ') belum memasang password login.',
                    ], 422);
                }
                $targetUser = $found;
            }
        }

        if (!\Illuminate\Support\Facades\Hash::check($request->password, $targetUser->password)) {
            $accountLabel = ($targetUser->id !== $request->user()->id) ? ' Akun CS (' . $targetUser->name . ')' : '';
            return response()->json([
                'success' => false,
                'message' => 'Password' . $accountLabel . ' salah.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password benar.',
        ]);
    }

    public function setPin(Request $request)
    {
        $user = $request->user();
        $request->validate(['transaction_pin' => 'required|string|size:4']);
        $user->transaction_pin = $request->transaction_pin;
        $user->pin_enabled = true;
        $user->save();
        return response()->json(['success' => true, 'user' => $user->load('branch', 'roles', 'warehouse', 'onlineShop', 'placements')]);
    }

    public function updatePin(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'current_pin' => 'required|string|size:4',
            'new_pin' => 'required|string|size:4'
        ]);
        if (!\Illuminate\Support\Facades\Hash::check($request->current_pin, $user->transaction_pin)) {
            return response()->json(['success' => false, 'message' => 'PIN saat ini salah.'], 422);
        }
        $user->transaction_pin = $request->new_pin;
        $user->save();
        return response()->json(['success' => true]);
    }

    public function togglePin(Request $request)
    {
        $user = $request->user();

        if ($user->transaction_pin) {
            $request->validate(['transaction_pin' => 'required|string']);
            if (!\Illuminate\Support\Facades\Hash::check($request->transaction_pin, $user->transaction_pin)) {
                return response()->json(['success' => false, 'message' => 'PIN salah.'], 422);
            }
        }

        $user->pin_enabled = !$user->pin_enabled;
        
        if (!$user->pin_enabled) {
            $user->transaction_pin = null;
        }
        
        $user->pin_reset_requested_at = null;
        $user->save();

        return response()->json([
            'success' => true, 
            'pin_enabled' => $user->pin_enabled,
            'user' => $user->load('branch', 'roles', 'warehouse', 'onlineShop', 'placements')
        ]);
    }

    public function verifyPin(Request $request)
    {
        $user = $request->user();
        $request->validate(['transaction_pin' => 'required|string|size:4']);
        if (!\Illuminate\Support\Facades\Hash::check($request->transaction_pin, $user->transaction_pin)) {
            return response()->json(['success' => false, 'message' => 'PIN salah.'], 422);
        }
        return response()->json(['success' => true]);
    }

    public function requestResetPin(Request $request)
    {
        $user = $request->user();
        $user->pin_reset_requested_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Permintaan reset PIN telah dicatat.'
        ]);
    }

    public function updateFontSize(Request $request)
    {
        $request->validate([
            'font_size' => 'required|string|in:small,standard,big'
        ]);

        $user = $request->user();
        $user->font_size = $request->font_size;
        $user->save();

        return response()->json([
            'success' => true,
            'user' => $user->load('branch', 'roles', 'warehouse', 'onlineShop', 'placements')
        ]);
    }
}

