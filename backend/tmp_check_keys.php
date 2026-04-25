<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\ReportController;

$user = User::where('username', 'salestrial')->first() ?? User::where('branch_id', 69)->first();
if (!$user) die("User salestrial (Cabang 69) nggak ketemu nih kak!\n");

// Bikin instance Request palsu
$request = Request::create('/api/reports/stock-history', 'GET', [
    'date' => date('Y-m-d'),
    'branch_id' => 69 
]);
$request->setUserResolver(function () use ($user) { return $user; });

// Kita copy mentahan ReportController supaya bisa langsung print KEY-nya
$targetDate = \Carbon\Carbon::now();
$resetTime = $targetDate->copy()->setTime(5, 0, 0);
$endTime = $resetTime->copy()->addDay();

$normalize = function($brand, $name, $storage, $condition) {
    $b = trim($brand ?? '');
    $n = trim($name ?? '');
    $s = trim($storage ?? '');
    $c = trim($condition ?? 'second');

    $b = trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $b));
    $n = trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $n));
    $s = trim(preg_replace('/[\xA0\s]+/', ' ', $s));

    $dispName = "{$b} {$n}";
    $dispName = trim(preg_replace('/\s+/', ' ', str_replace('™', '', $dispName)));

    if ($s) $dispName .= " ({$s})";
    $dispName .= " (" . ($c === 'new' ? 'Baru' : ($c === 'ex_ibox' ? 'Ex iBox' : 'Bekas')) . ")";

    return [
        'display' => $dispName,
        'key' => md5(preg_replace('/[^a-z0-9]/', '', strtolower($dispName))),
        'cleanStr' => preg_replace('/[^a-z0-9]/', '', strtolower($dispName))
    ];
};

$results = [];
$defaultRow = [
    'initial' => 0, 'in_total' => 0, 'in_manual' => 0, 'in_tt' => 0, 'in_tu' => 0, 'in_dw' => 0, 'in_rf' => 0, 'in_ab' => 0,
    'out_total' => 0, 'out_sold' => 0, 'out_tt' => 0, 'out_tu' => 0, 'out_dw' => 0, 'out_pindah' => 0, 'out_kesalahan' => 0, 'out_keluar' => 0, 'out_hilang' => 0, 'out_retur' => 0,
    'final' => 0
];

echo "--- 1. CEK CURRENT STOCK ---\n";
// 2. Get CURRENT REAL-TIME STOCK
$currentStock = \App\Models\ProductDetail::join('products', 'product_details.product_id', '=', 'products.id')
    ->select(
        'products.id as product_id',
        'products.brand',
        'products.name as product_name',
        'products.type',
        'products.has_imei',
        'product_details.storage',
        'product_details.condition',
        \DB::raw('count(*) as qty')
    )
    ->where('product_details.status', 'available')
    ->where('product_details.placement_id', 69)
    ->where('product_details.placement_type', 'branch')
    ->groupBy('products.id', 'products.brand', 'products.name', 'products.type', 'products.has_imei', 'product_details.storage', 'product_details.condition');

foreach($currentStock->get() as $s) {
    if (stripos($s->product_name, '14 Pro Max') !== false) {
        $norm = $normalize($s->brand, $s->product_name, $s->storage, $s->condition);
        echo "CURRENT | Name: {$norm['display']} | CleanStr: {$norm['cleanStr']} | Key: {$norm['key']}\n";
    }
    $norm = $normalize($s->brand, $s->product_name, $s->storage, $s->condition);
    $groupKey = $norm['key'];
    if (!isset($results[$groupKey])) { $results[$groupKey] = array_merge($defaultRow, ['name' => $norm['display']]); }
    $results[$groupKey]['final'] += $s->qty;
}

echo "--- 2. CEK INVENTORY LOG (MASUK) ---\n";
$dayLogs = \App\Models\InventoryLog::with('productDetail.product')
    ->where('created_at', '>=', $resetTime)
    ->where('created_at', '<', $endTime)
    ->where('type', 'in')
    ->where('branch_id', 69);

foreach($dayLogs->get() as $log) {
    if ($log->description && (str_contains($log->description, 'Pindah Cabang') || str_contains($log->description, 'Resi:'))) continue;
    $pd = $log->productDetail;
    if (!$pd) continue;

    if (stripos($pd->product->name, '14 Pro Max') !== false) {
        $norm = $normalize($pd->product->brand, $pd->product->name, $pd->storage, $pd->condition);
        echo "INV_LOG | Name: {$norm['display']} | CleanStr: {$norm['cleanStr']} | Key: {$norm['key']}\n";
    }
    $norm = $normalize($pd->product->brand ?? '', $pd->product->name ?? '', $pd->storage, $pd->condition);
    $groupKey = $norm['key'];
    if (!isset($results[$groupKey])) { $results[$groupKey] = array_merge($defaultRow, ['name' => $norm['display']]); }
    $results[$groupKey]['in_total'] += ($log->quantity ?? 1);
}

echo "--- 3. CEK STOCK OUT (KELUAR) ---\n";
$dayOuts = \App\Models\StockOut::with(['items.product', 'nonHpItems.product'])
    ->where('created_at', '>=', $resetTime)
    ->where('created_at', '<', $endTime)
    ->where('status', '!=', 'cancelled')
    ->where(function($q) {
        $q->whereIn('branch_id', [69])->orWhere('destination_id', [69]);
    });

$incomingAuditCategories = ['barang_masuk', 'pembelian', 'cancel_penjualan', 'retur_customer'];

foreach($dayOuts->get() as $out) {
    $isAB = $out->category === 'angkat_barang';
    $isIncoming = in_array($out->category, $incomingAuditCategories) || $isAB;

    foreach($out->items as $pd) {
        if (stripos($pd->product->name, '14 Pro Max') !== false) {
            $norm = $normalize($pd->product->brand, $pd->product->name, $pd->storage, $pd->condition);
            echo "STK_OUT | Name: {$norm['display']} | CleanStr: {$norm['cleanStr']} | Key: {$norm['key']}\n";
        }
        $norm = $normalize($pd->product->brand ?? '', $pd->product->name ?? '', $pd->storage, $pd->condition);
        $groupKey = $norm['key'];

        if (!isset($results[$groupKey])) {
            $results[$groupKey] = array_merge($defaultRow, ['name' => $norm['display']]);
        }
        if ($isIncoming) {
            $results[$groupKey]['in_total']++;
        } else {
            $results[$groupKey]['out_total']++;
        }
    }
}

echo "\n--- HASIL AKHIR SETELAH PUSH (HARUSNYA 1 KEY) ---\n";
$found = [];
foreach($results as $k => $row) {
    if (stripos($row['name'], '14 Pro Max') !== false) {
        if ($row['final'] - $row['in_total'] + $row['out_total'] == 0 && $row['in_total'] == 0 && $row['out_total'] == 0 && $row['final'] == 0) continue;
        echo "KEY: {$k} | Name: {$row['name']} | Initial: " . ($row['final'] - $row['in_total'] + $row['out_total']) . "\n";
    }
}
