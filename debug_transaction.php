<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StockOut;
use Illuminate\Support\Facades\DB;

$receipt = 'O23APR-V1Z';
$so = StockOut::where('receipt_id', $receipt)->first();

if (!$so) {
    echo "Transaction $receipt not found.\n";
    exit;
}

echo "Transaction Found:\n";
echo "ID: " . $so->id . "\n";
echo "Receipt: " . $so->receipt_id . "\n";
echo "Reporting Date: " . $so->reporting_date . "\n";
echo "Branch ID: " . $so->branch_id . "\n";
echo "User ID: " . $so->user_id . "\n";
echo "Created At: " . $so->created_at . "\n";

$user = $so->user;
echo "User Name: " . ($user->name ?? 'N/A') . "\n";
echo "User Branch ID: " . ($user->branch_id ?? 'N/A') . "\n";

$items = $so->nonHpDetails;
echo "Items Count: " . $items->count() . "\n";
foreach($items as $i) {
    echo " - " . ($i->product->name ?? 'N/A') . " (Dist: " . $i->distributor_id . ")\n";
}
