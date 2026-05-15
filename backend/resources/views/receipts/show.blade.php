<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota PSTORE - {{ $transaction->receipt_id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono&display=swap"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }

        .nota-paper {
            background: white;
            max-width: 480px;
            margin: 20px auto;
            padding: 30px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            color: #000;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        @media print {
            body {
                background: white;
                margin: 0;
            }

            .nota-paper {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
                border-radius: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body class="p-4 md:p-8">

    <div class="no-print flex justify-center gap-4 mb-6">
        <button onclick="window.print()"
            class="bg-gray-800 text-white px-6 py-2 rounded-full font-bold shadow-lg hover:bg-black transition-all">
            Cetak Nota
        </button>
        <button onclick="downloadPDF()"
            class="bg-emerald-600 text-white px-6 py-2 rounded-full font-bold shadow-lg hover:bg-emerald-700 transition-all">
            Simpan PDF
        </button>
    </div>

    <div id="receipt-content" class="nota-paper">
        <!-- HEADER -->
        <div class="flex items-start gap-4 mb-6 border-b pb-6">
            <img src="https://api.stokps.com/images/logo-pstore.png" alt="PSTORE" class="w-16 h-auto object-contain shrink-0" />
            <div class="flex-1">
                <h1 class="text-3xl font-black tracking-tighter text-black leading-none">PSTORE</h1>
                <p class="text-[10px] leading-tight text-gray-700 mt-2">
                    Pusat Perbelanjaan Online HP, Laptop, Barang Elektronik<br>
                    Bergaransi Terjamin Dan Terpercaya
                </p>
                <p class="text-[10px] font-bold text-gray-800 mt-1">CS: 0851 - 3300 - 5600</p>
            </div>
        </div>

        <!-- INFO -->
        <div class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs mb-6">
            <span class="text-gray-500">No. Nota</span>
            <span class="font-bold">: {{ $transaction->receipt_id }}</span>
            <span class="text-gray-500">Pelanggan</span>
            <span class="font-bold">: {{ $transaction->customer_name ?? 'Umum' }}</span>
            <span class="text-gray-500">Tanggal</span>
            <span class="font-bold">: {{ $transaction->created_at->format('d/m/Y H:i') }}</span>
        </div>

        <!-- TABLE -->
        <table class="w-full text-xs mb-6">
            <thead>
                <tr class="border-y-2 border-black">
                    <th class="py-2 text-left">Item</th>
                    <th class="py-2 text-center">Qty</th>
                    <th class="py-2 text-right">Harga</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transaction->items as $item)
                    @php
                        $pName = $item->product->name ?? 'Produk';
                        $pName = str_ireplace('paket bundling', 'Paket Promo', $pName);
                    @endphp
                    <tr class="border-b border-gray-100">
                        <td class="py-3">
                            <div class="font-bold uppercase">{{ $pName }}</div>
                            <div class="text-[10px] text-gray-500 mono">{{ $item->imei }}</div>
                        </td>
                        <td class="py-3 text-center">{{ $item->pivot->qty ?? 1 }}</td>
                        <td class="py-3 text-right font-bold">{{ number_format($item->pivot->selling_price, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach

                @if($transaction->nonHpItems)
                    @foreach($transaction->nonHpItems as $item)
                        @php
                            $nonHpName = $item->product->name ?? $item->name;
                            $nonHpName = str_ireplace('paket bundling', 'Paket Promo', $nonHpName);
                        @endphp
                        <tr class="border-b border-gray-100">
                            <td class="py-3">
                                <div class="font-bold uppercase">{{ $nonHpName }}</div>
                            </td>
                            <td class="py-3 text-center">{{ $item->quantity }}</td>
                            <td class="py-3 text-right font-bold">{{ number_format($item->selling_price, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>

        <!-- TOTALS -->
        <div class="flex justify-end mb-8">
            <div class="w-full max-w-[200px] space-y-2">
                <div class="flex justify-between text-xs">
                    <span>Subtotal</span>
                    <span class="font-bold">Rp {{ number_format($transaction->selling_price, 0, ',', '.') }}</span>
                </div>
                @if($transaction->total_discount > 0)
                    <div class="flex justify-between text-xs text-red-600">
                        <span>Diskon</span>
                        <span class="font-bold">-Rp {{ number_format($transaction->total_discount, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-lg font-black border-t-2 border-black pt-2">
                    <span>TOTAL</span>
                    <span>Rp
                        {{ number_format($transaction->paid_amount ?: $transaction->selling_price - $transaction->total_discount, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="bg-gray-50 p-4 rounded-lg mb-8">
            <ul class="text-[9px] text-gray-600 space-y-1 list-disc pl-4">
                <li class="font-bold italic">Garansi 1 Bulan (Nota Dan Segel Jangan Hilang)</li>
                <li>Barang yang Sudah Dibeli Tidak Dapat Dikembalikan/Ditukarkan</li>
            </ul>
        </div>

        <div class="flex justify-between text-center text-xs mt-10">
            <div>
                <div class="mb-12">Penerima</div>
                <div class="border-b border-gray-300 w-24 mx-auto"></div>
            </div>
            <div>
                <div class="mb-12">Hormat Kami</div>
                <div class="font-black">PSTORE</div>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('receipt-content');
            const opt = {
                margin: 0,
                filename: 'Nota-PSTORE-{{ $transaction->receipt_id }}.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
                jsPDF: { unit: 'mm', format: [100, 180], orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>

</html>