<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$receiptId = 'O14FEB-KPP'; // The specific receipt
$targetWarehouseName = 'ANU'; // The name seen in the screenshot

echo "--- Debugging Stock Out: $receiptId ---\n";

$stockOut = App\Models\StockOut::where('receipt_id', $receiptId)->first();

if (!$stockOut) {
    echo "ERROR: Stock Out record not found!\n";
    exit(1);
}

echo "Current State:\n";
echo "  ID: {$stockOut->id}\n";
echo "  Category: {$stockOut->category}\n";
echo "  Destination Type: " . ($stockOut->destination_type ?? 'NULL') . "\n";
echo "  Destination ID: " . ($stockOut->destination_id ?? 'NULL') . "\n";

// RELATIONSHIP CHECK
try {
    $dest = $stockOut->destination;
    echo "  Destination Relation: " . ($dest ? "Found: {$dest->name}" : "NULL") . "\n";
} catch (\Exception $e) {
    echo "  Destination Relation: ERROR (" . $e->getMessage() . ")\n";
}

// LOOKING FOR TARGET WAREHOUSE
echo "\n--- Searching for Warehouse '$targetWarehouseName' ---\n";
$warehouse = App\Models\Warehouse::where('name', 'LIKE', "%$targetWarehouseName%")->first();

if ($warehouse) {
    echo "Found Warehouse: [{$warehouse->id}] {$warehouse->name}\n";

    // FIXING
    if ($stockOut->destination_id != $warehouse->id || $stockOut->destination_type != 'warehouse') {
        echo "\nFixing Record...\n";
        $stockOut->destination_type = 'warehouse';
        $stockOut->destination_id = $warehouse->id;
        // Ensure status is pending if it's supposed to be incoming, or received if already done? 
        // User said "transfer masuk barangnya gaada", implying it's expected to be 'pending'.
        // Current status check
        echo "  Current Status: {$stockOut->status}\n";

        $stockOut->save();
        echo "SUCCESS: Record updated to point to Warehouse '{$warehouse->name}' (ID: {$warehouse->id}).\n";
        echo "Please refresh the 'Lacak Barang' and 'Transfer Masuk' pages.\n";
    } else {
        echo "Record already points to this warehouse. No changes needed on DB level.\n";
        echo "If still not showing, check 'destination_type' string matches AppServiceProvider morphMap.\n";
    }

} else {
    echo "ERROR: Could not find a warehouse named '$targetWarehouseName'.\n";
    echo "Available Warehouses:\n";
    foreach (App\Models\Warehouse::all() as $w) {
        echo "  - [{$w->id}] {$w->name}\n";
    }
}
