<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$export = new \App\Exports\SalesExport();
$pms = $export->getPaymentMethods();

echo "Payment Methods Order:\n";
foreach ($pms as $pm) {
    echo "- " . $pm->name . "\n";
}

echo "\n--- Simulasi Waterfall ---\n";
$totalPenjualan = 6100000;
echo "Total Penjualan: Rp " . number_format($totalPenjualan, 0, ',', '.') . "\n";

$payData = [
    'Cash Toko' => 0,
    'EDC BCA' => 0,
    'transfer apple lux' => 0,
    'tf coka' => 0,
    'transfer BCA' => 6100000, // Misal bayarnya pakai transfer BCA
];

echo "Pembayaran Masuk:\n";
foreach ($payData as $name => $amt) {
    if ($amt > 0) {
        echo "- $name: Rp " . number_format($amt, 0, ',', '.') . "\n";
    }
}

echo "\nHasil Pisah (Waterfall):\n";
$unpaid = $totalPenjualan;
foreach ($pms as $pm) {
    $pmAmount = $payData[$pm->name] ?? 0;
    if ($unpaid <= 0) {
        $allocated = 0;
    } else {
        $allocated = min($unpaid, $pmAmount);
        $unpaid -= $allocated;
    }
    echo "Pisah " . $pm->name . " = Rp " . number_format($allocated, 0, ',', '.') . "\n";
}
