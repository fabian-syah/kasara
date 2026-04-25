<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\ReportController;

// 1. Ambil user salestrial sesuai request
$user = User::where('username', 'salestrial')->first();
if (!$user) {
    // Kalau username gak ketemu, coba pake ID cabang ke-69
    $user = User::where('branch_id', 69)->first();
}

if (!$user) die("User salestrial (Cabang 69) nggak ketemu nih kak!\n");

echo "MENSIMULASIKAN REPORT SEBAGAI USER: " . $user->username . " (Branch ID: " . $user->branch_id . ")\n\n";

// 2. Bikin instance Request palsu
$request = Request::create('/api/reports/stock-history', 'GET', [
    'date' => date('Y-m-d'),
    'branch_id' => 69 
]);
$request->setUserResolver(function () use ($user) { return $user; });

// 3. Panggil method
$controller = new ReportController();
try {
    $response = $controller->getStockHistory($request);
    $data = json_decode($response->getContent(), true);

    if (isset($data['data']['hp'])) {
        $hp = $data['data']['hp'];
        echo "Total Item HP di Cabang Ini: " . count($hp) . "\n\n";

        // Cari yang namanya ngandung 14 Pro Max
        $found = [];
        foreach ($hp as $item) {
            if (stripos($item['name'], '14 Pro Max') !== false) {
                $found[] = $item;
            }
        }

        echo "--- KETEMU " . count($found) . " BARIS '14 Pro Max' ---\n";
        foreach ($found as $idx => $f) {
            echo "[" . ($idx + 1) . "] Nama: " . $f['name'] . "\n";
            echo "    Initial: {$f['initial']} | In Total: {$f['in_total']} | Out Total: {$f['out_total']} | Final: {$f['final']}\n";
            echo "    Type: {$f['type']} | Has IMEI: " . ($f['has_imei'] ? 'true' : 'false') . "\n";
            echo "----------------------------------------\n";
        }
        
    } else {
        echo "Data HP gak ditemuin di response!\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
