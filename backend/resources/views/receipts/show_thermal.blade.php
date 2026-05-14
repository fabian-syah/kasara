<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Nota Penjualan {{ $transaction->receipt_id }}</title>
    <style>
        @page {
            margin: 0;
            size: auto;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #0a0a0a;
            margin: 0 auto;
            padding: 30px;
            width: 100%;
            max-width: 650px;
            background: #ffffff;
            position: relative;
        }

        /* DYNAMIC CORNER ACCENTS */
        .corner-accent-left {
            position: absolute;
            top: 0;
            left: 0;
            width: 80px;
            height: 80px;
            z-index: 10;
            overflow: hidden;
        }

        .corner-accent-right {
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            z-index: 10;
            overflow: hidden;
        }

        .watermark {
            position: absolute;
            top: 42%;
            left: 50%;
            transform: translate(-50%, -50%) scale(1.2);
            width: 320px;
            opacity: 0.07;
            z-index: -1;
            pointer-events: none;
        }

        /* NOTA HEADER */
        .nota-header {
            display: table;
            width: 100%;
            margin-top: 10px;
            margin-bottom: 12px;
            padding-left: 16px;
            padding-right: 16px;
        }

        .logo-container {
            display: table-cell;
            width: 70px;
            vertical-align: middle;
        }

        .logo-img {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }

        .header-info {
            display: table-cell;
            vertical-align: middle;
            padding-left: 16px;
        }

        .brand-title-red {
            font-size: 22px;
            font-weight: 900;
            color: #dc2626;
            display: inline;
            text-transform: uppercase;
        }

        .brand-title-black {
            font-size: 22px;
            font-weight: 900;
            color: #0a0a0a;
            display: inline;
            text-transform: uppercase;
        }

        .header-sub {
            font-size: 10px;
            color: #4b5563;
            font-weight: 700;
            margin: 3px 0 6px 0;
            line-height: 1.3;
        }

        .social-item {
            display: inline-block;
            font-size: 9px;
            font-weight: 900;
            color: #1f2937;
            white-space: nowrap;
            margin-right: 8px;
        }

        .social-icon {
            width: 10px;
            height: 10px;
            vertical-align: middle;
            margin-right: 3px;
        }

        .social-divider {
            color: #d1d5db;
            margin-right: 8px;
        }

        /* THICK SEPARATOR LINE */
        .thick-separator {
            position: relative;
            width: 100%;
            height: 1px;
            background-color: #e5e7eb;
            margin: 12px 0 16px 0;
            text-align: center;
        }

        .thick-separator-segment {
            position: absolute;
            top: -1px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background-color: #dc2626;
            border-radius: 4px;
        }

        /* TITLE BLOCK */
        .receipt-title-block {
            text-align: center;
            margin-bottom: 20px;
        }

        .receipt-title-main {
            font-size: 24px;
            font-weight: 900;
            color: #0a0a0a;
            text-transform: uppercase;
            margin: 0;
            letter-spacing: 0.02em;
        }

        .receipt-title-sub {
            font-size: 9px;
            font-weight: 900;
            color: #dc2626;
            letter-spacing: 0.15em;
            margin-top: 2px;
        }

        /* INFO METADATA GRID (3 columns using table) */
        .info-table {
            width: 100%;
            margin-bottom: 20px;
            background: #ffffff;
            border: 1px solid #f3f4f6;
            border-radius: 12px;
            border-collapse: collapse;
        }

        .info-cell {
            width: 33.33%;
            padding: 12px 8px;
            vertical-align: middle;
        }

        .info-icon-container {
            float: left;
            width: 28px;
            height: 28px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 50%;
            text-align: center;
            margin-right: 8px;
        }

        .info-icon {
            width: 14px;
            height: 14px;
            margin-top: 7px;
        }

        .info-text-container {
            display: inline-block;
            vertical-align: top;
        }

        .info-label {
            font-size: 7px;
            font-weight: 900;
            color: #0a0a0a;
            text-transform: uppercase;
            margin-bottom: 1px;
        }

        .info-value {
            font-size: 10px;
            font-weight: 900;
            color: #0a0a0a;
        }

        /* 6-COLUMN ITEM TABLE */
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .item-table th {
            font-weight: 900;
            text-align: left;
            background-color: #0a0a0a;
            color: #ffffff;
            padding: 8px 10px;
            font-size: 9px;
            text-transform: uppercase;
        }

        .item-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
            font-size: 10px;
        }

        .item-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .badge-status {
            display: inline-block;
            padding: 3px 6px;
            font-size: 7px;
            font-weight: 900;
            border-radius: 4px;
            text-transform: uppercase;
            text-align: center;
            white-space: nowrap;
        }

        .badge-masuk {
            background-color: #ffffff;
            border: 1px solid #ef4444;
            color: #ef4444;
        }

        .badge-keluar {
            background-color: #ef4444;
            border: 1px solid #ef4444;
            color: #ffffff;
        }

        /* SPLIT LAYOUT CONTAINERS */
        .split-container {
            width: 100%;
            margin-bottom: 24px;
        }

        .split-col-left {
            width: 55%;
            vertical-align: top;
            padding-right: 20px;
        }

        .split-col-right {
            width: 45%;
            vertical-align: top;
        }

        /* CATATAN RIBBON BOX */
        .ribbon-header {
            display: inline-block;
            background-color: #dc2626;
            color: #ffffff;
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            padding: 4px 12px;
            border-top-right-radius: 10px;
            border-bottom-left-radius: 2px;
            position: relative;
            z-index: 2;
        }

        .ribbon-body {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px;
            margin-top: -8px;
            min-height: 48px;
            font-size: 9px;
            font-weight: 700;
            color: #374151;
            z-index: 1;
        }

        /* FINANCIAL SUMMARY LIST */
        .summary-table {
            width: 100%;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 12px;
        }

        .summary-row {
            width: 100%;
            margin-bottom: 4px;
        }

        .summary-label {
            float: left;
            font-size: 9px;
            font-weight: 700;
            color: #6b7280;
        }

        .summary-value {
            float: right;
            font-size: 9px;
            font-weight: 900;
            color: #111827;
        }

        .summary-row-red {
            width: 100%;
            padding-top: 6px;
            margin-top: 6px;
            border-top: 1px dashed #d1d5db;
        }

        .summary-label-red {
            float: left;
            font-size: 9px;
            font-weight: 900;
            color: #dc2626;
            text-transform: uppercase;
        }

        .summary-value-red {
            float: right;
            font-size: 11px;
            font-weight: 900;
            color: #dc2626;
        }

        /* GRAND TOTAL BANNER (SLANTED BULLETPROOF TABLE) */
        .grand-total-wrapper {
            width: 100%;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
        }

        .gt-left {
            background-color: #0a0a0a;
            color: #ffffff;
            padding: 8px 12px;
            width: 40%;
        }

        .gt-left-text-line1 {
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            color: #ffffff;
            line-height: 1.1;
        }

        .gt-left-text-line2 {
            font-size: 6px;
            font-weight: 700;
            color: #9ca3af;
            margin-top: 2px;
        }

        .gt-slant {
            width: 15px;
            background-color: #dc2626;
            padding: 0;
        }

        .gt-right {
            background-color: #dc2626;
            color: #ffffff;
            padding: 8px 12px;
            text-align: right;
            vertical-align: middle;
        }

        .gt-value {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: -0.02em;
        }

        /* WARRANTY & CONDITIONS */
        .warranty-title {
            font-size: 9px;
            font-weight: 900;
            color: #dc2626;
            text-transform: uppercase;
            margin-top: 16px;
            margin-bottom: 4px;
        }

        .warranty-list {
            margin: 0;
            padding-left: 14px;
            font-size: 8px;
            font-weight: 700;
            color: #4b5563;
            line-height: 1.4;
        }

        /* SIGNATURE */
        .sig-table {
            width: 100%;
            margin-top: 24px;
            border: 1px solid #f3f4f6;
            border-radius: 12px;
            padding: 16px;
        }

        .sig-title {
            font-size: 8px;
            font-weight: 900;
            color: #dc2626;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 40px;
        }

        .sig-line {
            width: 130px;
            height: 1px;
            background-color: #d1d5db;
            margin: 0 auto 4px auto;
        }

        .sig-name {
            font-size: 9px;
            font-weight: 900;
            color: #0a0a0a;
            text-align: center;
            text-transform: uppercase;
        }

        /* FOOTER BAR */
        .footer-bar-container {
            position: relative;
            height: 20px;
            background-color: #0a0a0a;
            margin: 24px -30px -30px -30px;
            overflow: hidden;
            text-align: center;
        }

        .footer-slant-left {
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 25%;
            background-color: #dc2626;
        }

        .footer-slant-right {
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            width: 25%;
            background-color: #dc2626;
        }

        .footer-dots {
            position: relative;
            display: inline-block;
            margin-top: 7px;
            z-index: 5;
        }

        .dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin: 0 2px;
        }

        .dot-white {
            background-color: #ffffff;
        }

        .dot-red {
            background-color: #dc2626;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>

<body>
    <!-- CORNER DECORATIONS -->
    <div class="corner-accent-left">
        <svg width="80" height="80" viewBox="0 0 100 100">
            <polygon points="0,0 100,0 0,100" fill="#0a0a0a" />
            <polygon points="0,0 55,0 0,55" fill="#dc2626" />
        </svg>
    </div>
    <div class="corner-accent-right">
        <svg width="80" height="80" viewBox="0 0 100 100">
            <polygon points="100,0 0,0 100,100" fill="#0a0a0a" />
            <polygon points="100,0 45,0 100,55" fill="#dc2626" />
        </svg>
    </div>

    <!-- WATERMARK BACKGROUND -->
    @if($logoBase64)
        <img src="{{ $logoBase64 }}" class="watermark" alt="">
    @else
        <img src="https://stokps.com/images/logo-pstore.png" class="watermark" alt="">
    @endif

    <!-- HEADER -->
    <div class="nota-header">
        <div class="logo-container">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="PSTORE" class="logo-img">
            @else
                <img src="https://stokps.com/images/logo-pstore.png" alt="PSTORE" class="logo-img">
            @endif
        </div>
        <div class="header-info">
            <div>
                <h2 class="brand-title-red">PSTORE</h2>
                <h2 class="brand-title-black">{{ strtoupper(str_replace('PSTORE', '', str_replace('PSTORE ', '', ($transaction->branch->name ?? ($transaction->branch_name ?? 'CABANG'))))) }}</h2>
            </div>
            <p class="header-sub">
                {{ $transaction->branch->address ?? 'Pusat Perbelanjaan Online. HP, Laptop, Barang Elektronik Bergaransi Terjamin.' }}
            </p>
            <div>
                <!-- WhatsApp -->
                <span class="social-item">
                    <svg class="social-icon" style="fill: #dc2626;" viewBox="0 0 24 24"><path d="M20 15.5c-1.25 0-2.45-.2-3.57-.57a1.02 1.02 0 00-1.02.24l-2.2 2.2a15.05 15.05 0 01-6.59-6.59l2.2-2.21a.96.96 0 00.25-1.02A11.36 11.36 0 018.5 4c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1 0 9.39 7.61 17 17 17 .55 0 1-.45 1-1v-3.5c0-.55-.45-1-1-1zM12 3v10l3-3h6V3h-9z"/></svg>
                    WA: 0851-3300-5600
                </span>
                <span class="social-divider">|</span>
                <!-- TikTok -->
                <span class="social-item">
                    <svg class="social-icon" style="fill: #dc2626;" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.59-1 .01 2.24.01 4.48 0 6.72-.09 2.93-1.52 5.82-4.32 7.01-2.86 1.29-6.51.83-8.86-1.38-2.43-2.22-2.99-6.09-1.31-8.93 1.49-2.6 4.72-4 7.69-3.43v4.25c-1.82-.35-3.87.19-4.98 1.69-1.13 1.48-1.09 3.72-.02 5.22 1.15 1.66 3.58 2.27 5.44 1.4 1.71-.73 2.71-2.59 2.76-4.44.06-3.34.03-6.68.03-10.02l.02-.31z"/></svg>
                    TikTok: PSTORE_
                </span>
                <span class="social-divider">|</span>
                <!-- IG -->
                <span class="social-item">
                    <svg class="social-icon" style="fill: #dc2626;" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    IG: PSTORE_
                </span>
            </div>
        </div>
    </div>

    <!-- SEPARATOR LINE -->
    <div class="thick-separator">
        <div class="thick-separator-segment"></div>
    </div>

    <!-- TITLE -->
    <div class="receipt-title-block">
        @php
            $hasExchange = count($transaction->items->filter(fn($i) => str_contains(strtoupper($i->pivot->notes ?? ''), 'IN:') || str_contains(strtoupper($i->pivot->notes ?? ''), 'MASUK'))) > 0;
            $title = $hasExchange ? 'NOTA TUKAR TAMBAH' : 'NOTA PENJUALAN';
        @endphp
        <h1 class="receipt-title-main">{{ $title }}</h1>
        <div class="receipt-title-sub">— BUKTI TRANSAKSI —</div>
    </div>

    <!-- INFO METADATA TABLE (3 Columns) -->
    <table class="info-table">
        <tr>
            <!-- Column 1: No Nota & Tanggal -->
            <td class="info-cell" style="border-right: 1px dashed #d1d5db;">
                <div class="clearfix" style="margin-bottom: 10px;">
                    <div class="info-icon-container">
                        <svg class="info-icon" style="fill: #dc2626;" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                    </div>
                    <div class="info-text-container">
                        <div class="info-label">No. Nota</div>
                        <div class="info-value">#{{ $transaction->receipt_id }}</div>
                    </div>
                </div>
                <div class="clearfix">
                    <div class="info-icon-container">
                        <svg class="info-icon" style="fill: #dc2626;" viewBox="0 0 24 24"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg>
                    </div>
                    <div class="info-text-container">
                        <div class="info-label">Tanggal & Waktu</div>
                        <div class="info-value">{{ $transaction->created_at->format('d M Y, H:i') }}</div>
                    </div>
                </div>
            </td>
            
            <!-- Column 2: Atas Nama & No HP -->
            <td class="info-cell" style="border-right: 1px dashed #d1d5db;">
                <div class="clearfix" style="margin-bottom: 10px;">
                    <div class="info-icon-container">
                        <svg class="info-icon" style="fill: #dc2626;" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
                    <div class="info-text-container">
                        <div class="info-label">Atas Nama</div>
                        <div class="info-value" style="max-width: 120px; overflow: hidden;">{{ $transaction->customer_name ?? 'Umum' }}</div>
                    </div>
                </div>
                <div class="clearfix">
                    <div class="info-icon-container">
                        <svg class="info-icon" style="fill: #dc2626;" viewBox="0 0 24 24"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 00-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.72 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/></svg>
                    </div>
                    <div class="info-text-container">
                        <div class="info-label">No. HP</div>
                        <div class="info-value">{{ $transaction->customer_phone && $transaction->customer_phone !== '-' ? $transaction->customer_phone : '000000000000' }}</div>
                    </div>
                </div>
            </td>
            
            <!-- Column 3: Customer Service -->
            <td class="info-cell">
                <div class="clearfix">
                    <div class="info-icon-container">
                        <svg class="info-icon" style="fill: #dc2626;" viewBox="0 0 24 24"><path d="M12 2c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg>
                    </div>
                    <div class="info-text-container">
                        <div class="info-label">Customer Service</div>
                        <div class="info-value" style="max-width: 120px; overflow: hidden;">
                            {{ $transaction->inventory_user_name ?? ($transaction->sales_name ?? 'PSTORE STAFF') }}
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ITEMS TABLE -->
    <table class="item-table">
        <thead>
            <tr>
                <th style="width: 35px; text-align: center;">Qty</th>
                <th style="width: 110px;">Brand / IMEI</th>
                <th>Deskripsi Barang</th>
                <th style="width: 85px; text-align: center;">Status</th>
                <th style="width: 85px; text-align: right;">Harga Satuan</th>
                <th style="width: 85px; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $item)
                @php
                    $isMasuk = str_contains(strtoupper($item->pivot->notes ?? ''), 'IN:') || str_contains(strtoupper($item->pivot->notes ?? ''), 'MASUK');
                    $pName = $item->product->name ?? ($item->product_name ?? 'Produk');
                    $brand = str_contains(strtolower($pName), 'iphone') || str_contains(strtolower($pName), 'xr') || str_contains(strtolower($pName), 'xs') || str_contains(strtolower($pName), 'ipad') ? 'Apple' : 'PSTORE UNIT';
                @endphp
                <tr>
                    <td style="text-align: center; font-weight: 900; color: #0a0a0a;">1</td>
                    <td>
                        <div style="font-weight: 900; text-transform: uppercase; color: #0a0a0a;">{{ $brand }}</div>
                        <div style="font-size: 8px; font-family: monospace; color: #6b7280; margin-top: 2px;">
                            {{ $item->pivot->imei && $item->pivot->imei !== '-' ? $item->pivot->imei : '-' }}
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 900; color: #374151;">{{ str_replace('IN:', '', str_replace('OUT:', '', $pName)) }}</div>
                        <div style="font-size: 8px; color: #6b7280; margin-top: 2px;">
                            Condition: {{ $item->condition === 'new' ? 'Baru' : ($item->condition === 'ex_ibox' ? 'Ex iBox' : 'Second') }}
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge-status {{ $isMasuk ? 'badge-masuk' : 'badge-keluar' }}">
                            {{ $isMasuk ? 'UNIT MASUK' : 'UNIT KELUAR' }}
                        </span>
                    </td>
                    <td style="text-align: right; font-weight: 700; color: #1f2937;">
                        {{ number_format(abs(($item->pivot->selling_price ?? 0) - ($item->pivot->item_discount ?? 0)), 0, ',', '.') }}
                    </td>
                    <td style="text-align: right; font-weight: 900; color: #0a0a0a;">
                        {{ number_format(abs(($item->pivot->selling_price ?? 0) - ($item->pivot->item_discount ?? 0)), 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach

            @foreach($transaction->nonHpItems as $item)
                <tr>
                    <td style="text-align: center; font-weight: 900; color: #0a0a0a;">{{ $item->quantity }}</td>
                    <td>
                        <div style="font-weight: 900; text-transform: uppercase; color: #0a0a0a;">AKSESORIS</div>
                        <div style="font-size: 8px; color: #6b7280;">-</div>
                    </td>
                    <td>
                        <div style="font-weight: 900; color: #374151;">{{ $item->product->name ?? $item->name }}</div>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge-status badge-keluar">UNIT KELUAR</span>
                    </td>
                    <td style="text-align: right; font-weight: 700; color: #1f2937;">
                        {{ number_format(abs(($item->selling_price ?? 0) - ($item->item_discount ?? 0)), 0, ',', '.') }}
                    </td>
                    <td style="text-align: right; font-weight: 900; color: #0a0a0a;">
                        {{ number_format(abs($item->quantity * (($item->selling_price ?? 0) - ($item->item_discount ?? 0))), 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach

            {{-- Filling standard table --}}
            @php $rowCount = count($transaction->items) + count($transaction->nonHpItems); @endphp
            @for($i = 0; $i < max(0, 2 - $rowCount); $i++)
                <tr style="opacity: 0.1;">
                    <td style="padding: 12px;">&nbsp;</td>
                    <td colspan="5"></td>
                </tr>
            @endfor
        </tbody>
    </table>

    <!-- BOTTOM SPLIT SECTION -->
    <table class="split-container" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <!-- Left: Notes and Warranty -->
            <td class="split-col-left">
                <div style="margin-bottom: 12px;">
                    <div class="ribbon-header">Catatan & Alasan</div>
                    <div class="ribbon-body">
                        {{ $transaction->notes ?? ($transaction->reason ?? 'Tidak ada catatan tambahan.') }}
                    </div>
                </div>

                <div class="warranty-title">Syarat & Ketentuan —</div>
                <ul class="warranty-list">
                    <li>Garansi unit selama 1 bulan terhitung sejak tanggal nota.</li>
                    <li>Garansi yang sudah tidak batas tanggal akan tidak mendapatkan klaim garansi.</li>
                    <li>Segel wajib utuh. Kerusakan akibat human error membatalkan garansi.</li>
                </ul>
            </td>

            <!-- Right: Totals Breakdown & Slanted Banner -->
            <td class="split-col-right">
                <div class="summary-table clearfix">
                    <div class="summary-row clearfix">
                        <span class="summary-label">Sub Total</span>
                        <span class="summary-value">Rp {{ number_format($total_original, 0, ',', '.') }}</span>
                    </div>
                    @if($total_discount > 0)
                        <div class="summary-row clearfix" style="margin-top: 4px;">
                            <span class="summary-label">Diskon</span>
                            <span class="summary-value" style="color: #dc2626;">-Rp {{ number_format($total_discount, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    {{-- Breakdown row support --}}
                    @if($transaction->split_payments && count($transaction->split_payments) > 0)
                        @foreach($transaction->split_payments as $sp)
                            @if(($sp['amount'] ?? 0) > 0)
                                <div class="summary-row clearfix" style="margin-top: 4px; border-top: 1px solid #f3f4f6; padding-top: 2px;">
                                    <span class="summary-label" style="text-transform: uppercase;">{{ $sp['method'] ?? 'Bayar' }}</span>
                                    <span class="summary-value">Rp {{ number_format($sp['amount'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                        @endforeach
                    @endif

                    <div class="summary-row-red clearfix">
                        <span class="summary-label-red">Selisih Harga</span>
                        <span class="summary-value-red">Rp {{ number_format(abs($transaction->selling_price), 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- PIXEL-PERFECT SLANTED BANNER TABLE -->
                <table class="grand-total-wrapper" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td class="gt-left">
                            <div class="gt-left-text-line1">Yang Harus</div>
                            <div class="gt-left-text-line1">Dibayarkan</div>
                            <div class="gt-left-text-line2">(Cash / Debit)</div>
                        </td>
                        <!-- The Slanted Separator SVG for 100% PDF compatibility -->
                        <td class="gt-slant">
                            <svg width="15" height="42" viewBox="0 0 15 42" preserveAspectRatio="none" style="display: block;">
                                <polygon points="0,0 15,0 0,42" fill="#0a0a0a" />
                                <polygon points="15,0 15,42 0,42" fill="#dc2626" />
                            </svg>
                        </td>
                        <td class="gt-right">
                            <span class="gt-value">Rp {{ number_format(abs($transaction->selling_price), 0, ',', '.') }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- SIGNATURE -->
    <table class="sig-table" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td style="width: 50%; vertical-align: top; border-right: 1px solid #f3f4f6;">
                <div class="sig-title">Customer / Pembeli</div>
                <div class="sig-line"></div>
                <div class="sig-name">{{ $transaction->customer_name ?? 'UMUM' }}</div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div class="sig-title">Hormat Kami</div>
                <div class="sig-line"></div>
                <div class="sig-name" style="color: #dc2626;">PSTORE</div>
            </td>
        </tr>
    </table>

    <!-- STRIPED FOOTER BAR -->
    <div class="footer-bar-container">
        <!-- Absolute CSS Slanted Accents -->
        <div class="footer-slant-left">
            <svg width="100%" height="20" viewBox="0 0 100 20" preserveAspectRatio="none">
                <polygon points="0,0 80,0 100,20 0,20" fill="#dc2626" />
                <polygon points="80,0 100,0 100,20 80,20" fill="#0a0a0a" />
            </svg>
        </div>
        <div class="footer-slant-right">
            <svg width="100%" height="20" viewBox="0 0 100 20" preserveAspectRatio="none">
                <polygon points="20,0 100,0 100,20 0,20" fill="#dc2626" />
                <polygon points="0,0 20,0 20,20 0,20" fill="#0a0a0a" />
            </svg>
        </div>
        <!-- Dot accents -->
        <div class="footer-dots">
            <span class="dot dot-white"></span>
            <span class="dot dot-red"></span>
            <span class="dot dot-white"></span>
        </div>
    </div>
</body>

</html>