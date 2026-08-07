<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tt = DB::table('tukar_tambahs')->where('receipt_id', 'TT07Aug26-013')->first();
if ($tt) {
    $items = [
        [
            'product_type_id' => $tt->incoming_product_type_id,
            'imeis' => [$tt->incoming_imei],
            'storage' => $tt->incoming_storage,
            'condition' => $tt->incoming_condition,
            'buy_price' => $tt->incoming_cost_price,
            'quantity' => 1,
            'distributor_id' => $tt->distributor_id
        ],
        [
            'product_type_id' => 23, // XR
            'imeis' => ['XR-TEST-8890'],
            'storage' => '128 GB',
            'condition' => 'second',
            'buy_price' => 3000000,
            'quantity' => 1,
            'distributor_id' => $tt->distributor_id
        ]
    ];
    DB::table('tukar_tambahs')->where('id', $tt->id)->update(['incoming_items' => json_encode($items)]);
    echo "Updated successfully.\\n";
} else {
    echo "Not found.\\n";
}
