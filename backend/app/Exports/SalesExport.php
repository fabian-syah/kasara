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

        $query = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'branch', 'onlineShop', 'paymentMethod'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$this->startDate, $this->endDate])
            ->where('status', '!=', 'cancelled');

        if ($this->branchId) {
            $query->where('stock_outs.branch_id', $this->branchId);
        } elseif ($this->onlineShopId) {
            $query->where('stock_outs.online_shop_id', $this->onlineShopId);
        } else {
            // User-based scoping for non-admins
            if ($this->user && !$this->user->hasAnyRole(['super_admin', 'owner', 'analist', 'analis'])) {
                $branchIds = $this->user->getAccessibleBranchIds();
                $onlineShopIds = $this->user->getAccessibleOnlineShopIds();
                
                $query->where(function($q) use ($branchIds, $onlineShopIds) {
                    $q->whereIn('stock_outs.branch_id', $branchIds)
                      ->orWhereIn('stock_outs.online_shop_id', $onlineShopIds)
                      ->orWhereHas('user', function($uq) use ($branchIds, $onlineShopIds) {
                          $uq->whereIn('branch_id', $branchIds)
                             ->orWhereIn('online_shop_id', $onlineShopIds);
                      });
                });
            }
        }

        $stockOuts = $query->latest('created_at')->get();
        $rows = [];

        // Pre-fetch exchange data to avoid N+1
        $receiptIds = $stockOuts->pluck('receipt_id')->unique()->toArray();
        $ttData = \App\Models\TukarTambah::with('incomingProductType')->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');
        $dgData = \App\Models\Downgrade::with('incomingProductType')->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');
        $ueData = \App\Models\UnitExchange::with('incomingProductType')->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');

        foreach ($stockOuts as $so) {
            $location = $so->branch_id ? ($so->branch->name ?? '-') : ($so->onlineShop->name ?? '-');
            $csName = $so->inventoryUser->name ?? ($so->user->name ?? '-');
            $cat = strtolower($so->category);
            $receiptId = $so->receipt_id;
            
            // Check for Exchange Data
            $exchangeInfo = null;
            $exchangeType = '';
            if ($cat === 'tukar_tambah') { $exchangeInfo = $ttData->get($receiptId); $exchangeType = 'TUKAR TAMBAH'; }
            elseif ($cat === 'downgrade') { $exchangeInfo = $dgData->get($receiptId); $exchangeType = 'DOWNGRADE'; }
            elseif ($cat === 'tukar_unit') { $exchangeInfo = $ueData->get($receiptId); $exchangeType = 'TUKAR UNIT'; }

            if ($so->is_bundle) {
                // Consolidate bundle into ONE row
                $totalPrice = 0;
                $allImeis = [];
                
                foreach ($so->items as $item) {
                    $totalPrice += ($item->pivot->selling_price ?? 0);
                    if ($item->imei) $allImeis[] = $item->imei;
                }
                foreach ($so->nonHpItems as $item) {
                    $totalPrice += (($item->price ?? 0) * $item->quantity);
                }

                $payment = $so->paymentMethod->name ?? null;
                if (!$payment && !empty($so->split_payments_data)) {
                    $payment = implode(', ', array_column($so->split_payments_data, 'method_name'));
                }

                $priceOut = 0;
                $priceIn = 0;
                $balance = 0;

                // Category specific logic
                $isDowngrade = strtolower($so->category) === 'downgrade';
                $isTukarTambah = strtolower($so->category) === 'tukar_tambah';
                $isTukarUnit = strtolower($so->category) === 'tukar_unit';

                if ($isDowngrade || $isTukarTambah || $isTukarUnit) {
                    $priceOut = $totalPrice; // Usually the selling price is the OUT price
                    $priceIn = 0; // Needs data from somewhere else if available
                    $balance = $totalPrice; 
                }

                $rows[] = [
                    'waktu' => $so->created_at->format('d/m/Y H:i'),
                    'order_no' => $so->receipt_id,
                    'lokasi' => $location,
                    'user' => $csName,
                    'customer' => $so->customer_name ?? '-',
                    'whatsapp' => $so->customer_wa ?? '-',
                    'category' => str_replace('_', ' ', strtoupper($so->category)),
                    'product' => "📦 " . ($so->bundle_description ?: 'Paket Bundling'),
                    'imei' => implode(', ', array_map(fn($i) => "'" . $i, $allImeis)) ?: '-',
                    'qty' => 1,
                    'price' => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
                    'total' => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
                    'payment' => $payment ?: '-',
                    'status' => strtoupper($so->status),
                    'price_out' => $priceOut ? 'Rp ' . number_format($priceOut, 0, ',', '.') : '-',
                    'price_in' => $priceIn ? 'Rp ' . number_format($priceIn, 0, ',', '.') : '-',
                    'balance' => $balance ? 'Rp ' . number_format($balance, 0, ',', '.') : '-'
                ];

                // If bundle IS an exchange (rare but possible), add the incoming unit row
                if ($exchangeInfo) {
                    $rows[] = [
                        'waktu' => $so->created_at->format('d/m/Y H:i'),
                        'order_no' => $so->receipt_id,
                        'lokasi' => $location,
                        'user' => $csName,
                        'customer' => $so->customer_name ?? '-',
                        'whatsapp' => $so->customer_wa ?? '-',
                        'category' => $exchangeType . " (MASUK)",
                        'product' => ($exchangeInfo->incomingProductType->name ?? 'Unit Konsumen') . " [" . ($exchangeInfo->incoming_condition ?? '-') . "]",
                        'imei' => "'" . ($exchangeInfo->incoming_imei ?? '-'),
                        'qty' => 1,
                        'price' => 'Rp ' . number_format($exchangeInfo->incoming_cost_price ?? 0, 0, ',', '.'),
                        'total' => 'Rp ' . number_format($exchangeInfo->incoming_cost_price ?? 0, 0, ',', '.'),
                        'payment' => '-',
                        'status' => 'INCOMING',
                        'price_out' => '-',
                        'price_in' => 'Rp ' . number_format($exchangeInfo->incoming_cost_price ?? 0, 0, ',', '.'),
                        'balance' => '-'
                    ];
                }
            } else {
                // Standard multi-row display for non-bundles
                $payment = $so->paymentMethod->name ?? null;
                if (!$payment && !empty($so->split_payments_data)) {
                    $payment = implode(', ', array_column($so->split_payments_data, 'method_name'));
                }

                // HP Items
                foreach ($so->items as $item) {
                    $productName = ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . " " . ($item->ram ?? '') . "/" . ($item->storage ?? '');
                    $condition = $item->condition === 'new' ? 'Baru' : 'Second';
                    $notes = $item->pivot->notes ? " (" . $item->pivot->notes . ")" : "";
                    
                    $price = $item->pivot->selling_price ?? 0;
                    $priceOut = 0;
                    $priceIn = 0;
                    $balance = 0;

                    if ($exchangeInfo) {
                        $priceOut = $exchangeInfo->outgoing_price ?? $price;
                        $priceIn = $exchangeInfo->incoming_cost_price ?? 0;
                        $balance = $exchangeInfo->price_difference ?? ($priceOut - $priceIn);
                    }

                    $rows[] = [
                        'waktu' => $so->created_at->format('d/m/Y H:i'),
                        'order_no' => $so->receipt_id,
                        'lokasi' => $location,
                        'user' => $csName,
                        'customer' => $so->customer_name ?? '-',
                        'whatsapp' => $so->customer_wa ?? '-',
                        'category' => $exchangeInfo ? $exchangeType . " (KELUAR)" : str_replace('_', ' ', strtoupper($so->category)),
                        'product' => $productName . " [" . $condition . "]" . $notes,
                        'imei' => $item->imei ? "'" . $item->imei : '-',
                        'qty' => 1,
                        'price' => 'Rp ' . number_format($price, 0, ',', '.'),
                        'total' => 'Rp ' . number_format($price, 0, ',', '.'),
                        'payment' => $payment ?: '-',
                        'status' => strtoupper($so->status),
                        'price_out' => $priceOut ? 'Rp ' . number_format($priceOut, 0, ',', '.') : '-',
                        'price_in' => $priceIn ? 'Rp ' . number_format($priceIn, 0, ',', '.') : '-',
                        'balance' => $balance ? 'Rp ' . number_format($balance, 0, ',', '.') : '-'
                    ];
                }

                // If standard item IS an exchange, add the incoming unit row
                if ($exchangeInfo) {
                    $rows[] = [
                        'waktu' => $so->created_at->format('d/m/Y H:i'),
                        'order_no' => $so->receipt_id,
                        'lokasi' => $location,
                        'user' => $csName,
                        'customer' => $so->customer_name ?? '-',
                        'whatsapp' => $so->customer_wa ?? '-',
                        'category' => $exchangeType . " (MASUK)",
                        'product' => ($exchangeInfo->incomingProductType->name ?? 'Unit Konsumen') . " [" . ($exchangeInfo->incoming_condition ?? '-') . "]",
                        'imei' => "'" . ($exchangeInfo->incoming_imei ?? '-'),
                        'qty' => 1,
                        'price' => 'Rp ' . number_format($exchangeInfo->incoming_cost_price ?? 0, 0, ',', '.'),
                        'total' => 'Rp ' . number_format($exchangeInfo->incoming_cost_price ?? 0, 0, ',', '.'),
                        'payment' => '-',
                        'status' => 'INCOMING',
                        'price_out' => '-',
                        'price_in' => 'Rp ' . number_format($exchangeInfo->incoming_cost_price ?? 0, 0, ',', '.'),
                        'balance' => '-'
                    ];
                }

                // Non-HP Items (Usually not exchangeable but included for completeness)
                foreach ($so->nonHpItems as $item) {
                    $notes = $item->notes ? " (" . $item->notes . ")" : "";
                    $price = $item->price ?? 0;
                    $rows[] = [
                        'waktu' => $so->created_at->format('d/m/Y H:i'),
                        'order_no' => $so->receipt_id,
                        'lokasi' => $location,
                        'user' => $csName,
                        'customer' => $so->customer_name ?? '-',
                        'whatsapp' => $so->customer_wa ?? '-',
                        'category' => str_replace('_', ' ', strtoupper($so->category)),
                        'product' => ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . $notes,
                        'imei' => '-',
                        'qty' => $item->quantity,
                        'price' => 'Rp ' . number_format($price, 0, ',', '.'),
                        'total' => 'Rp ' . number_format($price * $item->quantity, 0, ',', '.'),
                        'payment' => $payment ?: '-',
                        'status' => strtoupper($so->status),
                        'price_out' => '-',
                        'price_in' => '-',
                        'balance' => '-'
                    ];
                }
            }
        }

        return collect($rows);
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
            'Metode Pembayaran',
            'Status',
            'Harga Unit Keluar',
            'Harga Unit Masuk',
            'Selisih (Sisa Bayar)'
        ];
    }

    public function map($row): array
    {
        return [
            $row['waktu'],
            $row['order_no'],
            $row['lokasi'],
            $row['user'],
            $row['customer'],
            $row['whatsapp'],
            $row['category'],
            $row['product'],
            $row['imei'],
            $row['qty'],
            $row['price'],
            $row['total'],
            $row['payment'],
            $row['status']
        ];
    }
}
