<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $export = new \App\Exports\SalesExport();
    echo "SalesExport created\n";
    $res = \Maatwebsite\Excel\Facades\Excel::download($export, 'test.xlsx');
    echo "Excel::download called\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
