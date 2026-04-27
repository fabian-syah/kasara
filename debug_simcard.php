<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$items = DB::table('stock_out_non_hp_items')
    ->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')
    ->where('products.name', 'LIKE', '%pembuatan iCloud%')
    ->select('stock_out_non_hp_items.*', 'products.name as product_name')
    ->get();

foreach ($items as $item) {
    echo "Item: {$item->product_name}, ID: {$item->id}, Distributor ID: " . ($item->distributor_id ?? 'NULL') . "\n";
}
