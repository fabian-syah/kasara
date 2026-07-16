<?php

use Illuminate\Support\Facades\Route;
Route::get('/', function () {
return view('welcome');
});

Route::get('/n/{receipt_id}', [\App\Http\Controllers\PublicReceiptController::class, 'show'])->name('public.receipt');
Route::get('/resi-tracking', [\App\Http\Controllers\PublicReceiptController::class, 'proxyTracking'])->name('public.tracking');