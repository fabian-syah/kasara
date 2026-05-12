<?php
require 'vendor/autoload.php';
require 'app/Utils/SimpleXLSXGen.php';

use App\Utils\SimpleXLSXGen;

echo "Loading Deep Test...\n";
try {
    $row1 = [
        '2026-05-12', 'ORD1', 'LOK', 'USR', 'CUS', 'WA', 'CAT', 'BUND',
        'PRODUK_OUT', 'IMEI_OUT', '1', '500000', 'DIST', 'PRODUK_IN', 'IMEI_IN', '1', '400000', 'DIST', '50000', // 19 cols
        'PAY1', 'PAY2', // Payment Methods
        '500000', '100000', 'LUNAS' // Aggregations
    ];
    $row2 = [
        '2026-05-12', 'ORD2', 'LOK', 'USR', 'CUS', 'WA', 'CAT', 'BUND',
        'PRODUK_OUT2', 'IMEI_OUT2', '1', '200000', 'DIST', '', '', '', '', '', '',
        5000.0, '', 
        200000, 0, 'LUNAS',
        '__bg_striped' => true
    ];
    
    $xlsx = SimpleXLSXGen::fromArray([$row1, $row2]);
    $content = (string)$xlsx;
    
    echo "DEEP SUCCESS! Generated length: " . strlen($content) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
