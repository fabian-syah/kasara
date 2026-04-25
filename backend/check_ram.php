<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductDetail;
use Illuminate\Support\Facades\DB;

$ramStats = ProductDetail::select('ram', DB::raw('count(*) as count'))
    ->groupBy('ram')
    ->get();

echo "RAM Distribution in ProductDetails:\n";
foreach ($ramStats as $stat) {
    echo ($stat->ram ?: '[NULL]') . ": " . $stat->count . "\n";
}
