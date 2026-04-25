<?php
// check_ab.php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dayLogs = \App\Models\InventoryLog::where('created_at', '>=', '2026-04-25 05:00:00')->get();
foreach($dayLogs as $l) {
    if(stripos($l->description, '14 Pro Max') !== false) {
        $pd = $l->productDetail;
        echo "LOG ID: {$l->id} (type: {$l->type}) desc='{$l->description}' PD_ID=".($pd ? $pd->id : 'null')."\n";
        if($pd && $pd->product) {
            echo "   -> product_id={$pd->product->id} name='{$pd->product->name}' storage='{$pd->storage}' condition='{$pd->condition}'\n";
        }
    }
}

$outs = \App\Models\StockOut::where('created_at', '>=', '2026-04-25 05:00:00')->with('items.product')->get();
foreach($outs as $o) {
    if($o->category !== 'angkat_barang') continue;
    foreach($o->items as $pd) {
        if($pd->product && stripos($pd->product->name, '14 Pro Max') !== false) {
             echo "OUT AB ID: {$o->id} PD_ID={$pd->id} name='{$pd->product->name}' storage='{$pd->storage}' condition='{$pd->condition}'\n";
        }
    }
}
