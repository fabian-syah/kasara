Route::get('/', function () {
return view('welcome');
});

Route::get('/n/{receipt_id}', [\App\Http\Controllers\PublicReceiptController::class, 'show'])->name('public.receipt');

Route::get('/external-package-tracker', [\App\Http\Controllers\StockOutController::class, 'proxyTracking']);