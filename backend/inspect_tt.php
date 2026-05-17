<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProductDetail;
use App\Models\TukarTambah;

$id = 11334;
$pd = ProductDetail::find($id);

if (!$pd) {
    echo "ProductDetail with ID {$id} not found.\n";
    
    // Let's search for TukarTambah with receipt_id = TT17May26-011
    $tt = TukarTambah::where('receipt_id', 'TT17May26-011')->first();
    if ($tt) {
        echo "TukarTambah found:\n";
        print_r($tt->toArray());
        
        echo "\nSearching for any ProductDetails linked to this TukarTambah...\n";
        $pds = ProductDetail::where('tukar_tambah_id', $tt->id)->get();
        echo "ProductDetails with tukar_tambah_id = {$tt->id}:\n";
        foreach ($pds as $p) {
            echo "ID: {$p->id} | IMEI: {$p->imei} | Notes: {$p->notes} | status: {$p->status}\n";
        }
    } else {
        echo "TukarTambah TT17May26-011 not found.\n";
    }
    exit;
}

echo "ProductDetail with ID {$id} properties:\n";
print_r($pd->toArray());

if ($pd->tukar_tambah_id) {
    echo "\ntukarTambah relation details:\n";
    $tt = TukarTambah::find($pd->tukar_tambah_id);
    if ($tt) {
        print_r($tt->toArray());
    } else {
        echo "TukarTambah record {$pd->tukar_tambah_id} not found!\n";
    }
} else {
    echo "\ntukar_tambah_id is NULL or not set.\n";
}

// Let's also check if there is any TukarTambah record where outgoing_product_detail_id = 11334
$tts = TukarTambah::where('outgoing_product_detail_id', $id)->get();
echo "\nTukarTambah records where outgoing_product_detail_id = {$id}:\n";
foreach ($tts as $tt) {
    echo "ID: {$tt->id} | Receipt: {$tt->receipt_id} | Customer: {$tt->customer_name}\n";
}
