<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockOut;

$so = StockOut::where('receipt_id', 'O11MAY-ZQS')->first();
if ($so) {
    echo json_encode([
        'receipt_id' => $so->receipt_id,
        'cancelled_by' => $so->cancelled_by,
        'cancel_reason' => $so->cancel_reason,
        'status' => $so->status,
        'cancelled_by_name' => $so->cancelledByUser?->name
    ], JSON_PRETTY_PRINT);
} else {
    echo "Record not found for O11MAY-ZQS\n";
}
