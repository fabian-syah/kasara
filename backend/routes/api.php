<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\OnlineShopController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DistributorController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\InventoryController;

// ... (previous routes)



Route::post('/login', [AuthController::class, 'login']);

// Protected routes
// Public Fixer Route (Temporary)
Route::get('/inventory/fix-data', [InventoryController::class, 'fixMergedImeis']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // ... users, branches, etc ...
    Route::apiResource('users', UserController::class);
    Route::apiResource('users', UserController::class);
    Route::post('/branches/{branch}/toggle-return', [BranchController::class, 'toggleReturn']);
    Route::apiResource('branches', BranchController::class);
    Route::apiResource('warehouses', WarehouseController::class);
    Route::post('/warehouses/{warehouse}/toggle-return', [WarehouseController::class, 'toggleReturn']);
    Route::apiResource('online-shops', OnlineShopController::class);

    Route::apiResource('products', ProductController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('distributors', DistributorController::class);
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('product-types', ProductTypeController::class);
    Route::post('/product-prices/lookup', [App\Http\Controllers\ProductPriceController::class, 'lookup']);
    Route::apiResource('product-prices', App\Http\Controllers\ProductPriceController::class);

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/history/in', [InventoryController::class, 'stockInHistory']);
    Route::get('/inventory/history/out', [InventoryController::class, 'stockOutHistory']);
    Route::post('/inventory/stock-in', [InventoryController::class, 'stockIn']);
    Route::put('/inventory/{id}', [InventoryController::class, 'update']);
    Route::patch('/inventory/{id}/status', [InventoryController::class, 'updateStatus']);
    Route::get('/inventory/products-lookup', [InventoryController::class, 'getProducts']);
    Route::post('/inventory/account', [InventoryController::class, 'createAccount']);
    Route::post('/inventory/account/{id}/update', [InventoryController::class, 'updateAccount']);

    // Stock Out (Pengeluaran Stok)
    Route::get('/stock-outs', [\App\Http\Controllers\StockOutController::class, 'index']);
    Route::post('/stock-outs', [\App\Http\Controllers\StockOutController::class, 'store']);
    Route::get('/stock-outs/shopee-history', [\App\Http\Controllers\StockOutController::class, 'shopeeHistory']);
    Route::get('/stock-outs/{id}', [\App\Http\Controllers\StockOutController::class, 'show']);
    Route::get('/track', [\App\Http\Controllers\StockOutController::class, 'track']);

    // Transfer confirmation (Pindah Cabang)
    Route::get('/transfers/pending', [\App\Http\Controllers\TransferController::class, 'pending']);
    Route::post('/transfers/{id}/confirm', [\App\Http\Controllers\TransferController::class, 'confirm']);
    Route::get('/transfers/history', [\App\Http\Controllers\TransferController::class, 'history']);
});
