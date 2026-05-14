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
            font-size: 14px;
            line-height: 1.6;
            color: #0a0a0a;
            margin: 0 auto;
            padding: 30px;
            width: 100%;
            max-width: 650px;
            background: #ffffff;
            border-top: 8px solid #dc2626;
            position: relative;
        }

        .watermark {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) scale(1.3);
            width: 280px;
            opacity: 0.03;
            z-index: -1;
            pointer-events: none;
        }

        .nota-header {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #fee2e2;
        }

        .logo-container {
            display: table-cell;
            width: 80px;
            vertical-align: middle;
        }

        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 16px;
            background-color: #ffffff;
            padding: 4px;
            border: 1px solid #fecaca;
        }

        .header-info {
            display: table-cell;
            vertical-align: middle;
            padding-left: 20px;
        }

        .brand-title {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: -0.02em;
            color: #dc2626;
            margin: 0;
            line-height: 1.1;
            text-transform: uppercase;
        }

        .header-sub {
            font-size: 11px;
            color: #4b5563;
            line-height: 1.4;
            margin: 4px 0 8px 0;
            max-width: 420px;
            font-weight: 600;
        }

        .social-bar {
            display: inline-block;
            background: #ffffff;
            border: 1px solid #fecaca;
            border-radius: 20px;
            padding: 4px 12px;
        }

        .social-item {
            display: inline-block;
            font-size: 10px;
            font-weight: 800;
            color: #1f2937;
            white-space: nowrap;
        }

        .social-icon {
            width: 11px;
            height: 11px;
            vertical-align: middle;
            margin-right: 4px;
            display: inline-block;
        }

        .social-divider {
            color: #fca5a5;
            font-size: 9px;
            margin: 0 10px;
            display: inline-block;
        }

        .info-container {
            display: table;
            width: 100%;
            margin-bottom: 24px;
            margin-top: 8px;
            border-collapse: separate;
            border-spacing: 12px 0;
        }

        .info-card {
            display: table-cell;
            width: 50%;
            background: #ffffff;
            border: 1px solid #fee2e2;
            border-radius: 12px;
            padding: 12px;
            vertical-align: top;
        }

        .info-card-right {
            text-align: right;
        }

        .info-label {
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 0.1em;
            color: #9ca3af;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 13px;
            font-weight: 900;
            color: #dc2626;
        }

        .info-value-right {
            font-size: 13px;
            font-weight: 900;
            color: #0a0a0a;
        }

        .info-subvalue {
            font-size: 10px;
            color: #4b5563;
            font-weight: 700;
            margin-top: 3px;
        }

        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            border: 1px solid #fee2e2;
            border-radius: 12px;
        }

        .item-table th {
            font-weight: 900;
            text-align: left;
            background-color: #dc2626;
            color: #ffffff;
            padding: 10px 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .item-table th:first-child {
            border-top-left-radius: 8px;
        }

        .item-table th:last-child {
            border-top-right-radius: 8px;
        }

        .item-table td {
            padding: 12px;
            border-bottom: 1px solid #fecaca40;
            vertical-align: top;
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: 800;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-right: 4px;
            margin-bottom: 4px;
        }

        .badge-out {
            background-color: #dc2626;
            color: #ffffff;
        }

        .badge-in {
            background-color: #0a0a0a;
            color: #ffffff;
        }

        .badge-cond {
            background-color: #f3f4f6;
            color: #374151;
            border: 1px solid #e5e7eb;
        }

        .item-name {
            font-weight: 900;
            color: #0a0a0a;
            text-transform: uppercase;
            font-size: 12px;
        }

        .item-imei-pill {
            display: inline-block;
            font-size: 9px;
            color: #dc2626;
            font-family: monospace;
            background-color: #fef2f2;
            padding: 1px 5px;
            border-radius: 4px;
            font-weight: 900;
            margin-top: 4px;
            border: 1px solid #fee2e2;
        }

        .item-meta {
            font-size: 9px;
            color: #4b5563;
            font-weight: 800;
            margin-top: 3px;
        }

        .payment-section {
            width: 100%;
            margin-bottom: 24px;
        }

        .payment-container {
            width: 240px;
            float: right;
        }

        .payment-row {
            display: table;
            width: 100%;
            padding: 6px 0;
            border-bottom: 1px solid #fee2e2;
            font-size: 12px;
        }

        .payment-label {
            display: table-cell;
            text-align: left;
            font-weight: 700;
            color: #4b5563;
        }

        .payment-value {
            display: table-cell;
            text-align: right;
            font-weight: 900;
            color: #0a0a0a;
        }

        .total-box {
            background-color: #0a0a0a;
            border-radius: 10px;
            padding: 10px 12px;
            margin-top: 8px;
            color: #ffffff;
        }

        .total-label {
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .total-value {
            font-size: 15px;
            font-weight: 900;
            float: right;
        }

        .metode-info {
            text-align: right;
            font-size: 9px;
            color: #9ca3af;
            font-style: italic;
            margin-top: 6px;
            font-weight: bold;
            clear: both;
        }

        .garansi-box {
            background-color: #ffffff;
            border: 1px solid #fee2e2;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 24px;
            clear: both;
        }

        .garansi-title {
            font-weight: 900;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #dc2626;
            margin-bottom: 6px;
            border-bottom: 1px solid #fee2e2;
            padding-bottom: 4px;
        }

        .garansi-list {
            margin: 0;
            padding-left: 14px;
            font-size: 9px;
            color: #374151;
            font-weight: 800;
            line-height: 1.5;
        }

        .signature-area {
            width: 100%;
            margin-top: 32px;
            margin-bottom: 8px;
            display: table;
        }

        .sig-box {
            display: table-cell;
            width: 50%;
            text-align: center;
        }

        .sig-title {
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #9ca3af;
            margin-bottom: 48px;
        }

        .sig-line {
            border-bottom: 2px dashed #e5e7eb;
            width: 120px;
            margin: 0 auto 4px auto;
        }

        .sig-name {
            font-size: 11px;
            font-weight: 900;
            color: #374151;
            text-transform: uppercase;
        }

        .sig-name-pstore {
            color: #dc2626;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>

<body>
    <!-- WATERMARK BACKGROUND -->
    @if($logoBase64)
        <img src="{{ $logoBase64 }}" class="watermark" alt="">
    @else
        <img src="https://stokps.com/images/logo-pstore.png" class="watermark" alt="">
    @endif

    <!-- NOTA HEADER -->
    <div class="nota-header">
        <div class="logo-container">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="PSTORE" class="logo-img">
            @else
                <img src="https://stokps.com/images/logo-pstore.png" alt="PSTORE" class="logo-img">
            @endif
        </div>
        <div class="header-info">
            <h2 class="brand-title">PSTORE</h2>
            <p class="header-sub">
                Pusat Perbelanjaan Online. HP, Laptop, Barang Elektronik Bergaransi Terjamin Dan Terpercaya.
            </p>
            <div class="social-bar">
                <!-- WhatsApp -->
                <span class="social-item">
                    <svg class="social-icon" style="fill: #10b981;" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.443-4.421-9.868-9.889-9.868-5.462 0-9.901 4.44-9.904 9.888-.001 2.15.619 4.193 1.694 5.829l-1.002 3.665 3.82-1.021z"/></svg>
                    0851 - 3300 - 5600
                </span>
                <span class="social-divider">|</span>
                <!-- Instagram -->
                <span class="social-item">
                    <svg class="social-icon" style="fill: #db2777;" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    @PSTORE_
                </span>
                <span class="social-divider">|</span>
                <!-- TikTok -->
                <span class="social-item">
                    <svg class="social-icon" style="fill: #0a0a0a;" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.59-1 .01 2.24.01 4.48 0 6.72-.09 2.93-1.52 5.82-4.32 7.01-2.86 1.29-6.51.83-8.86-1.38-2.43-2.22-2.99-6.09-1.31-8.93 1.49-2.6 4.72-4 7.69-3.43v4.25c-1.82-.35-3.87.19-4.98 1.69-1.13 1.48-1.09 3.72-.02 5.22 1.15 1.66 3.58 2.27 5.44 1.4 1.71-.73 2.71-2.59 2.76-4.44.06-3.34.03-6.68.03-10.02l.02-.31z"/></svg>
                    @PSTORE_
                </span>
            </div>
        </div>
    </div>

    <!-- INFO NOTA -->
    <div class="info-container">
        <div style="display: table-row;">
            <div class="info-card">
                <div class="info-label">Pelanggan</div>
                <div class="info-value">{{ $transaction->customer_name ?? 'Umum' }}</div>
                @if($transaction->customer_phone && $transaction->customer_phone !== '-')
                    <div class="info-subvalue">{{ $transaction->customer_phone }}</div>
                @endif
            </div>
            <div class="info-card info-card-right">
                <div class="info-label">No. Nota</div>
                <div class="info-value-right">#{{ $transaction->receipt_id }}</div>
                <div class="info-subvalue">{{ $transaction->created_at->format('d M Y H:i:s') }}</div>
            </div>
        </div>
    </div>

    <!-- TABLE ITEMS -->
    <table class="item-table">
        <thead>
            <tr>
                <th style="width: 50px; text-align: center;">Qty</th>
                <th>Item Detail</th>
                <th style="width: 110px; text-align: right;">Jumlah</th>
            </tr>
        </thead>
        <tbody style="background: transparent;">
            @foreach($transaction->items as $item)
                <tr>
                    <td style="text-align: center; font-weight: 900; color: #0a0a0a;">1</td>
                    <td>
                        <div style="margin-bottom: 2px;">
                            <span class="badge badge-out">KELUAR</span>
                            <span class="badge badge-cond">
                                {{ $item->condition === 'new' ? 'Baru' : ($item->condition === 'ex_ibox' ? 'Ex iBox' : 'Second') }}
                            </span>
                        </div>
                        <div class="item-name">{{ $item->product->name ?? ($item->product_name ?? 'Produk') }}</div>
                        
                        <div style="margin-top: 4px;">
                            @if($item->pivot->imei && $item->pivot->imei !== '-')
                                <span class="item-imei-pill">IMEI: {{ $item->pivot->imei }}</span>
                            @endif
                            @if($item->pivot->storage || $item->storage)
                                <span class="item-meta" style="margin-left: 6px;">
                                    • {{ $item->pivot->storage ?? $item->storage }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td style="text-align: right; font-weight: 900; color: #0a0a0a;">
                        Rp {{ number_format(($item->pivot->selling_price ?? 0) - ($item->pivot->item_discount ?? 0) - ($item->pivot->distributed_discount ?? 0), 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach

            @foreach($transaction->nonHpItems as $item)
                <tr>
                    <td style="text-align: center; font-weight: 900; color: #0a0a0a;">{{ $item->quantity }}</td>
                    <td>
                        <div class="item-name">{{ $item->product->name ?? $item->name }}</div>
                    </td>
                    <td style="text-align: right; font-weight: 900; color: #0a0a0a;">
                        Rp {{ number_format($item->quantity * (($item->selling_price ?? 0) - ($item->item_discount ?? 0)), 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach

            {{-- Empty rows --}}
            @php
                $itemCount = count($transaction->items) + count($transaction->nonHpItems);
            @endphp
            @for ($i = 0; $i < max(0, 3 - $itemCount); $i++)
                <tr style="opacity: 0.15;">
                    <td style="padding: 14px;">&nbsp;</td>
                    <td></td>
                    <td></td>
                </tr>
            @endfor
        </tbody>
    </table>

    <!-- PAYMENT SECTION -->
    <div class="payment-section clearfix">
        <div class="payment-container">
            <div class="payment-row">
                <span class="payment-label">SUB TOTAL :</span>
                <span class="payment-value">Rp {{ number_format($total_original, 0, ',', '.') }}</span>
            </div>

            @if($transaction->global_discount_value > 0)
                <div class="payment-row">
                    <span class="payment-label">DISKON :</span>
                    <span class="payment-value" style="color: #dc2626;">-Rp {{ number_format($total_discount, 0, ',', '.') }}</span>
                </div>
            @endif

            {{-- Split Payments Breakdown --}}
            @if(isset($split_payments_data) && count($split_payments_data) > 0)
                @foreach($split_payments_data as $payment)
                    <div class="payment-row" style="font-size: 11px;">
                        <span class="payment-label" style="text-transform: uppercase;">{{ $payment['method_name'] }} :</span>
                        <span class="payment-value">Rp {{ number_format($payment['amount'], 0, ',', '.') }}</span>
                    </div>
                @endforeach
            @elseif($transaction->split_payments && count($transaction->split_payments) > 0)
                @foreach($transaction->split_payments as $payment)
                    @if(($payment['amount'] ?? 0) > 0)
                        <div class="payment-row" style="font-size: 11px;">
                            <span class="payment-label" style="text-transform: uppercase;">{{ ($payment['method'] ?? ($payment['method_name'] ?? 'Pembayaran')) }} :</span>
                            <span class="payment-value">Rp {{ number_format($payment['amount'], 0, ',', '.') }}</span>
                        </div>
                    @endif
                @endforeach
            @elseif($transaction->paid_amount > 0)
                <div class="payment-row" style="font-size: 11px;">
                    <span class="payment-label">BAYAR :</span>
                    <span class="payment-value">Rp {{ number_format($transaction->paid_amount, 0, ',', '.') }}</span>
                </div>
            @endif

            <!-- Total Box -->
            <div class="total-box">
                <span class="total-label">TOTAL :</span>
                <span class="total-value">Rp {{ number_format($transaction->selling_price, 0, ',', '.') }}</span>
            </div>

            <div class="metode-info">
                Metode: {{ $transaction->paymentMethod->name ?? ($transaction->payment_method_id ? 'Transfer/Debit' : 'Cash') }}
            </div>
        </div>
    </div>

    <!-- GARANSI NOTES -->
    <div class="garansi-box">
        <div class="garansi-title">Ketentuan Garansi</div>
        <ul class="garansi-list">
            <li style="font-weight: 900; color: #dc2626;">Garansi 1 Bulan (Nota Dan Segel Jangan Hilang)</li>
            <li>Barang yang Sudah Dibeli Tidak Dapat Dikembalikan/Ditukarkan</li>
            <li>Tidak ada garansi IMEI afr, jatuh, gagal upgrade dan LCD</li>
        </ul>
    </div>

    <!-- SIGNATURE AREA -->
    <div class="signature-area">
        <div class="sig-box">
            <div class="sig-title">Pembeli</div>
            <div class="sig-line"></div>
            <div class="sig-name">{{ $transaction->customer_name ?? 'Umum' }}</div>
        </div>
        <div class="sig-box">
            <div class="sig-title">Hormat Kami</div>
            <div class="sig-line"></div>
            <div class="sig-name sig-name-pstore">PSTORE</div>
        </div>
    </div>
</body>

</html>