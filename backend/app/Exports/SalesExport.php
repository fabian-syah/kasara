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
    protected $resetTime;

    public function __construct($branchId = null, $onlineShopId = null)
    {
        $this->branchId = $branchId;
        $this->onlineShopId = $onlineShopId;
        
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $this->resetTime = $logicalNow->copy()->setTime(5, 0, 0);
    }

    public function title(): string
    {
        return 'Laporan Penjualan';
    }

    public function collection()
    {
        $query = \App\Models\StockOut::with(['product', 'user', 'branch', 'onlineShop', 'paymentMethod'])
            ->where('created_at', '>=', $this->resetTime);

        if ($this->branchId) {
            $query->where('placement_type', 'branch')->where('placement_id', $this->branchId);
        }
        if ($this->onlineShopId) {
            $query->where('placement_type', 'online_shop')->where('placement_id', $this->onlineShopId);
        }

        // Exclude test data
        $query->whereHas('user', function($q) {
            $q->where('name', 'not like', '%ANU%')
              ->where('name', 'not like', '%trial%')
              ->where('name', 'not like', '%testing%')
              ->where('name', 'not like', '%huft%');
        });

        return $query->latest()->get();
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
        $location = $row->placement_type === 'branch' 
            ? ($row->branch->name ?? '-') 
            : ($row->onlineShop->name ?? '-');

        return [
            $row->created_at->format('d/m/Y H:i'),
            $location,
            $row->user->name ?? '-',
            $row->customer_name ?? '-',
            $row->customer_whatsapp ?? '-',
            str_replace('_', ' ', strtoupper($row->category)),
            $row->product->name ?? ($row->product_name ?? '-'),
            $row->imei ?? ($row->imei_original ?? '-'),
            $row->final_price ?? $row->price,
            $row->paymentMethod->name ?? '-',
            strtoupper($row->status)
        ];
    }
}
