<?php

$startDate = '2026-07-16';
$endDate = '2026-07-16';
$startTS = $startDate . ' 05:00:00';
$endTS = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 04:59:59';
$branchId = 4; // PStore KELAPA GADING

$salesCategoriesExtended = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'angkat_barang', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pos', 'sale', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'refund'];

$rawTransactions = DB::table('stock_outs')
    ->leftJoin('users', 'stock_outs.user_id', '=', 'users.id')
    ->whereIn('stock_outs.category', $salesCategoriesExtended)
    ->whereNull('stock_outs.deleted_at')
    ->where(function ($q) use ($startDate, $endDate, $startTS, $endTS) {
        $q->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
          ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]);
    })
    ->where(function($q) use ($branchId) {
        $q->where('stock_outs.branch_id', $branchId)
          ->orWhere(function($sq) use ($branchId) {
              $sq->whereNull('stock_outs.branch_id')->where('users.branch_id', $branchId);
          });
    })
    ->select('stock_outs.id', 'stock_outs.receipt_id', 'stock_outs.category', 'stock_outs.selling_price', 'stock_outs.split_payments', 'stock_outs.notes', 'stock_outs.sales_account', 'stock_outs.reporting_date', 'stock_outs.created_at')
    ->get();

$ttData = DB::table('tukar_tambahs')->whereIn('receipt_id', $rawTransactions->pluck('receipt_id')->unique())->select('receipt_id', 'outgoing_price', 'incoming_cost_price')->get()->groupBy('receipt_id');
$dgData = DB::table('downgrades')->whereIn('receipt_id', $rawTransactions->pluck('receipt_id')->unique())->select('receipt_id', 'outgoing_price', 'incoming_cost_price')->get()->groupBy('receipt_id');

$totalOmset = 0; $totalDeductions = 0; $totalTradeIncoming = 0; $totalDowngradeIncoming = 0;
$paymentSum = 0; // Total dari rincian pembayaran (split_payments / price)

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

    if ($saleType === 'base_sale' || $saleType === 'tukar_tambah') {
        $paymentSum += ($spTotal > 0 ? $spTotal : $price);
    }

    $omsetPerTx = 0;
    if ($saleType === 'base_sale') {
        $omsetPerTx = $effectivePrice;
        $totalOmset += $effectivePrice;
    } elseif ($saleType === 'tukar_tambah') {
        $tt = $ttData->get($tx->receipt_id);
        $outPrice = $tt ? $tt->sum('outgoing_price') : 0;
        if ($outPrice <= 0) $outPrice = $effectivePrice;
        $totalOmset += $outPrice;
        $totalTradeIncoming += $tt ? $tt->sum('incoming_cost_price') : 0;
    } elseif ($saleType === 'downgrade') {
        $dg = $dgData->get($tx->receipt_id);
        $outDg = $dg ? $dg->sum('outgoing_price') : 0;
        $inDg = $dg ? $dg->sum('incoming_cost_price') : 0;
        if ($outDg > 0 || $inDg > 0) {
            $totalOmset += $outDg;
            $totalDowngradeIncoming += $inDg;
        } else {
            $totalDeductions += $effectivePrice;
        }
    } elseif ($saleType === 'refund' || $saleType === 'angkat_barang') {
        $totalDeductions += $effectivePrice;
    }
}

$omsetBersih = $totalOmset - $totalDeductions - $totalTradeIncoming - $totalDowngradeIncoming;
echo "--- HASIL AKHIR KELAPA GADING ---\n";
echo "Total Uang Diterima (Payment Sum) = $paymentSum\n";
echo "Total Omset (Berdasarkan Harga)   = $totalOmset\n";
echo "Omset Bersih                      = $omsetBersih\n";
echo "Selisih Payment vs Omset          = " . ($paymentSum - $totalOmset) . "\n";
