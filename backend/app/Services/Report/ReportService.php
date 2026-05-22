<?php

namespace App\Services\Report;

use App\Models\User;

/**
 * Handles report generation including sales reports, stock mutation reports,
 * and scheduled report dispatching.
 */
class ReportService
{
    public function __construct()
    {
        //
    }

    /**
     * Generate a sales report for the given parameters.
     *
     * @param User $user The user requesting the report
     * @param array $params Report parameters (date range, branch, etc.)
     * @return array Report data
     */
    public function generateSalesReport(User $user, array $params): array
    {
        // TODO: Extract from ReportController
        return [];
    }

    /**
     * Generate a stock mutation report for the given parameters.
     *
     * @param User $user The user requesting the report
     * @param array $params Report parameters (date range, product, placement, etc.)
     * @return array Report data
     */
    public function generateStockMutationReport(User $user, array $params): array
    {
        // TODO: Extract from ReportController
        return [];
    }
}
