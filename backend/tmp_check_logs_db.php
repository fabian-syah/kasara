<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$resetTime = \Carbon\Carbon::now()->setTime(5, 0, 0);

$logs = \App\Models\InventoryLog::with('productDetail.product')
    ->where('created_at', '>=', $resetTime)
    ->where('type', 'in')
    ->get();

echo "--- SEMUA INVENTORY LOG MASUK HARI INI ---\n";
foreach($logs as $log) {
    if (!$log->productDetail || !$log->productDetail->product) continue;
    $pName = $log->productDetail->product->name;
    if (in_array($pName, ['Iphone XS', 'Iphone SE 2020', 'Iphone XR'])) {
        echo "ID Log: {$log->id} | Name: $pName | Desc: {$log->description}\n";
    }
}
