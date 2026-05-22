<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registers all service layer classes as singletons in the container.
 * This ensures consistent state and efficient memory usage across requests.
 */
class ServiceLayerProvider extends ServiceProvider
{
    /**
     * Register service bindings.
     */
    public function register(): void
    {
        // Inventory services
        $this->app->singleton(
            \App\Services\Inventory\InventoryFilterService::class
        );

        $this->app->singleton(
            \App\Services\Inventory\InventoryService::class,
            function ($app) {
                return new \App\Services\Inventory\InventoryService(
                    $app->make(\App\Services\Inventory\InventoryFilterService::class)
                );
            }
        );

        $this->app->singleton(
            \App\Services\Inventory\StockInService::class
        );

        $this->app->singleton(
            \App\Services\Inventory\InventoryExportService::class,
            function ($app) {
                return new \App\Services\Inventory\InventoryExportService(
                    $app->make(\App\Services\Inventory\InventoryFilterService::class)
                );
            }
        );

        // Stock-out service
        $this->app->singleton(
            \App\Services\StockOut\StockOutService::class
        );

        // Transfer service
        $this->app->singleton(
            \App\Services\Transfer\TransferService::class
        );

        // Audit service
        $this->app->singleton(
            \App\Services\Audit\AuditService::class
        );

        // Report service
        $this->app->singleton(
            \App\Services\Report\ReportService::class
        );

        // Notification service
        $this->app->singleton(
            \App\Services\Notification\NotificationService::class
        );

        // Cache service
        $this->app->singleton(
            \App\Services\Cache\CacheService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
