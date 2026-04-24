<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class SalesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    protected $branchId;
    protected $onlineShopId;
    protected $date;
    protected $mode;
    protected $user;

    public function __construct($branchId = null, $onlineShopId = null, $date = null, $mode = 'daily', $user = null)
    {
        $this->branchId = $branchId;
        $this->onlineShopId = $onlineShopId;
        $this->date = $date ? \Carbon\Carbon::parse($date) : now();
        $this->mode = $mode;
        $this->user = $user;
    }

    public function title(): string
    {
        return 'Laporan Penjualan';
    }

    public function collection()
    {
        if ($this->mode === 'monthly') {
            $resetTime = $this->date->copy()->startOfMonth()->setTime(5, 0, 0);
        } else {
            $resetTime = $this->date->copy()->setTime(5, 0, 0);
        }

        $query = \App\Models\StockOut::with(['items.product', 'user', 'branch', 'onlineShop', 'paymentMethod'])
            ->where('created_at', '>=', $resetTime)
            ->where('status', '!=', 'cancelled')
            ->whereIn('category', ['penjualan_offline', 'shopee', 'orderan_online', 'penjualan_store', 'bundling']);

        // Application level scoping
        if ($this->user && !$this->user->hasRole('super_admin')) {
            $accessibleBranchIds = $this->user->getAccessibleBranchIds();
            $accessibleOnlineShopIds = $this->user->getAccessibleOnlineShopIds();
            
            if ($this->branchId) {
                if (in_array($this->branchId, $accessibleBranchIds)) {
                     $query->where('branch_id', $this->branchId);
                } else {
                     $query->where('id', 0); // Forbidden
                }
            } elseif ($this->onlineShopId) {
                if (in_array($this->onlineShopId, $accessibleOnlineShopIds)) {
                     $query->where('online_shop_id', $this->onlineShopId);
                } else {
                     $query->where('id', 0); // Forbidden
                }
            } else {
                // All Accessible
                $query->where(function($q) use ($accessibleBranchIds, $accessibleOnlineShopIds) {
                    $q->whereIn('branch_id', $accessibleBranchIds)
                      ->orWhereIn('online_shop_id', $accessibleOnlineShopIds);
                });
            }
        } else {
            // Admin or unauthenticated (shouldn't happen)
            if ($this->branchId) {
                $query->where('branch_id', $this->branchId);
            }
            if ($this->onlineShopId) {
                $query->where('online_shop_id', $this->onlineShopId);
            }
        }

        // Exclude test data
        $query->whereHas('user', function($q) {
            $q->where('name', 'not like', '%ANU%')
              ->where('name', 'not like', '%trial%')
              ->where('name', 'not like', '%testing%')
              ->where('name', 'not like', '%huft%');
        });

        $stockOuts = $query->latest()->get();
        $rows = [];

        foreach ($stockOuts as $so) {
            $location = $so->branch_id ? ($so->branch->name ?? '-') : ($so->onlineShop->name ?? '-');
            
            foreach ($so->items as $item) {
                $rows[] = [
                    'waktu' => $so->created_at->format('d/m/Y H:i'),
                    'lokasi' => $location,
                    'user' => $so->user->name ?? '-',
                    'customer' => $so->customer_name ?? '-',
                    'whatsapp' => $so->customer_wa ?? '-',
                    'category' => str_replace('_', ' ', strtoupper($so->category)),
                    'product' => ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . " " . ($item->ram ?? '') . "/" . ($item->storage ?? ''),
                    'imei' => $item->imei ?? '-',
                    'price' => $so->final_price ?? ($so->selling_price ?? 0),
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
            'Lokasi',
            'User/Admin',
            'Nama Customer',
            'WhatsApp',
            'Kategori',
            'Produk',
            'IMEI/S/N',
            'Harga Jual',
            'Metode Pembayaran',
            'Status'
        ];
    }

    public function map($row): array
    {
        return [
            $row['waktu'],
            $row['lokasi'],
            $row['user'],
            $row['customer'],
            $row['whatsapp'],
            $row['category'],
            $row['product'],
            $row['imei'],
            $row['price'],
            $row['payment'],
            $row['status']
        ];
    }
}
