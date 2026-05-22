<?php

namespace App\Services\Inventory;

use App\Models\User;
use App\Models\Inventory;
use App\Models\ProductDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates all inventory filtering logic (search, placement, brand,
 * status, capacity, security constraints) into a single reusable service.
 * Used by both listing and export operations to ensure consistent behavior.
 */
class InventoryFilterService
{
    /**
     * Apply all relevant filters to an inventory query builder.
     * Unified logic extracted from InventoryController::index() and applyInventoryFilters().
     *
     * @param Builder $query The base query to filter
     * @param array $filters Filter parameters from the request
     * @param string $type Inventory type ('hp' or 'non-hp')
     * @param User $user The authenticated user (for security/access filtering)
     * @return Builder The filtered query
     */
    public function apply(Builder $query, array $filters, string $type, User $user): Builder
    {
        // 1. Security: Placement access restrictions
        $this->applyPlacementSecurity($query, $user);

        // 2. Search filter
        if (!empty($filters['search'])) {
            $this->applySearch($query, $filters['search'], $type);
        }

        // 3. Location filters
        if (!empty($filters['branch_id'])) {
            $query->where('placement_type', 'branch')->where('placement_id', $filters['branch_id']);
        }
        if (!empty($filters['online_shop_id'])) {
            $query->where('placement_type', 'online_shop')->where('placement_id', $filters['online_shop_id']);
        }
        if (!empty($filters['warehouse_id'])) {
            $query->where('placement_type', 'warehouse')->where('placement_id', $filters['warehouse_id']);
        }
        if (!empty($filters['distributor_id'])) {
            $query->where('placement_type', 'distributor')->where('placement_id', $filters['distributor_id']);
        }
        if (!empty($filters['placement_type'])) {
            $query->where('placement_type', $filters['placement_type']);
        }

        // 4. Brand filter
        if (!empty($filters['brand'])) {
            $brands = is_array($filters['brand']) ? $filters['brand'] : explode(',', $filters['brand']);
            $query->whereHas('product', fn($q) => $q->whereIn('brand', $brands));
        }

        // 5. Product filter
        if (!empty($filters['product'])) {
            $products = is_array($filters['product']) ? $filters['product'] : explode(',', $filters['product']);
            $query->whereHas('product', fn($q) => $q->whereIn('name', $products));
        }

        // 6. Type-specific filters
        if ($type === 'hp') {
            $this->applyHpFilters($query, $filters);
        } else {
            $query->where('quantity', '>', 0);
        }

        return $query;
    }

    /**
     * Apply placement security restrictions based on user's accessible locations.
     */
    protected function applyPlacementSecurity(Builder $query, User $user): void
    {
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner', 'analist'];
        if ($user->hasRole($unrestrictedRoles)) {
            return;
        }

        $branchIds = (array) ($user->getAccessibleBranchIds() ?: []);
        $warehouseIds = (array) ($user->getAccessibleWarehouseIds() ?: []);
        $shopIds = (array) ($user->getAccessibleOnlineShopIds() ?: []);

        if ($user->branch_id) $branchIds[] = $user->branch_id;
        if ($user->warehouse_id) $warehouseIds[] = $user->warehouse_id;
        if ($user->online_shop_id) $shopIds[] = $user->online_shop_id;

        $branchIds = array_unique(array_filter($branchIds));
        $warehouseIds = array_unique(array_filter($warehouseIds));
        $shopIds = array_unique(array_filter($shopIds));

        $query->where(function ($q) use ($branchIds, $warehouseIds, $shopIds) {
            $hasConstraint = false;
            if (!empty($branchIds)) {
                $q->orWhere(fn($sq) => $sq->where('placement_type', 'branch')->whereIn('placement_id', $branchIds));
                $hasConstraint = true;
            }
            if (!empty($warehouseIds)) {
                $q->orWhere(fn($sq) => $sq->where('placement_type', 'warehouse')->whereIn('placement_id', $warehouseIds));
                $hasConstraint = true;
            }
            if (!empty($shopIds)) {
                $q->orWhere(fn($sq) => $sq->where('placement_type', 'online_shop')->whereIn('placement_id', $shopIds));
                $hasConstraint = true;
            }
            if (!$hasConstraint) {
                $q->whereRaw('0 = 1');
            }
        });
    }

    /**
     * Apply search filter across relevant fields.
     */
    protected function applySearch(Builder $query, string $search, string $type): void
    {
        if ($type === 'hp') {
            $query->where(function ($q) use ($search) {
                $q->where('imei', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%");
                    });
            });
        } else {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Apply HP-specific filters (status, capacity, condition).
     */
    protected function applyHpFilters(Builder $query, array $filters): void
    {
        // Status filter
        $status = $filters['status'] ?? ($filters['stock_status'] ?? null);
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['available', 'booking', 'returned', 'process']);
        }

        // Condition filter
        if (!empty($filters['condition']) && $filters['condition'] !== 'all') {
            $query->where('condition', $filters['condition']);
        }

        // Capacity filter
        if (!empty($filters['capacity'])) {
            $caps = is_array($filters['capacity']) ? $filters['capacity'] : explode(',', $filters['capacity']);
            $query->where(function ($q) use ($caps) {
                foreach ($caps as $cap) {
                    $cap = trim($cap);
                    if (str_contains($cap, '/')) {
                        [$ram, $storage] = explode('/', $cap);
                        $q->orWhere(fn($sq) => $sq->where('ram', $ram)->where('storage', $storage));
                    } else {
                        $q->orWhere('storage', $cap);
                    }
                }
            });
        }
    }
}
