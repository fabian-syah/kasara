<?php

namespace App\Services\StockOut;

use App\Models\User;

/**
 * Handles stock-out business logic including sales, returns (retur),
 * and non-HP item stock-out processing.
 */
class StockOutService
{
    public function __construct()
    {
        //
    }

    /**
     * Process a stock-out transaction.
     *
     * @param User $user The user performing the stock-out
     * @param array $data Validated stock-out data
     * @return array Result containing the created stock-out record
     */
    public function processStockOut(User $user, array $data): array
    {
        // TODO: Extract from StockOutController
        return [];
    }

    /**
     * Void (cancel) a stock-out transaction.
     *
     * @param User $user The user performing the void
     * @param int $stockOutId The stock-out ID to void
     * @return bool Whether the void was successful
     */
    public function voidStockOut(User $user, int $stockOutId): bool
    {
        // TODO: Extract from StockOutController
        return false;
    }
}
