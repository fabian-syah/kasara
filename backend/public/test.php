<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$items = \App\Models\ProductDetail::withTrashed()->where('imei', '356873113907276')->get();
echo json_encode($items->toArray(), JSON_PRETTY_PRINT);
