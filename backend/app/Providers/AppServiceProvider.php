<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'branch' => \App\Models\Branch::class,
            'warehouse' => \App\Models\Warehouse::class,
            'online_shop' => \App\Models\OnlineShop::class,
            'distributor' => \App\Models\Distributor::class,
        ]);

        // General API rate limit (authenticated users get more)
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(1000)->by($request->user()->id)
                : Limit::perMinute(60)->by($request->ip());
        });

        // Login: strict limit to prevent brute-force
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())->response(function (Request $req, array $headers) {
                return response()->json([
                    'message' => 'Terlalu banyak percobaan login. Coba lagi nanti.',
                    'retry_after' => $headers['Retry-After'] ?? 60,
                ], 429)->withHeaders($headers);
            });
        });

        // Export/heavy operations: prevent abuse
        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'message' => 'Terlalu banyak request export. Tunggu 1 menit.',
                ], 429);
            });
        });

        // Sensitive operations (block IP, toggle defender, etc)
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

    }
}
