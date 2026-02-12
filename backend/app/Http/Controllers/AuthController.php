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

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => $user->load('branch', 'roles', 'warehouse', 'onlineShop'),
                'theme_color' => $user->theme_color,
            ]);
        }

        // Log failed login
        \Illuminate\Support\Facades\Log::warning('Login Failed for: ' . $credentials['username']);

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
        return response()->json([
            'success' => true,
            'user' => $request->user()->load('branch', 'roles', 'warehouse', 'onlineShop'),
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
}
