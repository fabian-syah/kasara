<?php

$startDate = '2026-07-16';
$endDate = '2026-07-16';

$startTS = $startDate . ' 05:00:00';
$endTS = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59';

// Menarik semua kategori yang relevan
$salesCategoriesExtended = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'angkat_barang', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pos', 'sale', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'refund'];

$rawTransactions = DB::table('stock_outs')
    ->whereIn('category', $salesCategoriesExtended)
    ->whereNull('deleted_at')
    ->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
        $q->whereBetween('reporting_date', [$startDate, $endDate])
          ->orWhereBetween('created_at', [$startTS, $endTS]);
    })
    ->get(['id', 'receipt_id', 'category', 'selling_price', 'split_payments', 'notes', 'sales_account', 'reporting_date', 'created_at']);

$ttData = DB::table('tukar_tambahs')
    ->whereIn('receipt_id', $rawTransactions->pluck('receipt_id')->unique())
    ->select('receipt_id', 'outgoing_price', 'incoming_cost_price')
    ->get()
    ->groupBy('receipt_id');

$dgData = DB::table('downgrades')
    ->whereIn('receipt_id', $rawTransactions->pluck('receipt_id')->unique())
    ->select('receipt_id', 'outgoing_price', 'incoming_cost_price')
    ->get()
    ->groupBy('receipt_id');

$totalOmset = 0;
$totalDeductions = 0;
$totalTradeIncoming = 0;
$totalDowngradeIncoming = 0;
$omsetBersih = 0;

$rincian = [];

foreach ($rawTransactions as $tx) {
    $cat = strtolower(str_replace(' ', '_', $tx->category));
    $notes = strtolower($tx->notes ?? '');
    $sa = strtolower($tx->sales_account ?? '');

    $saleType = 'ignored';
    if ($cat === 'tukar_tambah' || str_contains($notes, 'tukar tambah') || str_contains($notes, 'tukar_tambah') || str_contains($sa, 'tukar tambah') || str_contains($sa, 'tukar_tambah')) {
        $saleType = 'tukar_tambah';
    } elseif (in_array($cat, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship'])) {
        $saleType = 'base_sale';
    } elseif (str_contains($notes, 'barang angkat') || str_contains($notes, 'angkat barang') || str_contains($notes, 'angkat_barang') || str_contains($sa, 'barang angkat') || str_contains($sa, 'angkat barang') || str_contains($sa, 'angkat_barang') || $cat === 'angkat_barang') {
        $saleType = 'angkat_barang';
    } elseif (str_contains($notes, 'refund') || str_contains($sa, 'refund') || $cat === 'refund') {
        $saleType = 'refund';
    } elseif (str_contains($notes, 'downgrade') || str_contains($sa, 'downgrade') || $cat === 'downgrade') {
        $saleType = 'downgrade';
    }

    $price = max(0, abs((float) $tx->selling_price));
    $spTotal = 0;
    if ($tx->split_payments) {
        $sData = is_string($tx->split_payments) ? json_decode($tx->split_payments, true) : $tx->split_payments;
        if (is_array($sData)) {
            foreach ($sData as $sp) {
                $spTotal += abs((float) ($sp['amount'] ?? 0));
            }
        }
    }
    $effectivePrice = ($price == 0 && $spTotal > 0) ? $spTotal : $price;

    $omsetPerTx = 0;
    $deductionPerTx = 0;
    $inTTPerTx = 0;
    $inDGPerTx = 0;

    if ($saleType === 'base_sale') {
        $omsetPerTx = $effectivePrice;
        $totalOmset += $effectivePrice;
    } elseif ($saleType === 'tukar_tambah') {
        $tt = $ttData->get($tx->receipt_id);
        $outPrice = $tt ? $tt->sum('outgoing_price') : 0;
        if ($outPrice <= 0) $outPrice = $effectivePrice;
        $inPrice = $tt ? $tt->sum('incoming_cost_price') : 0;

        $omsetPerTx = $outPrice;
        $inTTPerTx = $inPrice;

        $totalOmset += $outPrice;
        $totalTradeIncoming += $inPrice;
    } elseif ($saleType === 'downgrade') {
        $dg = $dgData->get($tx->receipt_id);
        $outDg = $dg ? $dg->sum('outgoing_price') : 0;
        $inDg = $dg ? $dg->sum('incoming_cost_price') : 0;
        
        if ($outDg > 0 || $inDg > 0) {
            $omsetPerTx = $outDg;
            $inDGPerTx = $inDg;
            
            $totalOmset += $outDg;
            $totalDowngradeIncoming += $inDg;
        } else {
            $deductionPerTx = $effectivePrice;
            $totalDeductions += $effectivePrice;
        }
    } elseif ($saleType === 'refund' || $saleType === 'angkat_barang') {
        $deductionPerTx = $effectivePrice;
        $totalDeductions += $effectivePrice;
    }

    $rincian[] = [
        'id' => $tx->id,
        'cat' => $cat,
        'sale_type' => $saleType,
        'selling_price_db' => $price,
        'split_total' => $spTotal,
        'omset_added' => $omsetPerTx,
        'deduction' => $deductionPerTx,
        'in_tt' => $inTTPerTx,
        'in_dg' => $inDGPerTx
    ];
}

$omsetBersih = $totalOmset - $totalDeductions - $totalTradeIncoming - $totalDowngradeIncoming;

echo "--- HASIL PERHITUNGAN TINKER ---\n";
echo "Total Omset Kotor: Rp " . number_format($totalOmset, 0, ',', '.') . "\n";
echo "Omset Bersih: Rp " . number_format($omsetBersih, 0, ',', '.') . "\n";
echo "Total Potongan (Refund/Angkat): Rp " . number_format($totalDeductions, 0, ',', '.') . "\n";
echo "Total Masuk TT: Rp " . number_format($totalTradeIncoming, 0, ',', '.') . "\n";
echo "Total Masuk DG: Rp " . number_format($totalDowngradeIncoming, 0, ',', '.') . "\n";
echo "\n--- RINCIAN TRANSAKSI YANG MENAMBAH OMSET ---\n";

foreach ($rincian as $r) {
    if ($r['omset_added'] > 0 || $r['deduction'] > 0) {
        echo "ID: {$r['id']} | Type: {$r['sale_type']} | SP_DB: {$r['selling_price_db']} | Split: {$r['split_total']} | Omset +: {$r['omset_added']} | Potong: {$r['deduction']}\n";
    }
}
