<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$branchId = 9; // PSTORE BOGOR
$startDate = '2026-04-01';
$endDate = '2026-04-30';
$startTS = $startDate . ' 05:00:00';
$endTS = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59';

$salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'refund', 'angkat_barang', 'sale', 'pos', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'brand_ambassador', 'event_/_sponsorship'];
$normalizedSalesCategories = array_unique(array_map(fn($c) => strtolower(str_replace(' ', '_', $c)), $salesCategories));

// Query 1: Simple count of all stock_outs in April 2026 for Bogor
$q1 = DB::table('stock_outs')
    ->leftJoin('users', 'stock_outs.user_id', '=', 'users.id')
    ->where(function ($q) use ($branchId) {
        $q->where('stock_outs.branch_id', $branchId)
            ->orWhere('users.branch_id', $branchId);
    })
    ->whereNull('stock_outs.deleted_at')
    ->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
        $q->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
            ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
    });

$totalCount = $q1->count();
echo "Total transactions in April 2026 for Bogor: " . $totalCount . "\n";

// Query 2: Stats with categories
$stats = $q1->select(
    'stock_outs.category',
    DB::raw('COUNT(*) as count'),
    DB::raw('SUM(selling_price) as sum_selling_price')
)->groupBy('stock_outs.category')->get();

echo "Transactions by category:\n";
foreach ($stats as $s) {
    echo "Category: {$s->category}, Count: {$s->count}, Sum Price: {$s->sum_selling_price}\n";
}

// Query 3: Running the exact Dashboard calculation
$query = DB::table('stock_outs')
    ->leftJoin('users', 'stock_outs.user_id', '=', 'users.id')
    ->whereIn(DB::raw("LOWER(REPLACE(stock_outs.category, ' ', '_'))"), $normalizedSalesCategories)
    ->whereNull('stock_outs.deleted_at')
    ->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
        $q->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
            ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
    });

$dashboardStats = $query->select(
    DB::raw('COALESCE(stock_outs.branch_id, users.branch_id) as branch_id'),
    DB::raw("SUM(
        CASE 
            WHEN (LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'tukar_tambah', 'bundling', 'brand_ambassador', 'event_/_sponsorship'))
                 AND NOT (LOWER(stock_outs.notes) LIKE '%tukar unit%' OR LOWER(stock_outs.notes) LIKE '%tukar_unit%' OR LOWER(stock_outs.sales_account) LIKE '%tukar unit%' OR LOWER(stock_outs.sales_account) LIKE '%tukar_unit%')
            THEN ABS(COALESCE(stock_outs.selling_price, 0))
            ELSE 0
        END
    ) as total_omset"),
    DB::raw("SUM(
        CASE 
            WHEN (LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship'))
                 AND NOT (LOWER(stock_outs.notes) LIKE '%tukar unit%' OR LOWER(stock_outs.notes) LIKE '%tukar_unit%' OR LOWER(stock_outs.sales_account) LIKE '%tukar unit%' OR LOWER(stock_outs.sales_account) LIKE '%tukar_unit%')
            THEN ABS(COALESCE(stock_outs.selling_price, 0))
            WHEN (LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('refund', 'angkat_barang', 'downgrade'))
                 OR (
                     NOT (LOWER(REPLACE(stock_outs.category, ' ', '_')) IN ('shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'tukar_tambah', 'brand_ambassador', 'event_/_sponsorship'))
                     AND (
                         (LOWER(stock_outs.notes) LIKE '%refund%' OR LOWER(stock_outs.sales_account) LIKE '%refund%')
                         OR (LOWER(stock_outs.notes) LIKE '%barang angkat%' OR LOWER(stock_outs.notes) LIKE '%angkat barang%' OR LOWER(stock_outs.notes) LIKE '%angkat_barang%' OR LOWER(stock_outs.sales_account) LIKE '%barang angkat%' OR LOWER(stock_outs.sales_account) LIKE '%angkat barang%' OR LOWER(stock_outs.sales_account) LIKE '%angkat_barang%')
                         OR (LOWER(stock_outs.notes) LIKE '%downgrade%' OR LOWER(stock_outs.sales_account) LIKE '%downgrade%')
                     )
                 )
            THEN -ABS(COALESCE(stock_outs.selling_price, 0))
            ELSE 0
        END
    ) as omset_bersih")
)->groupBy(DB::raw('COALESCE(stock_outs.branch_id, users.branch_id)'))->get();

echo "Dashboard stats for all branches:\n";
foreach ($dashboardStats as $ds) {
    if ($ds->branch_id == 9) {
        echo "BOGOR -> Total Omset: {$ds->total_omset}, Omset Bersih: {$ds->omset_bersih}\n";
    } else {
        echo "Branch ID {$ds->branch_id} -> Total Omset: {$ds->total_omset}, Omset Bersih: {$ds->omset_bersih}\n";
    }
}
