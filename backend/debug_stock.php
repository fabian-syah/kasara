<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$stockOut = App\Models\StockOut::where('receipt_id', 'O14FEB-WUA')->first();

if ($stockOut) {
    echo "ID: " . $stockOut->id . "\n";
    echo "Category: " . $stockOut->category . "\n";
    echo "Destination Type: " . $stockOut->destination_type . "\n";
    echo "Destination ID: " . $stockOut->destination_id . "\n";
    echo "Status: " . $stockOut->status . "\n";
    echo "Items Count: " . $stockOut->items()->count() . "\n";
} else {
    echo "Stock Out not found\n";
}
