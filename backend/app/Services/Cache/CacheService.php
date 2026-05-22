<?php

namespace App\Services\Cache;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Provides caching for frequently-accessed reference data (products, brands,
 * categories, distributors) with automatic invalidation on data changes.
 */
class CacheService
{
    /** @var int Cache TTL in seconds (5 minutes) */
    private const TTL = 300;

    public function __construct()
    {
        //
    }

    /**
     * Get cached product listings or fetch from database.
     *
     * @return Collection
     */
    public function getProducts(): Collection
    {
        // TODO: Implement with Cache::remember()
        return collect();
    }

    /**
     * Get cached brand listings or fetch from database.
     *
     * @return Collection
     */
    public function getBrands(): Collection
    {
        // TODO: Implement with Cache::remember()
        return collect();
    }

    /**
     * Get cached category listings or fetch from database.
     *
     * @return Collection
     */
    public function getCategories(): Collection
    {
        // TODO: Implement with Cache::remember()
        return collect();
    }

    /**
     * Invalidate a specific cache key.
     *
     * @param string $key The cache key to invalidate
     * @return void
     */
    public function invalidate(string $key): void
    {
        Cache::forget($key);
    }

    /**
     * Invalidate all product-related caches.
     *
     * @return void
     */
    public function invalidateProducts(): void
    {
        Cache::forget('products:all');
        Cache::forget('products:filter_options');
    }

    /**
     * Invalidate all brand-related caches.
     *
     * @return void
     */
    public function invalidateBrands(): void
    {
        Cache::forget('brands:all');
    }
}
