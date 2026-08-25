<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$logs = App\Models\InventoryLog::where('description', 'like', '%356329105614823%')
    ->orWhere('reference_id', '17637')
    ->get(['id', 'type', 'description', 'reference_id', 'created_at']);

foreach($logs as $l) {
    echo "[{$l->created_at}] [{$l->type}] (Ref: {$l->reference_id}) {$l->description}\n";
}
