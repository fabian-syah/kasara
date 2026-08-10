<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $type = 'failed';
    $query = \App\Models\StockOut::with(['items.product.brandRelation', 'items.distributor', 'nonHpItems.product.brandRelation', 'nonHpItems.distributor', 'user.branch', 'user.warehouse', 'user.onlineShop', 'inventoryUser.branch', 'inventoryUser.warehouse', 'inventoryUser.onlineShop', 'destinationBranch', 'destination', 'confirmedBy', 'branch', 'onlineShop', 'warehouse'])
        ->where('category', 'pindah_cabang');

    $query->where(function ($sub) {
        $sub->whereHas('items', function ($q) {
            $q->whereIn('stock_out_items.status', ['rejected', 'returned']);
        })
        ->orWhereHas('nonHpItems', function ($q) {
            $q->where('received_quantity', '<', \Illuminate\Support\Facades\DB::raw('quantity'));
        });
    });

    $data = $query->latest()->paginate(15);
    echo "SUCCESS, found " . $data->count() . " rows in paginator\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
