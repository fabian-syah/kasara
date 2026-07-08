<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$lux = DB::table('payment_methods')->where('name', 'like', '%apple lux%')->first();
if (!$lux) die("No apple lux");
echo "Apple Lux ID: " . $lux->id . "\n";

$branch = DB::table('branches')->where('name', 'like', '%BIG JAKARTA%')->first();
echo "Branch ID: " . $branch->id . "\n";

$txs = DB::table('stock_outs')
    ->where('branch_id', $branch->id)
    ->where(function($q) use ($lux) {
        $q->where('payment_method_id', $lux->id)
          ->orWhere('split_payments', 'like', '%"payment_method_id":' . $lux->id . '%')
          ->orWhere('split_payments', 'like', '%"payment_method_id": "' . $lux->id . '"%');
    })
    ->get(['id', 'category', 'selling_price', 'payment_method_id', 'split_payments']);

foreach ($txs as $tx) {
    echo "TX {$tx->id}: Category {$tx->category}, Price {$tx->selling_price}\n";
    if ($tx->split_payments) {
        echo "  Splits: {$tx->split_payments}\n";
    }
}
