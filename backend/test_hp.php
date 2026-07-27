<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$req = Illuminate\Http\Request::create('/api/audit/sales', 'GET', ['start_date' => '2026-07-26', 'end_date' => '2026-07-26', 'branch_id' => '71']);
$req->setUserResolver(function() { return App\Models\User::first(); });
$controller = new App\Http\Controllers\AuditController();
$res = $controller->sales($req);

$data = $res->getData(true);
$summary = $data['report_summary'];

echo "Penjualan HP (mapRp['hp']): Rp " . number_format($summary['dist_map_rp']['hp'] ?? 0, 0, ',', '.') . "\n";
echo "Total Omset (payment_total): Rp " . number_format($summary['payment_total'] ?? 0, 0, ',', '.') . "\n";
echo "Total Cancel: " . ($summary['audit_stats']['total_cancel'] ?? 0) . "\n";
