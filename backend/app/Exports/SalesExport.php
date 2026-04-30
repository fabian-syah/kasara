<?php

namespace App\Exports;

use App\Models\StockOut;

class SalesExport
{
    protected $branchId;
    protected $onlineShopId;
    protected $startDate;
    protected $endDate;
    protected $user;

    public function __construct($branchId = null, $onlineShopId = null, $startDate = null, $endDate = null, $user = null)
    {
        $this->branchId = $branchId;
        $this->onlineShopId = $onlineShopId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->user = $user;
    }

    public function title(): string
    {
        return 'Laporan Penjualan';
    }

    public function collection()
    {
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'sale', 'pos', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'refund', 'angkat_barang', 'bundling'];

        $query = StockOut::with(['items.product', 'items.distributor', 'nonHpItems.product', 'nonHpItems.distributor', 'user', 'inventoryUser', 'branch', 'onlineShop', 'paymentMethod'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$this->startDate, $this->endDate])
            ->where('status', '!=', 'cancelled');

        if ($this->branchId) {
            $query->where('stock_outs.branch_id', $this->branchId);
        } elseif ($this->onlineShopId) {
            $query->where('stock_outs.online_shop_id', $this->onlineShopId);
        } else {
            if ($this->user && !$this->user->hasAnyRole(['super_admin', 'owner', 'analist', 'analis'])) {
                $branchIds = $this->user->getAccessibleBranchIds();
                $onlineShopIds = $this->user->getAccessibleOnlineShopIds();
                $query->where(function($q) use ($branchIds, $onlineShopIds) {
                    $q->whereIn('stock_outs.branch_id', $branchIds)
                      ->orWhereIn('stock_outs.online_shop_id', $onlineShopIds);
                });
            }
        }

        $stockOuts = $query->orderBy('reporting_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        $rows = [];

        $receiptIds = $stockOuts->pluck('receipt_id')->filter()->toArray();
        $ttData = \App\Models\TukarTambah::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');
        $dgData = \App\Models\Downgrade::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');
        $ueData = \App\Models\UnitExchange::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');

        foreach ($stockOuts as $so) {
            $location = $so->branch_id ? ($so->branch->name ?? '-') : ($so->onlineShop->name ?? '-');
            $csName = $so->inventoryUser->name ?? ($so->user->name ?? '-');
            $cat = strtolower($so->category);
            $receiptId = $so->receipt_id;
            
            $exchangeInfo = null;
            if ($cat === 'tukar_tambah') $exchangeInfo = $ttData->get($receiptId);
            elseif ($cat === 'downgrade') $exchangeInfo = $dgData->get($receiptId);
            elseif ($cat === 'tukar_unit') $exchangeInfo = $ueData->get($receiptId);

            $payment = $so->paymentMethod->name ?? null;
            if (!$payment && !empty($so->split_payments_data)) {
                $payment = implode(', ', array_column($so->split_payments_data, 'method_name'));
            }

            // Helper to calculate split payments
            $splitPayments = $so->split_payments_data;
            $payData = [
                'cash' => 0,
                'edc_bca' => 0,
                'edc_mandiri' => 0,
                'trf_bca' => 0,
                'trf_mandiri' => 0,
                'qris' => 0,
                'other' => 0
            ];

            if (empty($splitPayments)) {
                $name = strtolower($so->paymentMethod->name ?? '');
                $amount = $so->paid_amount ?: $so->selling_price;
                if (str_contains($name, 'cash') || str_contains($name, 'tunai')) $payData['cash'] = $amount;
                elseif (str_contains($name, 'edc') && str_contains($name, 'bca')) $payData['edc_bca'] = $amount;
                elseif (str_contains($name, 'edc') && str_contains($name, 'mandiri')) $payData['edc_mandiri'] = $amount;
                elseif (str_contains($name, 'bca')) $payData['trf_bca'] = $amount;
                elseif (str_contains($name, 'mandiri')) $payData['trf_mandiri'] = $amount;
                elseif (str_contains($name, 'qris')) $payData['qris'] = $amount;
                else $payData['other'] = $amount;
            } else {
                foreach ($splitPayments as $sp) {
                    $name = strtolower($sp['method_name'] ?? '');
                    $amount = $sp['amount'] ?? 0;
                    if (str_contains($name, 'cash') || str_contains($name, 'tunai')) $payData['cash'] += $amount;
                    elseif (str_contains($name, 'edc') && str_contains($name, 'bca')) $payData['edc_bca'] += $amount;
                    elseif (str_contains($name, 'edc') && str_contains($name, 'mandiri')) $payData['edc_mandiri'] += $amount;
                    elseif (str_contains($name, 'bca')) $payData['trf_bca'] += $amount;
                    elseif (str_contains($name, 'mandiri')) $payData['trf_mandiri'] += $amount;
                    elseif (str_contains($name, 'qris')) $payData['qris'] += $amount;
                    else $payData['other'] += $amount;
                }
            }

            $groups = [];
            $singles = [];

            // Grouping logic: Items with the same non-empty note are combined into a bundle row.
            // Items with empty notes are treated as individual single rows.
            foreach ($so->items as $item) {
                $note = trim($item->pivot->notes ?? '');
                if ($note !== '') {
                    $groups[$note][] = ['type' => 'hp', 'data' => $item];
                } else {
                    $singles[] = ['type' => 'hp', 'data' => $item];
                }
            }
            foreach ($so->nonHpItems as $item) {
                $note = trim($item->notes ?? '');
                if ($note !== '') {
                    $groups[$note][] = ['type' => 'non_hp', 'data' => $item];
                } else {
                    $singles[] = ['type' => 'non_hp', 'data' => $item];
                }
            }

            // 1. Process Grouped Bundles
            foreach ($groups as $groupName => $items) {
                $totalPrice = 0;
                $allImeis = [];
                $productList = [];
                $distributors = [];

                foreach ($items as $it) {
                    if ($it['type'] === 'hp') {
                        $item = $it['data'];
                        $rawPrice = $item->pivot->selling_price ?? 0;
                        $itemDiscount = $item->pivot->item_discount ?? 0;
                        $distDiscount = $item->pivot->distributed_discount ?? 0;
                        $netPrice = $rawPrice - $itemDiscount - $distDiscount;
                        $totalPrice += $netPrice;

                        if ($item->imei) $allImeis[] = $item->imei;
                        
                        $productName = ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . " " . ($item->ram ?? '') . "/" . ($item->storage ?? '') . " [" . ($item->condition === 'new' ? 'Baru' : 'Second') . "]";
                        $productList[] = ($so->is_bundle ? "📦 " : "") . $productName;
                        
                        $dist = $item->distributor->name ?? $item->supplier_name ?? 'PSTORE';
                        if (!in_array($dist, $distributors)) $distributors[] = $dist;
                    } else {
                        $item = $it['data'];
                        $rawPrice = $item->selling_price ?? 0;
                        $itemDiscount = $item->item_discount ?? 0;
                        $distDiscount = $item->distributed_discount ?? 0;
                        $netPrice = ($rawPrice - $itemDiscount - $distDiscount) * $item->quantity;
                        $totalPrice += $netPrice;

                        $productName = ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . " (Qty: {$item->quantity})";
                        $productList[] = ($so->is_bundle ? "📦 " : "") . $productName;
                        
                        $dist = $item->distributor->name ?? $item->product->brand ?? $item->supplier_name ?? '-';
                        if ($dist !== '-' && !in_array($dist, $distributors)) $distributors[] = $dist;
                    }
                }

                $rows[] = [
                    'waktu' => $so->created_at->format('d/m/Y H:i'),
                    'order_no' => $so->receipt_id,
                    'lokasi' => $location,
                    'user' => $csName,
                    'customer' => $so->customer_name ?? '-',
                    'whatsapp' => $so->customer_wa ?? '-',
                    'category' => strtoupper($so->category),
                    'product' => implode("\n", $productList),
                    'imei' => implode(', ', array_map(fn($i) => "'" . $i, $allImeis)) ?: '-',
                    'qty' => 1,
                    'price' => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
                    'total' => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
                    'distributor' => implode(', ', $distributors) ?: '-',
                    'payment' => $payment ?: '-',
                    'cash_toko' => $payData['cash'],
                    'edc_bca' => $payData['edc_bca'],
                    'edc_mandiri' => $payData['edc_mandiri'],
                    'trf_bca' => $payData['trf_bca'],
                    'trf_mandiri' => $payData['trf_mandiri'],
                    'qris' => $payData['qris'],
                    'other' => $payData['other'],
                    'status' => strtoupper($so->status),
                    'price_out' => '-',
                    'price_in' => '-',
                    'balance' => '-'
                ];
            }

            // 2. Process Individual Singles
            foreach ($singles as $it) {
                if ($it['type'] === 'hp') {
                    $item = $it['data'];
                    $productName = ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . " " . ($item->ram ?? '') . "/" . ($item->storage ?? '');
                    $condition = $item->condition === 'new' ? 'Baru' : 'Second';
                    
                    $rawPrice = $item->pivot->selling_price ?? 0;
                    $itemDiscount = $item->pivot->item_discount ?? 0;
                    $distDiscount = $item->pivot->distributed_discount ?? 0;
                    $price = $rawPrice - $itemDiscount - $distDiscount;
                    
                    $distOut = $item->distributor->name ?? $item->supplier_name ?? 'PSTORE';
                    $finalDistributor = $distOut;
                    $finalProductName = $productName . " [" . $condition . "]";
                    $finalImei = $item->imei ? "'" . $item->imei : '-';
                    
                    $pOut = $price; $pIn = 0; $diff = 0;

                    if ($exchangeInfo) {
                        $pOut = (float)($exchangeInfo->outgoing_price ?? ($cat === 'tukar_unit' ? $exchangeInfo->incoming_cost_price : $price));
                        $pIn = (float)($exchangeInfo->incoming_cost_price ?? 0);
                        $diff = $pOut - $pIn;

                        $inProd = ($exchangeInfo->incomingProductType->name ?? 'Unit Konsumen') . " [" . ($exchangeInfo->incoming_condition ?? 'Second') . "]";
                        $inImei = $exchangeInfo->incoming_imei ?? '-';
                        $finalProductName = "OUT: " . $productName . " [" . $condition . "]\nIN: " . $inProd;
                        $finalImei = "OUT: " . ($item->imei ?? '-') . "\nIN: " . $inImei;
                        
                        $distIn = $exchangeInfo->distributor->name ?? $exchangeInfo->incoming_source ?? 'Konsumen';
                        $finalDistributor = "OUT: " . $distOut . "\nIN: " . $distIn;
                    }

                    $rows[] = [
                        'waktu' => $so->created_at->format('d/m/Y H:i'),
                        'order_no' => $so->receipt_id,
                        'lokasi' => $location,
                        'user' => $csName,
                        'customer' => $so->customer_name ?? '-',
                        'whatsapp' => $so->customer_wa ?? '-',
                        'category' => strtoupper($so->category),
                        'product' => $finalProductName,
                        'imei' => $finalImei,
                        'qty' => 1,
                        'price' => 'Rp ' . number_format($pOut, 0, ',', '.'),
                        'total' => 'Rp ' . number_format($exchangeInfo ? $diff : $pOut, 0, ',', '.'),
                        'distributor' => $finalDistributor,
                        'payment' => $payment ?: '-',
                        'cash_toko' => $payData['cash'],
                        'edc_bca' => $payData['edc_bca'],
                        'edc_mandiri' => $payData['edc_mandiri'],
                        'trf_bca' => $payData['trf_bca'],
                        'trf_mandiri' => $payData['trf_mandiri'],
                        'qris' => $payData['qris'],
                        'other' => $payData['other'],
                        'status' => strtoupper($so->status),
                        'price_out' => $exchangeInfo ? 'Rp ' . number_format($pOut, 0, ',', '.') : '-',
                        'price_in' => $exchangeInfo ? 'Rp ' . number_format($pIn, 0, ',', '.') : '-',
                        'balance' => $exchangeInfo ? 'Rp ' . number_format($diff, 0, ',', '.') : '-'
                    ];
                } else {
                    $item = $it['data'];
                    $rawPrice = $item->selling_price ?? 0;
                    $itemDiscount = $item->item_discount ?? 0;
                    $distDiscount = $item->distributed_discount ?? 0;
                    $price = $rawPrice - $itemDiscount - $distDiscount;
                    
                    $rows[] = [
                        'waktu' => $so->created_at->format('d/m/Y H:i'),
                        'order_no' => $so->receipt_id,
                        'lokasi' => $location,
                        'user' => $csName,
                        'customer' => $so->customer_name ?? '-',
                        'whatsapp' => $so->customer_wa ?? '-',
                        'category' => strtoupper($so->category),
                        'product' => ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . " (Qty: {$item->quantity})",
                        'imei' => '-',
                        'qty' => $item->quantity,
                        'price' => 'Rp ' . number_format($price, 0, ',', '.'),
                        'total' => 'Rp ' . number_format($price * $item->quantity, 0, ',', '.'),
                        'distributor' => $item->distributor->name ?? $item->product->brand ?? $item->supplier_name ?? '-',
                        'payment' => $payment ?: '-',
                        'cash_toko' => $payData['cash'],
                        'edc_bca' => $payData['edc_bca'],
                        'edc_mandiri' => $payData['edc_mandiri'],
                        'trf_bca' => $payData['trf_bca'],
                        'trf_mandiri' => $payData['trf_mandiri'],
                        'qris' => $payData['qris'],
                        'other' => $payData['other'],
                        'status' => strtoupper($so->status),
                        'price_out' => '-',
                        'price_in' => '-',
                        'balance' => '-'
                    ];
                }
            }

        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Waktu',
            'No Pesanan',
            'Lokasi',
            'Customer Service',
            'Nama Customer',
            'WhatsApp',
            'Kategori',
            'Produk',
            'IMEI/S/N',
            'Qty',
            'Harga Satuan',
            'Total Harga',
            'Distributor',
            'Metode Pembayaran',
            'Cash Toko',
            'EDC BCA',
            'EDC Mandiri',
            'Transfer BCA',
            'Transfer Mandiri',
            'QRIS',
            'Lainnya',
            'Status',
            'Harga Unit Keluar',
            'Harga Unit Masuk',
            'Selisih (Sisa Bayar)'
        ];
    }
}
