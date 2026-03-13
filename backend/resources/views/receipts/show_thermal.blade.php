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
            font-size: 16px;
            /* Larger font */
            line-height: 1.6;
            color: #000;
            margin: 0 auto;
            padding: 40px;
            width: 100%;
            max-width: 750px;
            /* Much wider to fill A4 better if needed */
            background: #fff;
        }

        .nota-header {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            padding-bottom: 16px;
        }

        .logo-container {
            display: table-cell;
            width: 80px;
            vertical-align: top;
        }

        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .header-info {
            display: table-cell;
            vertical-align: top;
            padding-left: 16px;
        }

        .brand-title {
            font-size: 32px;
            font-weight: 900;
            letter-spacing: 0.05em;
            margin: 0;
            line-height: 1.1;
        }

        .header-sub {
            font-size: 11px;
            color: #374151;
            line-height: 1.3;
            margin-top: 6px;
        }

        .header-cs {
            font-size: 11px;
            color: #4b5563;
            margin-top: 4px;
            font-weight: 600;
        }

        .social-icons {
            margin-top: 10px;
            font-size: 11px;
            color: #111;
        }

        .social-item {
            display: inline-block;
            margin-right: 18px;
            font-weight: 700;
        }

        .social-icon {
            width: 14px;
            height: 14px;
            vertical-align: middle;
            margin-right: 6px;
        }

        .info-nota {
            width: 100%;
            margin-bottom: 16px;
            font-size: 12px;
            border-collapse: collapse;
        }

        .info-nota td {
            padding: 2px 0;
            vertical-align: top;
        }

        .label {
            font-weight: 600;
            width: 80px;
        }

        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .item-table th {
            font-weight: 700;
            text-align: left;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 8px 4px;
            font-size: 12px;
        }

        .item-table td {
            padding: 8px 4px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .item-name {
            font-weight: 700;
            text-transform: uppercase;
        }

        .item-meta {
            font-size: 9px;
            color: #374151;
        }

        .item-imei {
            font-size: 9px;
            color: #2563eb;
            font-family: monospace;
            margin-top: 2px;
        }

        .payment-section {
            width: 100%;
            margin-bottom: 16px;
        }

        .payment-container {
            width: 240px;
            float: right;
        }

        .payment-row {
            display: table;
            width: 100%;
            padding: 2px 0;
            border-bottom: 1px solid #d1d5db;
        }

        .payment-label {
            display: table-cell;
            text-align: left;
            font-weight: 700;
        }

        .payment-value {
            display: table-cell;
            text-align: right;
        }

        .total-row {
            border-top: 2px solid #000;
            padding-top: 4px;
            border-bottom: none;
        }

        .total-label {
            font-size: 14px;
            font-weight: 800;
        }

        .total-value {
            font-size: 14px;
            font-weight: 800;
        }

        .metode-info {
            text-align: right;
            font-size: 9px;
            color: #6b7280;
            font-style: italic;
            margin-top: 4px;
            clear: both;
        }

        .garansi-box {
            background-color: #f9fafb;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 20px;
            clear: both;
        }

        .garansi-title {
            font-weight: 700;
            font-size: 9px;
            margin-bottom: 2px;
        }

        .garansi-list {
            margin: 0;
            padding-left: 12px;
            font-size: 9px;
            color: #374151;
        }

        .signature-area {
            width: 100%;
            margin-top: 24px;
            margin-bottom: 8px;
            display: table;
        }

        .sig-box {
            display: table-cell;
            width: 50%;
            text-align: center;
        }

        .sig-penerima {
            text-align: left;
        }

        .sig-title {
            font-weight: 600;
            margin-bottom: 48px;
        }

        .sig-line {
            border-bottom: 1px solid #9ca3af;
            width: 112px;
            margin: 0 auto;
        }

        .sig-penerima .sig-line {
            margin-left: 0;
        }

        .sig-hormat .sig-line {
            margin-right: 0;
        }

        .pstore-name {
            font-size: 10px;
            font-weight: 700;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>

<body>
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
                Pusat Perbelanjaan Online<br>
                HP, Laptop, Barang Elektronik Bergaransi Terjamin Dan Terpercaya
            </p>
            <p class="header-cs">
                No Customer Service 0851 - 3300 - 5600
            </p>
            <div class="social-icons">
                <span style="font-weight: bold;">Kami ada juga di :</span><br>
                <div style="margin-top: 4px;">
                    <span class="social-item">
                        @if($shopeeBase64)
                            <img src="{{ $shopeeBase64 }}" class="social-icon" alt="">
                        @else
                            <img src="https://stokps.com/images/shopee-icon-small.png" class="social-icon" alt="">
                        @endif
                        pstore_
                    </span>
                    <span class="social-item">
                        @if($tokopediaBase64)
                            <img src="{{ $tokopediaBase64 }}" class="social-icon" alt="">
                        @else
                            <img src="https://stokps.com/images/tokopedia-icon-small.png" class="social-icon" alt="">
                        @endif
                        pstore_
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- INFO NOTA -->
    <table class="info-nota">
        <tr>
            <td class="label">No. Nota</td>
            <td>: {{ $transaction->receipt_id }}</td>
        </tr>
        <tr>
            <td class="label">Atas Nama</td>
            <td style="font-weight: 700;">: {{ $transaction->customer_name ?? 'Umum' }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal</td>
            <td>: {{ $transaction->created_at->format('d M Y H:i:s') }}</td>
        </tr>
        @if($transaction->customer_phone && $transaction->customer_phone !== '-')
            <tr>
                <td class="label">No. HP</td>
                <td>: {{ $transaction->customer_phone }}</td>
            </tr>
        @endif
    </table>

    <!-- TABLE ITEMS -->
    <table class="item-table">
        <thead>
            <tr>
                <th style="width: 50px; text-align: center;">Banyak</th>
                <th style="width: 100px;">IMEI</th>
                <th>Keterangan</th>
                <th style="width: 100px; text-align: right;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $item)
                <tr>
                    <td style="text-align: center; font-weight: 700;">1</td>
                    <td class="item-imei">{{ $item->pivot->imei ?? '-' }}</td>
                    <td>
                        <div class="item-name">{{ $item->product->name ?? ($item->product_name ?? 'Produk') }}</div>
                        @if($item->pivot->storage || $item->storage)
                            <div class="item-meta">{{ $item->pivot->storage ?? $item->storage }}</div>
                        @endif
                        @if($item->condition)
                            <div class="item-meta" style="font-style: italic;">
                                Condition:
                                {{ $item->condition === 'new' ? 'Baru' : ($item->condition === 'ex_ibox' ? 'Ex iBox' : 'Second') }}
                            </div>
                        @endif
                    </td>
                    <td style="text-align: right; font-weight: 700;">
                        {{ number_format(($item->pivot->selling_price ?? 0) - ($item->pivot->item_discount ?? 0) - ($item->pivot->distributed_discount ?? 0), 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach

            @foreach($transaction->nonHpItems as $item)
                <tr>
                    <td style="text-align: center; font-weight: 700;">{{ $item->quantity }}</td>
                    <td class="item-imei">-</td>
                    <td>
                        <div class="item-name">{{ $item->product->name ?? $item->name }}</div>
                    </td>
                    <td style="text-align: right; font-weight: 700;">
                        {{ number_format($item->quantity * (($item->selling_price ?? 0) - ($item->item_discount ?? 0)), 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach

            {{-- Empty rows for physical feel --}}
            @php
                $itemCount = count($transaction->items) + count($transaction->nonHpItems);
            @endphp
            @for ($i = 0; $i < max(0, 3 - $itemCount); $i++)
                <tr>
                    <td style="padding: 12px;">&nbsp;</td>
                    <td></td>
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
                <span class="payment-value">{{ number_format($total_original, 0, ',', '.') }}</span>
            </div>

            @if($transaction->global_discount_value > 0)
                <div class="payment-row" style="font-weight: 700; font-style: italic;">
                    <span class="payment-label">DISKON :</span>
                    <span class="payment-value">-{{ number_format($total_discount, 0, ',', '.') }}</span>
                </div>
            @endif

            {{-- Split Payments Breakdown --}}
            @if(isset($split_payments_data) && count($split_payments_data) > 0)
                @foreach($split_payments_data as $payment)
                    <div class="payment-row" style="font-size: 11px;">
                        <span class="payment-label" style="text-transform: uppercase;">{{ $payment['method_name'] }}
                            :</span>
                        <span class="payment-value">{{ number_format($payment['amount'], 0, ',', '.') }}</span>
                    </div>
                @endforeach
            @elseif($transaction->split_payments && count($transaction->split_payments) > 0)
                @foreach($transaction->split_payments as $payment)
                    @if(($payment['amount'] ?? 0) > 0)
                        <div class="payment-row" style="font-size: 11px;">
                            <span class="payment-label"
                                style="text-transform: uppercase;">{{ ($payment['method'] ?? ($payment['method_name'] ?? 'Pembayaran')) }}
                                :</span>
                            <span class="payment-value">{{ number_format($payment['amount'], 0, ',', '.') }}</span>
                        </div>
                    @endif
                @endforeach
            @elseif($transaction->paid_amount > 0)
                <div class="payment-row" style="font-size: 11px;">
                    <span class="payment-label">BAYAR :</span>
                    <span class="payment-value">{{ number_format($transaction->paid_amount, 0, ',', '.') }}</span>
                </div>
            @endif

            <div class="payment-row total-row">
                <span class="payment-label total-label">TOTAL :</span>
                <span
                    class="payment-value total-value">{{ number_format($transaction->selling_price, 0, ',', '.') }}</span>
            </div>

            <div class="metode-info">
                Metode:
                {{ $transaction->paymentMethod->name ?? ($transaction->payment_method_id ? 'Transfer/Debit' : 'Cash') }}
            </div>
        </div>
    </div>

    <!-- GARANSI NOTES -->
    <div class="garansi-box">
        <ul class="garansi-list">
            <li style="font-weight: 700;">Garansi 1 Bulan (Nota Dan Segel Jangan Hilang)</li>
            <li>Barang yang Sudah Dibeli Tidak Dapat Dikembalikan/Ditukarkan</li>
            <li>Tidak ada garansi IMEI afr, jatuh, gagal upgrade dan LCD</li>
        </ul>
    </div>

    <!-- SIGNATURE AREA -->
    <div class="signature-area">
        <div class="sig-box sig-penerima">
            <p class="sig-title">Penerima,</p>
            <div class="sig-line"></div>
        </div>
        <div class="sig-box sig-hormat" style="float: right;">
            <p class="sig-title" style="margin-bottom: 40px !important;">Hormat Kami,</p>
            <div class="pstore-name">PSTORE</div>
            <div class="sig-line"></div>
        </div>
    </div>
</body>

</html>