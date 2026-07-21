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
            <img src="{{ url('/images/logo-pstore.png') }}" alt="PSTORE" class="w-16 h-auto object-contain shrink-0" />
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
                    <th class="py-2 text-left w-24">IMEI</th>
                    <th class="py-2 text-left">Deskripsi Barang</th>
                    <th class="py-2 text-right w-24">Harga Satuan</th>
                    <th class="py-2 text-right w-20">Diskon</th>
                    <th class="py-2 text-center w-12">Qty</th>
                    <th class="py-2 text-right w-28">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @php
                    // 1. Merge raw items
                    $baseBladeItems = [];
                    foreach($transaction->items as $it) {
                        $it->is_hp = true;
                        $baseBladeItems[] = $it;
                    }
                    foreach(($transaction->nonHpItems ?? []) as $it) {
                        $it->is_hp = false;
                        $baseBladeItems[] = $it;
                    }

                    // 2. Run Transformer to group bundle components under an Injected Header Row
                    $processedBladeItems = [];
                    $seenBundleKeys = [];

                    foreach($baseBladeItems as $it) {
                        $notes = strtolower($it->is_hp ? ($it->pivot->notes ?? '') : ($it->notes ?? ''));
                        $isBundle = str_contains($notes, 'paket') || str_contains($notes, 'bundle');
                        $groupKey = $it->is_hp ? ($it->pivot->notes ?? '') : ($it->notes ?? '');

                        if (!$isBundle) {
                            $it->is_bundle_header = false;
                            $it->is_bundle_child = false;
                            $processedBladeItems[] = $it;
                        } else {
                            if (!in_array($groupKey, $seenBundleKeys)) {
                                $seenBundleKeys[] = $groupKey;

                                // Extract all children belonging to this bundle
                                $groupChildren = [];
                                foreach($baseBladeItems as $c) {
                                    $cKey = $c->is_hp ? ($c->pivot->notes ?? '') : ($c->notes ?? '');
                                    if ($cKey === $groupKey) {
                                        $groupChildren[] = $c;
                                    }
                                }

                                // Calculate combined price
                                $groupTotal = 0;
                                $groupOriginalTotal = 0;
                                $groupDiscountTotal = 0;
                                foreach($groupChildren as $child) {
                                    $price = ($child->is_hp ? ($child->pivot->selling_price ?? 0) : ($child->selling_price ?? 0));
                                    $discount = ($child->is_hp ? ($child->pivot->item_discount ?? 0) : ($child->item_discount ?? 0));
                                    $qty = $child->is_hp ? 1 : ($child->quantity ?? 1);
                                    
                                    $groupOriginalTotal += ($price * $qty);
                                    $groupDiscountTotal += ($discount * $qty);
                                    $groupTotal += (abs($price - $discount) * $qty);
                                }

                                // Format cleaner display name
                                $cleanName = $groupKey;
                                $cleanName = str_ireplace('paket bundling:', 'Paket Bundling:', $cleanName);
                                $cleanName = str_ireplace('paket bundling', 'Paket Promo', $cleanName);
                                $cleanName = str_ireplace('paket promo:', 'Paket Promo:', $cleanName);
                                $cleanName = str_replace('📦 ', '', $cleanName);
                                $cleanName = trim($cleanName);
                                if (!str_contains(strtolower($cleanName), 'paket')) {
                                    $cleanName = 'Paket Promo: ' . $cleanName;
                                }

                                // Create special header object structure
                                $header = (object)[
                                    'is_hp' => false,
                                    'is_bundle_header' => true,
                                    'is_bundle_child' => false,
                                    'name' => $cleanName,
                                    'price' => $groupTotal,
                                    'original_price' => $groupOriginalTotal,
                                    'discount' => $groupDiscountTotal,
                                    'qty' => 1,
                                    'imei' => '-'
                                ];

                                $processedBladeItems[] = $header;

                                // Push child items formatted
                                foreach($groupChildren as $child) {
                                    $child->is_bundle_header = false;
                                    $child->is_bundle_child = true;
                                    $child->_hidePrice = true;
                                    $processedBladeItems[] = $child;
                                }
                            }
                        }
                    }
                @endphp

                @foreach($processedBladeItems as $idx => $item)
                    <tr class="border-b border-gray-100 @if($item->is_bundle_header) bg-blue-50/30 font-bold @endif">
                        <!-- IMEI COLUMN -->
                        <td class="py-3">
                            @if($item->is_bundle_header)
                                <div class="text-[10px] text-gray-400 text-center font-bold">-</div>
                            @else
                                <div class="text-[10px] text-gray-500 font-mono font-bold @if($item->is_bundle_child) pl-4 @endif">
                                    {{ ($item->is_hp ? ($item->pivot->imei && $item->pivot->imei !== '-' ? $item->pivot->imei : '-') : '-') }}
                                </div>
                            @endif
                        </td>

                        <!-- DESKRIPSI COLUMN -->
                        <td class="py-3">
                            @if($item->is_bundle_header)
                                <div class="font-bold uppercase text-xs text-neutral-900">📦 {{ $item->name }}</div>
                            @elseif($item->is_hp)
                                @php
                                    $pName = $item->product?->name ?? 'Produk';
                                    $pName = str_replace('IN:', '', str_replace('OUT:', '', $pName));
                                    $pName = str_ireplace(['paket bundling', 'paket bundling '], 'Paket Promo', $pName);
                                    $pName = str_replace('📦 ', '', $pName);
                                    $dbBrand = $item->product?->brandRelation?->name ?? $item->product?->brand ?? 'PSTORE UNIT';
                                    $storage = $item->storage ?? '-';
                                    $kondisi = $item->condition === 'new' ? 'Baru' : ($item->condition === 'ex_ibox' ? 'Ex iBox' : 'Second');
                                @endphp
                                <div class="font-bold uppercase @if($item->is_bundle_child) pl-4 text-gray-800 font-semibold flex items-center @endif">
                                    @if($item->is_bundle_child)<span class="text-neutral-600 mr-1.5 font-black">*</span>@endif
                                    {{ $dbBrand }} - {{ $pName }}
                                </div>
                                <div class="text-[10px] text-gray-500 @if($item->is_bundle_child) pl-7 @endif">Storage: {{ $storage }} | Kondisi: {{ $kondisi }}</div>
                            @else
                                @php
                                    $nonHpName = $item->product?->name ?? $item->name;
                                    $nonHpName = str_ireplace(['paket bundling', 'paket bundling '], 'Paket Promo', $nonHpName);
                                    $nonHpName = str_replace('📦 ', '', $nonHpName);
                                @endphp
                                <div class="font-bold uppercase @if($item->is_bundle_child) pl-4 text-gray-800 font-semibold flex items-center @endif">
                                    @if($item->is_bundle_child)<span class="text-neutral-600 mr-1.5 font-black">*</span>@endif
                                    {{ $nonHpName }}
                                </div>
                                <div class="text-[10px] text-gray-500 @if($item->is_bundle_child) pl-7 @endif">AKSESORIS</div>
                            @endif
                        </td>

                        <!-- HARGA SATUAN COLUMN -->
                        <td class="py-3 text-right font-bold">
                            @if($item->is_bundle_header)
                                {{ number_format($item->original_price ?? $item->price, 0, ',', '.') }}
                            @elseif(isset($item->_hidePrice))
                                -
                            @else
                                @php
                                    $original_price = ($item->is_hp ? ($item->pivot->selling_price ?? 0) : ($item->selling_price ?? 0));
                                @endphp
                                {{ number_format(abs($original_price), 0, ',', '.') }}
                            @endif
                        </td>

                        <!-- DISKON COLUMN -->
                        <td class="py-3 text-right text-red-600 font-bold">
                            @if($item->is_bundle_header)
                                {{ isset($item->discount) && $item->discount > 0 ? '-'.number_format($item->discount, 0, ',', '.') : '-' }}
                            @elseif(isset($item->_hidePrice))
                                -
                            @else
                                @php
                                    $discount = ($item->is_hp ? ($item->pivot->item_discount ?? 0) : ($item->item_discount ?? 0));
                                @endphp
                                {{ $discount > 0 ? '-'.number_format(abs($discount), 0, ',', '.') : '-' }}
                            @endif
                        </td>

                        <!-- QTY COLUMN -->
                        <td class="py-3 text-center">
                            @if($item->is_bundle_header)
                                1
                            @else
                                {{ ($item->is_hp ? 1 : ($item->quantity ?? 1)) }}
                            @endif
                        </td>

                        <!-- JUMLAH COLUMN -->
                        <td class="py-3 text-right font-bold">
                            @if($item->is_bundle_header)
                                {{ number_format($item->price, 0, ',', '.') }}
                            @elseif(isset($item->_hidePrice))
                                -
                            @else
                                @php
                                    $price = abs(($item->is_hp ? ($item->pivot->selling_price ?? 0) : ($item->selling_price ?? 0)) - ($item->is_hp ? ($item->pivot->item_discount ?? 0) : ($item->item_discount ?? 0)));
                                    $qty = ($item->is_hp ? 1 : ($item->quantity ?? 1));
                                @endphp
                                {{ number_format($price * $qty, 0, ',', '.') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
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