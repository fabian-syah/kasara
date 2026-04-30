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

        $query = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'branch', 'onlineShop', 'paymentMethod'])
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

        foreach ($stockOuts as $so) {
            $location = $so->branch_id ? ($so->branch->name ?? '-') : ($so->onlineShop->name ?? '-');
            
            // HP Items
            foreach ($so->items as $item) {
                $rows[] = [
                    'waktu' => $so->created_at->format('d/m/Y H:i'),
                    'order_no' => $so->receipt_id,
                    'lokasi' => $location,
                    'user' => $so->user->name ?? '-',
                    'customer' => $so->customer_name ?? '-',
                    'whatsapp' => $so->customer_wa ?? '-',
                    'category' => str_replace('_', ' ', strtoupper($so->category)),
                    'product' => ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . " " . ($item->ram ?? '') . "/" . ($item->storage ?? '') . " (" . ($item->condition === 'new' ? 'Baru' : 'Second') . ")",
                    'imei' => $item->imei ?? '-',
                    'qty' => 1,
                    'price' => $item->pivot->selling_price ?? 0,
                    'total' => $item->pivot->selling_price ?? 0,
                    'payment' => $so->paymentMethod->name ?? ($so->payment_method_name ?? '-'),
                    'status' => strtoupper($so->status)
                ];
            }

            // Non-HP Items
            foreach ($so->nonHpItems as $item) {
                $rows[] = [
                    'waktu' => $so->created_at->format('d/m/Y H:i'),
                    'order_no' => $so->receipt_id,
                    'lokasi' => $location,
                    'user' => $so->user->name ?? '-',
                    'customer' => $so->customer_name ?? '-',
                    'whatsapp' => $so->customer_wa ?? '-',
                    'category' => str_replace('_', ' ', strtoupper($so->category)),
                    'product' => ($item->product->brand ?? '') . ' ' . ($item->product->name ?? ''),
                    'imei' => '-',
                    'qty' => $item->quantity,
                    'price' => $item->price ?? 0,
                    'total' => ($item->price ?? 0) * $item->quantity,
                    'payment' => $so->paymentMethod->name ?? ($so->payment_method_name ?? '-'),
                    'status' => strtoupper($so->status)
                ];
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
            'User/Admin',
            'Nama Customer',
            'WhatsApp',
            'Kategori',
            'Produk',
            'IMEI/S/N',
            'Qty',
            'Harga Satuan',
            'Total Harga',
            'Metode Pembayaran',
            'Status'
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
