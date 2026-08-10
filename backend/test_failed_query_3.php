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

    $transfers = $query->latest()->paginate(15);
    
    // Enrich Non-HP Items (exact copy from historyIncoming)
    foreach ($transfers->items() as $transfer) {
        if ($transfer->non_hp_items) {
            $nonHpItems = is_string($transfer->non_hp_items) ? json_decode($transfer->non_hp_items, true) : $transfer->non_hp_items;
            $pIds = array_column($nonHpItems, 'product_id');
            if (!empty($pIds)) {
                $products = \App\Models\Product::whereIn('id', $pIds)->pluck('name', 'id');
                foreach ($nonHpItems as &$item) {
                    $item['product_name'] = $products[$item['product_id']] ?? 'Unknown';
                }
            }
            $transfer->non_hp_items = $nonHpItems;
        }
    }
    
    echo "SUCCESS, pagination and enrichment worked!\n";
} catch (\Exception $e) {
    echo "Exception ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} catch (\TypeError $e) {
    echo "TypeError ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} catch (\Error $e) {
    echo "Fatal ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
