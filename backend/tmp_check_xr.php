<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$xrs = \App\Models\ProductDetail::whereHas('product', function($q) { $q->where('name', 'like', '%XR%'); })
    ->where('created_at', '>=', \Carbon\Carbon::today())
    ->get();

foreach($xrs as $pd) {
    echo "ProductDetail ID: {$pd->id} | Name: {$pd->product->name} | Storage: {$pd->storage}\n";
    echo "  Notes: {$pd->notes} | Supplier: {$pd->supplier_name}\n";
    
    // Check Logs
    $logs = \App\Models\InventoryLog::where('reference_id', $pd->id)->orWhere('product_id', $pd->product_id)->where('created_at', '>=', \Carbon\Carbon::today())->get();
    echo "  [LOGS]\n";
    foreach($logs as $l) {
        // filter out other PD's logs
        if ($l->reference_id !== $pd->id && $l->reference_id !== null && !str_contains($l->description, 'XR')) continue;
        echo "    ID: {$l->id} | Desc: {$l->description} | Qty: {$l->quantity} | Ref ID: {$l->reference_id} | Type: {$l->type} | Branch: {$l->branch_id}\n";
    }

    // Check StockOuts
    $outs = \App\Models\StockOut::whereHas('items', function($q) use ($pd) { $q->where('product_details.id', $pd->id); })->get();
    echo "  [STOCKOUTS]\n";
    foreach($outs as $o) {
        echo "    ID: {$o->id} | Cat: {$o->category} | Desc: {$o->notes} | Branch: {$o->branch_id} | Created: {$o->created_at}\n";
    }

    echo "---------------------------\n";
}
