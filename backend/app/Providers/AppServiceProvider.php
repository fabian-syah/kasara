<?php

namespace App\Providers;

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
    }
}
