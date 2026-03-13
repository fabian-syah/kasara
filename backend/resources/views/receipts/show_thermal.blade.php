<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.2;
            color: #000;
            margin: 0;
            padding: 20px;
            width: 480px;
            background: #fff;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }

        .mb-10 {
            margin-bottom: 10px;
        }

        .mb-20 {
            margin-bottom: 20px;
        }

        .border-t {
            border-top: 1px dashed #000;
        }

        .border-b {
            border-bottom: 1px dashed #000;
        }

        .py-5 {
            padding-top: 5px;
            padding-bottom: 5px;
        }

        .header {
            margin-bottom: 15px;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .address {
            font-size: 10px;
        }

        .info-table,
        .item-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 2px 0;
        }

        .item-table th {
            text-align: left;
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
            padding: 5px 0;
        }

        .item-table td {
            padding: 5px 0;
            vertical-align: top;
        }

        .payment-section {
            margin-top: 15px;
            width: 250px;
            float: right;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
        }

        .total-row {
            border-top: 2px solid #000;
            margin-top: 5px;
            padding-top: 5px;
            font-size: 14px;
            font-weight: bold;
        }

        .garansi {
            margin-top: 100px;
            font-size: 9px;
            clear: both;
        }

        .signature-area {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }

        .sig-box {
            width: 120px;
            text-align: center;
        }

        .sig-space {
            height: 50px;
        }
    </style>
</head>

<body>
    <div class="header text-center">
        <div class="logo">PSTORE</div>
        <div class="address">
            Pusat Perbelanjaan Online<br>
            HP, Laptop, Barang Elektronik Bergaransi Terjamin Dan Terpercaya<br>
            No Customer Service 0851 - 3300 - 5600
        </div>
    </div>

    <table class="info-table mb-10">
        <tr>
            <td width="80" class="font-bold">No. Nota</td>
            <td>: {{ $transaction->receipt_id }}</td>
        </tr>
        <tr>
            <td class="font-bold">Atas Nama</td>
            <td class="font-bold">: {{ $transaction->customer_name ?? 'Umum' }}</td>
        </tr>
        <tr>
            <td class="font-bold">Tanggal</td>
            <td>: {{ $transaction->created_at->format('d M Y H:i') }}</td>
        </tr>
        @if($transaction->customer_phone)
            <tr>
                <td class="font-bold">No. HP</td>
                <td>: {{ $transaction->customer_phone }}</td>
            </tr>
        @endif
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th width="40">Qty</th>
                <th>Keterangan</th>
                <th class="text-right" width="100">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $item)
                <tr>
                    <td class="text-center">1</td>
                    <td>
                        <div class="font-bold">{{ $item->product_name ?? 'Produk' }}</div>
                        <div style="font-size: 9px;">IMEI: {{ $item->pivot->imei ?? '-' }}</div>
                    </td>
                    <td class="text-right">{{ number_format($item->pivot->selling_price, 0, ',', '.') }}</td>
                </tr>
            @endforeach

            @if($transaction->non_hp_items)
                @foreach($transaction->non_hp_items as $item)
                    <tr>
                        <td class="text-center">{{ $item['qty'] ?? 1 }}</td>
                        <td>
                            <div class="font-bold">{{ $item['name'] ?? 'Item' }}</div>
                        </td>
                        <td class="text-right">{{ number_format(($item['qty'] ?? 1) * ($item['price'] ?? 0), 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <div class="payment-section">
        <div class="payment-row">
            <span>SUB TOTAL :</span>
            <span>{{ number_format($total_original, 0, ',', '.') }}</span>
        </div>

        @if($transaction->global_discount_value > 0)
            <div class="payment-row font-bold">
                <span>DISKON :</span>
                <span>-{{ number_format($total_discount, 0, ',', '.') }}</span>
            </div>
        @endif

        <div class="payment-row total-row">
            <span>TOTAL :</span>
            <span>{{ number_format($transaction->selling_price, 0, ',', '.') }}</span>
        </div>

        <div class="text-right" style="font-size: 9px; margin-top: 5px; font-style: italic;">
            Metode: {{ $transaction->payment_method_id ? 'Transfer/Debit' : 'Cash' }}
        </div>
    </div>

    <div class="garansi">
        <ul style="padding-left: 15px;">
            <li class="font-bold">Garansi 1 Bulan (Nota Dan Segel Jangan Hilang)</li>
            <li>Barang yang Sudah Dibeli Tidak Dapat Dikembalikan/Ditukarkan</li>
            <li>Tidak ada garansi IMEI afr, jatuh, gagal upgrade dan LCD</li>
        </ul>
    </div>

    <div style="margin-top: 20px;">
        <div style="display: inline-block; width: 45%; text-align: center;">
            <p class="font-bold">Penerima,</p>
            <div
                style="margin-top: 50px; border-bottom: 1px solid #000; width: 120px; margin-left: auto; margin-right: auto;">
            </div>
        </div>
        <div style="display: inline-block; width: 45%; text-align: center; float: right;">
            <p class="font-bold">Hormat Kami,</p>
            <div style="margin-top: 50px; font-weight: bold;">PSTORE</div>
            <div style="border-bottom: 1px solid #000; width: 120px; margin-left: auto; margin-right: auto;"></div>
        </div>
    </div>
</body>

</html>