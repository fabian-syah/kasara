<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$trx = DB::table('stock_outs')->where('receipt_id', 'O21AUG-G60')->first();
echo "Trx:\n";
echo json_encode($trx, JSON_PRETTY_PRINT) . "\n";

$hpItems = DB::table('stock_out_items')->where('stock_out_id', $trx->id)->get();
echo "HP Items:\n";
echo json_encode($hpItems, JSON_PRETTY_PRINT) . "\n";

$nonHpItems = DB::table('stock_out_non_hp_items')->where('stock_out_id', $trx->id)->get();
echo "Non-HP Items:\n";
echo json_encode($nonHpItems, JSON_PRETTY_PRINT) . "\n";
