<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$date = '2026-05-06';
$transactions = DB::table('stock_outs')
    ->where('reporting_date', $date)
    ->orWhereBetween('created_at', [$date . ' 05:00:00', date('Y-m-d', strtotime($date . ' +1 day')) . ' 04:59:59'])
    ->get();

echo "Total transactions found: " . count($transactions) . "\n";
echo "No\tID\tReceipt ID\tCategory\tSelling Price\tNotes\tSales Account\tSplit Payments\n";
$i = 1;
foreach ($transactions as $t) {
    echo $i++ . "\t" . $t->id . "\t" . $t->receipt_id . "\t" . $t->category . "\t" . $t->selling_price . "\t" . $t->notes . "\t" . $t->sales_account . "\t" . $t->split_payments . "\n";
}
