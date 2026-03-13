Route::get('/', function () {
return view('welcome');
});

Route::get('/n/{receipt_id}', [\App\Http\Controllers\PublicReceiptController::class, 'show'])->name('public.receipt');