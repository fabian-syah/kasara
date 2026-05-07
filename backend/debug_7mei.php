<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StockOut;
use Illuminate\Support\Facades\DB;

$date = '2026-05-07';
$sos = StockOut::where('reporting_date', $date)->get();

echo "Total stock_outs for {$date}: " . $sos->count() . "\n";
foreach ($sos as $so) {
    echo "ID: {$so->id}, Category: {$so->category}, Notes: {$so->notes}, SalesAccount: {$so->sales_account}, Price: {$so->selling_price}, Receipt: {$so->receipt_id}\n";
}
