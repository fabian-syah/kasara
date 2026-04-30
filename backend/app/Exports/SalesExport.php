<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use App\Models\StockOut;

class SalesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
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
        $query = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'branch', 'onlineShop', 'paymentMethod'])
            ->whereBetween('reporting_date', [$this->startDate, $this->endDate])
            ->where('status', '!=', 'cancelled')
            ->whereIn('category', ['penjualan_offline', 'shopee', 'orderan_online', 'penjualan_store', 'bundling', 'sale', 'pos']);

        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }
        if ($this->onlineShopId) {
            $query->where('online_shop_id', $this->onlineShopId);
        }

        // Application level scoping for non-admins
        if ($this->user && !$this->user->hasAnyRole(['super_admin', 'owner', 'analist', 'analis'])) {
            $accessibleBranchIds = $this->user->getAccessibleBranchIds();
            $accessibleOnlineShopIds = $this->user->getAccessibleOnlineShopIds();
            
            $query->where(function($q) use ($accessibleBranchIds, $accessibleOnlineShopIds) {
                $q->whereIn('branch_id', $accessibleBranchIds)
                  ->orWhereIn('online_shop_id', $accessibleOnlineShopIds);
            });
        }

        // Exclude test locations (Branch/Online Shop)
        $excluded = ['trial', 'huft', 'anu', 'test', 'testing'];
        $query->where(function($q) use ($excluded) {
            $q->whereHas('branch', function($sub) use ($excluded) {
                foreach ($excluded as $term) {
                    $sub->where('name', 'not ilike', '%' . $term . '%');
                }
            })->orWhereHas('onlineShop', function($sub) use ($excluded) {
                foreach ($excluded as $term) {
                    $sub->where('name', 'not ilike', '%' . $term . '%');
                }
            })->orWhere(function($sub) {
                $sub->whereNull('branch_id')->whereNull('online_shop_id');
            });
        });

        $stockOuts = $query->latest()->get();
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
