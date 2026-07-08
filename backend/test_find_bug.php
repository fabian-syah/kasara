<?php

// Save this to a file, e.g. find_bug.php in your root directory, then run: php artisan tinker < find_bug.php

use App\Models\ProductDetail;
use App\Models\StockOut;
use Illuminate\Support\Facades\DB;

echo "Mencari barang yang statusnya 'available' tapi memiliki record penjualan yang valid...\n";

// Find ProductDetails that are 'available'
$buggyItems = ProductDetail::where('status', 'available')
    ->whereHas('stockOuts', function ($query) {
        $query->whereIn('category', ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pindah_cabang', 'keluar'])
              ->where('status', '!=', 'cancelled')
              ->whereNull('deleted_at');
    })
    ->with(['product', 'placement', 'stockOuts' => function($q) {
        $q->whereIn('category', ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pindah_cabang', 'keluar'])
          ->where('status', '!=', 'cancelled')
          ->whereNull('deleted_at');
    }])
    ->get();

if ($buggyItems->count() === 0) {
    echo "Tidak ditemukan barang dengan anomali tersebut.\n";
} else {
    echo "Ditemukan " . $buggyItems->count() . " barang yang aneh:\n";
    foreach ($buggyItems as $item) {
        echo "--------------------------------------------------\n";
        echo "IMEI        : " . $item->imei . "\n";
        echo "Produk      : " . ($item->product ? $item->product->name : 'Unknown') . "\n";
        echo "Lokasi Saat Ini : " . $item->placement_type . " ID: " . $item->placement_id . "\n";
        echo "Update Terakhir : " . $item->updated_at . "\n";
        echo "Record Keluar Terakhir:\n";
        foreach ($item->stockOuts as $so) {
            echo "  - Resi: " . $so->receipt_id . " | Kategori: " . $so->category . " | Tanggal: " . $so->created_at . " | Status SO: " . $so->status . "\n";
            
            // Check pivot
            $pivot = DB::table('stock_out_items')
                ->where('stock_out_id', $so->id)
                ->where('product_detail_id', $item->id)
                ->first();
            if ($pivot) {
                echo "    -> Pivot Status: " . $pivot->status . "\n";
            }
        }
    }
}
echo "--------------------------------------------------\n";
echo "Selesai.\n";
