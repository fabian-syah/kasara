<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$startDate = '2026-04-27';
$endDate = '2026-04-27';

// Simulating applyLocalScope logic for a specific branch
// Let's find CABANG TRIAL ID first
$branch = DB::table('branches')->where('name', 'LIKE', '%CABANG TRIAL%')->first();
$requestedBranchId = $branch ? $branch->id : null;

echo "Branch ID: " . ($requestedBranchId ?? 'NULL') . "\n";

$query = DB::table('stock_outs')
    ->whereBetween('reporting_date', [$startDate, $endDate]);

if ($requestedBranchId) {
    $query->where(function ($q) use ($requestedBranchId) {
        $q->where('stock_outs.branch_id', $requestedBranchId)
          ->orWhereExists(function ($sub) use ($requestedBranchId) {
              $sub->select(DB::raw(1))
                  ->from('users')
                  ->whereRaw('users.id = stock_outs.user_id')
                  ->where('users.branch_id', $requestedBranchId);
          });
    });
}

$results = $query->get();

echo "Found " . $results->count() . " stock_outs for 2026-04-27\n";
foreach ($results as $r) {
    echo "ID: {$r->id}, Category: {$r->category}, Receipt: {$r->receipt_id}, Branch: {$r->branch_id}\n";
}

// Check if there are any stock_outs at all for this date
$allDate = DB::table('stock_outs')->where('reporting_date', $startDate)->count();
echo "Total stock_outs for this date (any location): $allDate\n";
