<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Cloudflare proxies agar IP asli user terdeteksi dengan benar
        $middleware->trustProxies(at: '*');

        // Solusi untuk error "Route [login] not defined"
        // Jika user belum login dan akses API, jangan di-redirect, tapi kasih error 401
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                abort(response()->json([
                    'message' => 'Unauthenticated.'
                ], 401));
            }
            return route('login');
        });

        $middleware->validateCsrfTokens(except: [
            'api/*', // Kecualikan semua jalur API dari pengecekan CSRF
        ]);

        $middleware->statefulApi();
        $middleware->append(\App\Http\Middleware\UpdateLastSeen::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();