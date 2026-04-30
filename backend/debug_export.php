<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $startDate = '2026-04-30';
    $endDate = '2026-04-30';
    $user = \App\Models\User::first();
    echo "Using user: " . $user->name . "\n";
    
    $export = new \App\Exports\SalesExport(null, null, $startDate, $endDate, $user);
    echo "SalesExport created\n";
    $headings = $export->headings();
    echo "Headings retrieved\n";
    $rows = $export->collection();
    echo "Collection retrieved, count: " . count($rows) . "\n";
    
    foreach ($rows as $row) {
        // test data integrity
        array_values($row);
    }
    echo "All rows processed\n";
    
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
