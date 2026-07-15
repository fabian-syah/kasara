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
        \Illuminate\Support\Facades\Gate::define('viewApiDocs', function ($user = null) {
            return true; // Allow everyone to view API docs (or modify later for auth only)
        });

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

        /* \Dedoc\Scramble\Scramble::extendOpenApi(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
            $openApi->secure(
                \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer')
            );

            $openApi->setInfo(
                \Dedoc\Scramble\Support\Generator\InfoObject::make('Apex Frontend API')
                    ->setVersion('1.0.0')
                    ->setDescription("Dokumentasi API untuk Apex Frontend.\n\n### Tech Stack\n- Laravel 12\n- PHP 8.2\n- MySQL\n\n### License\nMIT License ([https://opensource.org/licenses/MIT](https://opensource.org/licenses/MIT))")
            );
        }); */
    }
}
