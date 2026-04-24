<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use App\Models\InventoryLog;
use App\Models\StockOut;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class StockMutationExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
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
        return 'Laporan Mutasi Barang';
    }

    public function collection()
    {
        // Re-use logic from ReportController to get the same data
        $reportController = new \App\Http\Controllers\ReportController();
        $request = new \Illuminate\Http\Request([
            'branch_id' => $this->branchId,
            'online_shop_id' => $this->onlineShopId,
            'date' => $this->date->format('Y-m-d'),
            'mode' => $this->mode
        ]);
        
        if ($this->user) {
            $request->setUserResolver(fn() => $this->user);
        }
        
        $response = $reportController->getStockHistory($request);
        $data = json_decode($response->getContent(), true)['data'] ?? [];
        
        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Produk',
            'Stok Awal',
            'Total Masuk',
            'Masuk TT', 
            'Masuk TU', 
            'Masuk DW', 
            'Masuk RF', 
            'Masuk AB',
            'Stok Terjual',
            'Total Keluar',
            'Keluar TT', 
            'Keluar TU', 
            'Keluar DW',
            'Stok Akhir'
        ];
    }

    public function map($row): array
    {
        return [
            $row['name'],
            $row['initial'],
            $row['in'],
            $row['in_tt'], 
            $row['in_tu'], 
            $row['in_dw'], 
            $row['in_rf'], 
            $row['in_ab'],
            $row['sold'],
            $row['out'],
            $row['out_tt'], 
            $row['out_tu'], 
            $row['out_dw'],
            $row['final']
        ];
    }
}
