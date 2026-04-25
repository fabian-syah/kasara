<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Fake admin user for testing
$user = \App\Models\User::first();
Auth::login($user);

$request = \Illuminate\Http\Request::create('/api/reports/stock-movement', 'GET', [
    'date' => '2026-04-25',
    'mode' => 'daily'
]);
$controller = new \App\Http\Controllers\ReportController();
$response = $controller->getStockHistory($request);
$data = json_decode($response->getContent(), true);

$count = 0;
foreach($data['hp'] as $i => $row) {
    if(stripos($row['name'], '14 Pro Max') !== false) {
        $count++;
        echo "INDEX {$i} -> name: '{$row['name']}' in: {$row['in_total']} ab: {$row['in_ab']} final: {$row['final']}\n";
    }
}
echo "Total 14 Pro Max rows: {$count}\n";
