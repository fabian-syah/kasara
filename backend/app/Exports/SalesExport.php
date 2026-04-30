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

        foreach ($stockOuts as $so) {
            $location = $so->branch_id ? ($so->branch->name ?? '-') : ($so->onlineShop->name ?? '-');
            $csName = $so->inventoryUser->name ?? ($so->user->name ?? '-');
            
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

                $rows[] = [
                    'waktu' => $so->created_at->format('d/m/Y H:i'),
                    'order_no' => $so->receipt_id,
                    'lokasi' => $location,
                    'user' => $csName,
                    'customer' => $so->customer_name ?? '-',
                    'whatsapp' => $so->customer_wa ?? '-',
                    'category' => str_replace('_', ' ', strtoupper($so->category)),
                    'product' => "📦 " . ($so->bundle_description ?: 'Paket Bundling'),
                    'imei' => implode(', ', $allImeis) ?: '-',
                    'qty' => 1,
                    'price' => $totalPrice,
                    'total' => $totalPrice,
                    'payment' => $payment ?: '-',
                    'status' => strtoupper($so->status)
                ];
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
                    
                    $rows[] = [
                        'waktu' => $so->created_at->format('d/m/Y H:i'),
                        'order_no' => $so->receipt_id,
                        'lokasi' => $location,
                        'user' => $csName,
                        'customer' => $so->customer_name ?? '-',
                        'whatsapp' => $so->customer_wa ?? '-',
                        'category' => str_replace('_', ' ', strtoupper($so->category)),
                        'product' => $productName . " [" . $condition . "]" . $notes,
                        'imei' => $item->imei ?? '-',
                        'qty' => 1,
                        'price' => $item->pivot->selling_price ?? 0,
                        'total' => $item->pivot->selling_price ?? 0,
                        'payment' => $payment ?: '-',
                        'status' => strtoupper($so->status)
                    ];
                }

                // Non-HP Items
                foreach ($so->nonHpItems as $item) {
                    $notes = $item->notes ? " (" . $item->notes . ")" : "";
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
                        'price' => $item->price ?? 0,
                        'total' => ($item->price ?? 0) * $item->quantity,
                        'payment' => $payment ?: '-',
                        'status' => strtoupper($so->status)
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
