<?php

namespace App\Services\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Encapsulates all inventory filtering logic (search, placement, brand,
 * status, capacity, security constraints) into a single reusable service.
 * Used by both listing and export operations to ensure consistent behavior.
 */
class InventoryFilterService
{
    public function __construct()
    {
        //
    }

    /**
     * Apply all relevant filters to an inventory query builder.
     *
     * @param Builder $query The base query to filter
     * @param array $filters Filter parameters from the request
     * @param string $type Inventory type ('hp' or 'non-hp')
     * @param User $user The authenticated user (for security/access filtering)
     * @return Builder The filtered query
     */
    public function apply(Builder $query, array $filters, string $type, User $user): Builder
    {
        // TODO: Extract and unify filter logic from InventoryController::index() and applyInventoryFilters()
        return $query;
    }
}
