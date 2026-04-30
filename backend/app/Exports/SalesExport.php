<?php

namespace App\Exports;

use App\Models\StockOut;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SalesExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
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

        $query = StockOut::with(['items.product', 'items.distributor', 'nonHpItems.product', 'user', 'inventoryUser', 'branch', 'onlineShop', 'paymentMethod'])
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

        $stockOuts = $query->latest('created_at')->get();
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

            if ($so->is_bundle) {
                $totalPrice = 0;
                $allImeis = [];
                foreach ($so->items as $item) {
                    $totalPrice += ($item->pivot->selling_price ?? 0);
                    if ($item->imei) $allImeis[] = $item->imei;
                }
                foreach ($so->nonHpItems as $item) {
                    $totalPrice += (($item->price ?? 0) * $item->quantity);
                }

                $rows[] = [
                    'waktu' => $so->created_at->format('d/m/Y H:i'),
                    'order_no' => $so->receipt_id,
                    'lokasi' => $location,
                    'user' => $csName,
                    'customer' => $so->customer_name ?? '-',
                    'whatsapp' => $so->customer_wa ?? '-',
                    'category' => strtoupper($so->category),
                    'product' => "📦 " . ($so->bundle_description ?: 'Paket Bundling'),
                    'imei' => implode(', ', array_map(fn($i) => "'" . $i, $allImeis)) ?: '-',
                    'qty' => 1,
                    'price' => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
                    'total' => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
                    'distributor' => $so->items->first()->distributor->name ?? '-',
                    'payment' => $payment ?: '-',
                    'status' => strtoupper($so->status),
                    'price_out' => '-',
                    'price_in' => '-',
                    'balance' => '-'
                ];
            } else {
                foreach ($so->items as $item) {
                    $productName = ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . " " . ($item->ram ?? '') . "/" . ($item->storage ?? '');
                    $condition = $item->condition === 'new' ? 'Baru' : 'Second';
                    $notes = $item->pivot->notes ? " (" . $item->pivot->notes . ")" : "";
                    $price = $item->pivot->selling_price ?? 0;
                    
                    $distOut = $item->distributor->name ?? $item->supplier_name ?? 'PSTORE';
                    $finalDistributor = $distOut;
                    $finalProductName = $productName . " [" . $condition . "]" . $notes;
                    $finalImei = $item->imei ? "'" . $item->imei : '-';
                    
                    $pOut = $price; $pIn = 0; $diff = 0;

                    if ($exchangeInfo) {
                        $pOut = (float)($exchangeInfo->outgoing_price ?? ($cat === 'tukar_unit' ? $exchangeInfo->incoming_cost_price : $price));
                        $pIn = (float)($exchangeInfo->incoming_cost_price ?? 0);
                        $diff = $pOut - $pIn;

                        $inProd = ($exchangeInfo->incomingProductType->name ?? 'Unit Konsumen') . " [" . ($exchangeInfo->incoming_condition ?? 'Second') . "]";
                        $inImei = $exchangeInfo->incoming_imei ?? '-';
                        $finalProductName = "OUT: " . $productName . " [" . $condition . "]" . $notes . "\nIN: " . $inProd;
                        $finalImei = "OUT: " . ($item->imei ?? '-') . "\nIN: " . $inImei;
                        $distIn = $exchangeInfo->distributor->name ?? 'Konsumen';
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
                        'total' => 'Rp ' . number_format($pOut, 0, ',', '.'),
                        'distributor' => $finalDistributor,
                        'payment' => $payment ?: '-',
                        'status' => strtoupper($so->status),
                        'price_out' => $exchangeInfo ? 'Rp ' . number_format($pOut, 0, ',', '.') : '-',
                        'price_in' => $exchangeInfo ? 'Rp ' . number_format($pIn, 0, ',', '.') : '-',
                        'balance' => $exchangeInfo ? 'Rp ' . number_format($diff, 0, ',', '.') : '-'
                    ];
                }

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
                        'category' => strtoupper($so->category),
                        'product' => ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . $notes,
                        'imei' => '-',
                        'qty' => $item->quantity,
                        'price' => 'Rp ' . number_format($price, 0, ',', '.'),
                        'total' => 'Rp ' . number_format($price * $item->quantity, 0, ',', '.'),
                        'distributor' => $item->supplier_name ?? '-',
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
            'Distributor',
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
            $row['waktu'] ?? '',
            $row['order_no'] ?? '',
            $row['lokasi'] ?? '',
            $row['user'] ?? '',
            $row['customer'] ?? '',
            $row['whatsapp'] ?? '',
            $row['category'] ?? '',
            $row['product'] ?? '',
            $row['imei'] ?? '',
            $row['qty'] ?? 0,
            $row['price'] ?? '',
            $row['total'] ?? '',
            $row['distributor'] ?? '',
            $row['payment'] ?? '',
            $row['status'] ?? '',
            $row['price_out'] ?? '',
            $row['price_in'] ?? '',
            $row['balance'] ?? ''
        ];
    }
}
