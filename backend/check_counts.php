<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductDetail;
use App\Models\Inventory;

$hpCount = ProductDetail::whereIn('status', ['available', 'booking', 'returned', 'process'])->count();
$nonHpCount = Inventory::where('quantity', '>', 0)->count();

echo "HP Count (available, booking, returned, process): " . $hpCount . "\n";
echo "Non-HP Count (quantity > 0): " . $nonHpCount . "\n";
echo "Total: " . ($hpCount + $nonHpCount) . "\n";
