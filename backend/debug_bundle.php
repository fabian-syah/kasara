<?php
$so = \App\Models\StockOut::where('receipt_id', 'O30APR-XB5')->first();
if ($so) {
    echo "HP Items:\n";
    foreach ($so->items as $i) {
        echo $i->productDetail->product->name . " - Notes: " . $i->pivot->notes . "\n";
    }
    echo "Non-HP Items:\n";
    foreach ($so->nonHpItems as $i) {
        echo $i->product->name . " - Notes: " . $i->notes . "\n";
    }
} else {
    echo "Not found";
}
