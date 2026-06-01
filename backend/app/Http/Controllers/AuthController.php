<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Debugging: Log all request data
        \Illuminate\Support\Facades\Log::info('Login Request Data:', $request->all());

        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

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

            $user->load('branch', 'roles', 'warehouse', 'onlineShop', 'placements');

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
        $user->load(['roles', 'branch', 'warehouse', 'onlineShop', 'placements']);

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
            ]),
        ]);
    }

    public function verifyPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (!\Illuminate\Support\Facades\Hash::check($request->password, $request->user()->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah.',
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
        $user->transaction_pin = $request->transaction_pin; // Hashed automatically by model cast
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

        // Verification is MANDATORY if PIN exists
        if ($user->transaction_pin) {
            $request->validate(['transaction_pin' => 'required|string']);
            if (!\Illuminate\Support\Facades\Hash::check($request->transaction_pin, $user->transaction_pin)) {
                return response()->json(['success' => false, 'message' => 'PIN salah.'], 422);
            }
        }

        // Toggle state
        $user->pin_enabled = !$user->pin_enabled;
        
        // If turning OFF, DELETE it as requested
        if (!$user->pin_enabled) {
            $user->transaction_pin = null;
        }
        
        // Clear reset request
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
