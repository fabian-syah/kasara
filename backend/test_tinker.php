$logs = App\Models\StockOut::whereHas('items', function($q) {
    $q->withTrashed()->where('imei', '351436774339809');
})->get(['id', 'receipt_id', 'category', 'user_id', 'created_at']);

foreach($logs as $l) {
    echo "[{$l->created_at}] StockOut: {$l->receipt_id} (Cat: {$l->category}) User: {$l->user_id}\n";
}
