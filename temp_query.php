<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$out = App\Models\StockOut::whereIn('category', ['tukar_unit', 'tukar_tambah', 'downgrade'])
    ->orderBy('id', 'desc')
    ->take(3)
    ->get(['id', 'receipt_id', 'category', 'selling_price', 'total_amount', 'total_discount', 'paid'])
    ->toArray();
echo json_encode($out, JSON_PRETTY_PRINT);
