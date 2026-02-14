<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$stockOut = App\Models\StockOut::with(['destination', 'destinationBranch'])->where('receipt_id', 'O14FEB-KPP')->first();

if ($stockOut) {
    echo "ID: " . $stockOut->id . "\n";
    echo "Receipt: " . $stockOut->receipt_id . "\n";
    echo "Category: " . $stockOut->category . "\n";
    echo "Destination Type (DB): " . $stockOut->destination_type . "\n";
    echo "Destination ID (DB): " . $stockOut->destination_id . "\n";
    echo "Destination Relation: " . ($stockOut->destination ? $stockOut->destination->name : 'NULL') . "\n";
    echo "Destination Branch Relation: " . ($stockOut->destinationBranch ? $stockOut->destinationBranch->name : 'NULL') . "\n";

    // Check if there are any items
    if ($stockOut->items->count() > 0) {
        $firstItem = $stockOut->items->first();
        echo "Item 1 IMEI: " . $firstItem->imei . "\n";
        echo "Item 1 Status: " . $firstItem->status . "\n";
    }
} else {
    echo "Stock Out not found\n";
}
