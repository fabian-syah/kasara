<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = \Illuminate\Http\Request::create('/api/reports/stock-movement', 'GET', [
    'date' => '2026-04-25',
    'mode' => 'daily'
]);
$controller = new \App\Http\Controllers\ReportController();
$response = $controller->getStockHistory($request);
$data = json_decode($response->getContent(), true);

echo count($data['hp']) . " HP items found.\n";
$count = 0;
foreach($data['hp'] as $row) {
    if(stripos($row['name'], '14 Pro Max') !== false) {
        $count++;
        echo "ROW {$count}: " . json_encode($row) . "\n\n";
    }
}
