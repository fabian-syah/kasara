<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Branch;
use App\Models\StockOut;
use Illuminate\Support\Facades\DB;

// Find Pekanbaru Branch ID
$branch = Branch::where('name', 'like', '%pekanbaru%')
    ->orWhere('name', 'like', '%PEKANBARU%')
    ->orWhere('name', 'like', '%Pekanbaru%')
    ->first();

if (!$branch) {
    echo "Pekanbaru branch not found!\n";
    exit;
}
$branchId = $branch->id;
echo "Pekanbaru Branch ID: {$branchId}, Name: {$branch->name}\n";

$startDate = '2026-05-07';
$endDate = '2026-05-07';

$startTS = $startDate . ' 05:00:00';
$endTS = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59';

$applyLocalScope = function ($query) use ($startDate, $endDate, $startTS, $endTS, $branchId) {
    $query->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
        $q->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
          ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
    });
    $query->whereNull('stock_outs.deleted_at');
    $query->where('stock_outs.branch_id', $branchId);
};

$successCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'sale', 'pos', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'bundling'];
$activityCategories = ['refund', 'angkat_barang'];
$salesCategories = array_merge($successCategories, $activityCategories);

$rawStatsQuery = DB::table('stock_outs');
$applyLocalScope($rawStatsQuery);

$rawStats = $rawStatsQuery->whereIn('stock_outs.category', $salesCategories)
    ->select('id', 'receipt_id', 'payment_method_id', 'category', 'selling_price', 'split_payments', 'notes', 'sales_account')
    ->get();

$resolveActualCategory = function ($category, $notes, $salesAccount) {
    $category = strtolower($category ?? '');
    $notes = strtolower($notes ?? '');
    $salesAccount = strtolower($salesAccount ?? '');

    if (in_array($category, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'tukar_tambah'])) {
        if (str_contains($notes, 'tukar unit') || str_contains($notes, 'tukar_unit') || str_contains($salesAccount, 'tukar unit') || str_contains($salesAccount, 'tukar_unit')) {
            return 'tukar_unit';
        }
        if (str_contains($notes, 'tukar tambah') || str_contains($notes, 'tukar_tambah') || str_contains($salesAccount, 'tukar tambah') || str_contains($salesAccount, 'tukar_tambah')) {
            return 'tukar_tambah';
        }
        return $category;
    }

    if (str_contains($notes, 'tukar unit') || str_contains($notes, 'tukar_unit') || str_contains($salesAccount, 'tukar unit') || str_contains($salesAccount, 'tukar_unit')) {
        return 'tukar_unit';
    }
    if (str_contains($notes, 'barang angkat') || str_contains($notes, 'angkat barang') || str_contains($notes, 'angkat_barang') || str_contains($salesAccount, 'barang angkat') || str_contains($salesAccount, 'angkat barang') || str_contains($salesAccount, 'angkat_barang')) {
        return 'angkat_barang';
    }
    if (str_contains($notes, 'refund') || str_contains($salesAccount, 'refund')) {
        return 'refund';
    }
    if (str_contains($notes, 'downgrade') || str_contains($salesAccount, 'downgrade')) {
        return 'downgrade';
    }
    if (str_contains($notes, 'tukar tambah') || str_contains($notes, 'tukar_tambah') || str_contains($salesAccount, 'tukar tambah') || str_contains($salesAccount, 'tukar_tambah')) {
        return 'tukar_tambah';
    }

    return $category;
};

$baseSalesOnly = 0;
$tradeSelisih = 0;
$deductions = 0;

echo "Total records found: " . $rawStats->count() . "\n";
echo "--- TRANSACTION LIST ---\n";
foreach ($rawStats as $ps) {
    $cat = $resolveActualCategory($ps->category, $ps->notes, $ps->sales_account);
    $price = abs((float) $ps->selling_price);

    $isBaseSale = in_array($cat, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'bundling']);
    $isTradeIn = ($cat === 'tukar_tambah');
    $isDeduction = in_array($cat, ['refund', 'angkat_barang', 'downgrade']);

    if ($cat === 'tukar_unit') {
        $price = 0;
    }

    $amt = $price;
    if ($ps->split_payments) {
        $sData = is_string($ps->split_payments) ? json_decode($ps->split_payments, true) : $ps->split_payments;
        if (is_array($sData)) {
            $amt = 0;
            foreach ($sData as $sp) {
                $amt += abs((float) ($sp['amount'] ?? 0));
            }
        }
    }

    if ($isBaseSale) {
        $baseSalesOnly += $amt;
    } elseif ($isTradeIn) {
        $tradeSelisih += $amt;
    } elseif ($isDeduction) {
        $deductions += $amt;
    }

    echo "ID: {$ps->id}, Receipt: {$ps->receipt_id}, OrigCat: {$ps->category}, ResolvedCat: {$cat}, Price: {$price}, SplitAmt: {$amt}, Notes: {$ps->notes}\n";
}

echo "\n--- TOTALS ---\n";
echo "Base Sales Only: " . number_format($baseSalesOnly) . "\n";
echo "Trade Selisih: " . number_format($tradeSelisih) . "\n";
echo "Deductions: " . number_format($deductions) . "\n";
echo "Total Omset (Base + Trade): " . number_format($baseSalesOnly + $tradeSelisih) . "\n";
echo "Omset Bersih (Base - Deductions): " . number_format($baseSalesOnly - $deductions) . "\n";
