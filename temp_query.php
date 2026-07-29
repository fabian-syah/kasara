<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$warehouse = \App\Models\Warehouse::where('name', 'ilike', '%audit%')->orWhere('code', 'ilike', '%audit%')->first();
if ($warehouse) {
    echo "ID: " . $warehouse->id . "\n";
    echo "Name: " . $warehouse->name . "\n";
    echo "Code: " . $warehouse->code . "\n";
} else {
    echo "Audit warehouse not found.\n";
}
