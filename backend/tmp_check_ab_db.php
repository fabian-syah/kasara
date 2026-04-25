<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$resetTime = \Carbon\Carbon::now()->setTime(5, 0, 0);

$outs = \App\Models\StockOut::with(['items.product'])
    ->where('category', 'angkat_barang')
    ->where('created_at', '>=', $resetTime)
    ->get();

echo "Ada " . count($outs) . " transaksi Angkat Barang hari ini.\n";

foreach($outs as $out) {
    echo "ID: {$out->id} | Branch: {$out->branch_id} | Created: {$out->created_at}\n";
    foreach($out->items as $item) {
        if ($item->product) {
            echo "  -> Prod: " . $item->product->name . " | Storage: " . $item->storage . "\n";
        } else {
            echo "  -> (Items array exists tapi ProductDetail NULL)\n";
        }
    }
}
