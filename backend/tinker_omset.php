<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockOut;
use App\Models\TukarTambah;
use App\Models\Downgrade;
use App\Models\Branch;
use Carbon\Carbon;

$date = '2026-08-21';
$branch = Branch::where('name', 'PStore CIKARANG')->first();

if (!$branch) {
    die("Branch PStore CIKARANG not found.\n");
}

$branchId = $branch->id;
echo "Found Branch: " . $branch->name . " (ID: $branchId)\n\n";

$start = Carbon::parse($date)->startOfDay();
$end = Carbon::parse($date)->endOfDay();

// 1. Penjualan Store (dan penjualan normal lainnya)
$normalSales = StockOut::where('reporting_date', $date)
    ->where('branch_id', $branchId)
    ->where('status', '!=', 'cancelled')
    ->whereNotIn('category', ['tukar_tambah', 'downgrade', 'refund', 'angkat_barang', 'tukar_unit', 'cancel_penjualan'])
    ->get();

$penjualanStore = 0;
foreach($normalSales as $sale) {
    $spTotal = 0;
    if ($sale->split_payments) {
        $sData = is_string($sale->split_payments) ? json_decode($sale->split_payments, true) : $sale->split_payments;
        if (is_array($sData)) {
            foreach ($sData as $sp) {
                $spTotal += abs((float) ($sp['amount'] ?? 0));
            }
        }
    }
    $priceTarget = $sale->category === 'pelunasan_dp' 
        ? max(0, abs((float) ($sale->paid_amount ?? 0))) 
        : max(0, abs((float) ($sale->selling_price ?? 0)));
    
    $omset = ($spTotal > 0) ? min($spTotal, $priceTarget) : $priceTarget;
    $penjualanStore += $omset;
    echo "NORMAL SALE: " . str_pad($sale->receipt_id, 15) . " | Omset: " . str_pad(number_format($omset, 0, ',', '.'), 10) . " | Cat: " . $sale->category . " | Status: " . $sale->status . "\n";
}

// 2. Out TT
$ttSales = StockOut::where('reporting_date', $date)
    ->where('branch_id', $branchId)
    ->where('status', '!=', 'cancelled')
    ->where('category', 'tukar_tambah')
    ->get();

$outTT = 0;
foreach($ttSales as $sale) {
    $tt = TukarTambah::where('receipt_id', $sale->receipt_id)->first();
    $pOut = $tt ? (float) $tt->outgoing_price : (float) $sale->selling_price;
    $outTT += $pOut;
    echo "TT SALE    : " . str_pad($sale->receipt_id, 15) . " | Out TT: " . number_format($pOut, 0, ',', '.') . "\n";
}

// 3. Out DG
$dgSales = StockOut::where('reporting_date', $date)
    ->where('branch_id', $branchId)
    ->where('status', '!=', 'cancelled')
    ->where('category', 'downgrade')
    ->get();

$outDG = 0;
foreach($dgSales as $sale) {
    $dg = Downgrade::where('receipt_id', $sale->receipt_id)->first();
    $pOut = $dg ? (float) $dg->outgoing_price : (float) $sale->selling_price;
    $outDG += $pOut;
    echo "DG SALE    : " . str_pad($sale->receipt_id, 15) . " | Out DG: " . number_format($pOut, 0, ',', '.') . "\n";
}

$totalOmset = $penjualanStore + $outTT + $outDG;

echo "\n==================================\n";
echo "Penjualan Store dkk : Rp " . number_format($penjualanStore, 0, ',', '.') . "\n";
echo "Out TT              : Rp " . number_format($outTT, 0, ',', '.') . "\n";
echo "Out DG              : Rp " . number_format($outDG, 0, ',', '.') . "\n";
echo "----------------------------------\n";
echo "TOTAL OMSET         : Rp " . number_format($totalOmset, 0, ',', '.') . "\n";
echo "==================================\n";

