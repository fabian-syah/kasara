<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$startDate = '2026-05-13';
$endDate = '2026-05-13';

$baseQuery = DB::table('stock_outs')->leftJoin('users', 'stock_outs.user_id', '=', 'users.id');

$startTS = $startDate . ' 05:00:00';
$endTS = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59';
$baseQuery->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
    $q->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
      ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
});

$transactions = (clone $baseQuery)->leftJoin('users as owners', function ($join) {
    $join->on('owners.id', '=', DB::raw('COALESCE(stock_outs.inventory_user_id, stock_outs.user_id)'));
})->select(
    'owners.id as owner_id',
    'owners.name as cs_name',
    'stock_outs.id as stock_out_id',
    'stock_outs.category',
    'stock_outs.selling_price',
    'stock_outs.notes',
    'stock_outs.sales_account',
    'stock_outs.receipt_id',
    DB::raw('(SELECT COALESCE(SUM(tt.outgoing_price), 0) FROM tukar_tambahs tt WHERE tt.receipt_id = stock_outs.receipt_id LIMIT 1) as tt_outgoing_price'),
    DB::raw('(SELECT COALESCE(SUM(dg.outgoing_price), 0) FROM downgrades dg WHERE dg.receipt_id = stock_outs.receipt_id LIMIT 1) as dg_outgoing_price')
)->get();

echo "Found " . $transactions->count() . " transactions.\n";
foreach ($transactions as $tx) {
    echo "Tx ID: {$tx->stock_out_id} | Category: {$tx->category} | Price: {$tx->selling_price} | TT Out: {$tx->tt_outgoing_price} | DG Out: {$tx->dg_outgoing_price} | CS: {$tx->cs_name} (OwnerID: {$tx->owner_id})\n";
}
