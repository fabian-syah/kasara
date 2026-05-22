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
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\TradeInController;
use App\Http\Controllers\UnitExchangeController;
use App\Http\Controllers\FailedTransferController;
use App\Http\Controllers\ReceiptSettingController;

// ... (previous routes)



Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index']);
    // ... other protected routes
});

// Protected routes

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/verify-password', [AuthController::class, 'verifyPassword']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/settings/font-size', [AuthController::class, 'updateFontSize']);
    
    // Admin-only maintenance/debug routes
    Route::middleware(['role:super_admin'])->prefix('admin')->group(function () {
        Route::get('/fix-data', [InventoryController::class, 'fixMergedImeis']);
        Route::get('/fix-logs', [InventoryController::class, 'fixInventoryLogs']);
        Route::get('/debug-pending', [UserController::class, 'debugPendingDump']);
    });

    // Receipt Settings
    Route::get('/receipt-settings', [ReceiptSettingController::class, 'show']);
    Route::post('/receipt-settings', [ReceiptSettingController::class, 'update']);

    // PIN Management
    Route::post('/pin/set', [AuthController::class, 'setPin']);
    Route::post('/pin/update', [AuthController::class, 'updatePin']);
    Route::post('/pin/toggle', [AuthController::class, 'togglePin']);
    Route::prefix('pin')->group(function () {
        Route::post('/verify', [AuthController::class, 'verifyPin']);
        Route::post('/request-reset', [AuthController::class, 'requestResetPin']);
    });

    // ... users, branches, etc ...
    // User Photo Approvals
    Route::get('/users/pending-photos', [UserController::class, 'pendingPhotos']);
    Route::post('/users/{id}/approve-photo', [UserController::class, 'approvePhoto']);
    Route::post('/users/{id}/reject-photo', [UserController::class, 'rejectPhoto']);
    Route::post('/users/{id}/approve-pin-reset', [UserController::class, 'approvePinReset']);

    // Direct POST/PUT for updates (Fix for 422 & 405 issues with file uploads)
    Route::match(['post', 'put'], '/users/{user}', [UserController::class, 'update']);
    Route::apiResource('users', UserController::class)->except(['update']);
    
    Route::post('/branches/{branch}/toggle-status', [BranchController::class, 'toggleStatus']);
    Route::apiResource('branches', BranchController::class);
    Route::apiResource('warehouses', WarehouseController::class);
    Route::post('/warehouses/{warehouse}/toggle-return', [WarehouseController::class, 'toggleReturn']);
    Route::apiResource('online-shops', OnlineShopController::class);

    Route::apiResource('products', ProductController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::get('/distributors/monitoring', [DistributorController::class, 'monitoring']);
    Route::get('/monitoring/online-shop', [InventoryController::class, 'monitoringOnlineShop']);
    Route::get('/monitoring/warehouse', [InventoryController::class, 'monitoringWarehouse']);
    Route::apiResource('distributors', DistributorController::class);
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('product-types', ProductTypeController::class);
    Route::apiResource('questions', QuestionController::class);
    Route::post('/product-prices/lookup', [App\Http\Controllers\ProductPriceController::class, 'lookup']);
    Route::apiResource('product-prices', App\Http\Controllers\ProductPriceController::class);

    // Inventory Static Routes (MUST BE ABOVE DYNAMIC ROUTES)
    Route::get('/inventory/stock-summary', [InventoryController::class, 'stockSummary']);
    Route::get('/inventory/history/in', [InventoryController::class, 'stockInHistory']);
    Route::get('/inventory/history/out', [InventoryController::class, 'stockOutHistory']);
    Route::get('/inventory/history/in/export', [InventoryController::class, 'exportStockInHistory']);
    Route::get('/inventory/history/out/export', [InventoryController::class, 'exportStockOutHistory']);
    Route::get('/inventory/history/export', [InventoryController::class, 'exportStockHistoryCombined']);
    Route::get('/inventory/export', [InventoryController::class, 'export']);
    Route::get('/inventory/filter-options', [InventoryController::class, 'getFilterOptions']);
    Route::get('/inventory/meta-locations', [InventoryController::class, 'getMetaLocations']);
    Route::get('/inventory/products-lookup', [InventoryController::class, 'getProducts']);
    Route::get('/inventory/my-accounts', [InventoryController::class, 'getMyInventoryUsers']);
    Route::get('/inventory/accounts/pending-photos', [InventoryController::class, 'pendingPhotos']);

    Route::post('/inventory/stock-in', [InventoryController::class, 'stockIn']);
    Route::post('/inventory/account', [InventoryController::class, 'createAccount']);
    Route::post('/inventory/account/{id}/update', [InventoryController::class, 'updateAccount']);
    Route::post('/inventory/account/{id}/toggle-pin', [InventoryController::class, 'togglePin']);
    Route::post('/inventory/account/{id}/request-reset', [InventoryController::class, 'requestResetPin']);
    Route::post('/inventory/account/{id}/approve-photo', [InventoryController::class, 'approvePhoto']);
    Route::post('/inventory/account/{id}/reject-photo', [InventoryController::class, 'rejectPhoto']);
    Route::delete('/inventory/account/{id}', [InventoryController::class, 'destroyAccount']);
    Route::delete('/inventory/history/in/{id}/void', [InventoryController::class, 'voidStockIn']);

    // Inventory Dynamic Routes (Must be below static ones)
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::match(['post', 'put'], '/inventory/{id}', [InventoryController::class, 'update']);
    Route::patch('/inventory/{id}/reject-return', [InventoryController::class, 'rejectReturn']);
    Route::patch('/inventory/{id}/status', [InventoryController::class, 'updateStatus']);

    // Stock Out (Pengeluaran Stok)
    Route::get('/stock-outs', [\App\Http\Controllers\StockOutController::class, 'index']);
    Route::post('/stock-outs', [\App\Http\Controllers\StockOutController::class, 'store']);
    Route::get('/stock-outs/check-resi', [\App\Http\Controllers\StockOutController::class, 'checkResi']);
    Route::get('/stock-outs/shopee-history', [\App\Http\Controllers\StockOutController::class, 'shopeeHistory']);
    Route::get('/stock-outs/{id}', [\App\Http\Controllers\StockOutController::class, 'show']);
    Route::post('/stock-outs/{id}/cancel', [\App\Http\Controllers\StockOutController::class, 'cancel']);
    Route::get('/track', [\App\Http\Controllers\StockOutController::class, 'track']);
    Route::post('/trade-ins', [TradeInController::class, 'store']);
    Route::post('/refunds', [\App\Http\Controllers\RefundController::class, 'store']);
    Route::post('/unit-exchanges', [UnitExchangeController::class, 'store']);
    Route::post('/tukar-tambah', [\App\Http\Controllers\TukarTambahController::class, 'store']);
    Route::post('/downgrades', [\App\Http\Controllers\DowngradeController::class, 'store']);


    // Incoming Transfers (Pindah Cabang)
    Route::get('/transfers/pending', [\App\Http\Controllers\StockOutController::class, 'indexIncoming']);
    Route::get('/transfers/history', [\App\Http\Controllers\StockOutController::class, 'historyIncoming']);
    Route::get('/transfers/outgoing', [\App\Http\Controllers\StockOutController::class, 'indexOutgoing']); // NEW
    Route::get('/transfers/asset-values', [\App\Http\Controllers\StockOutController::class, 'getAssetValues']);
    Route::post('/transfers/{id}/confirm', [\App\Http\Controllers\StockOutController::class, 'confirm']);
    Route::post('/transfers/{id}/expedition', [\App\Http\Controllers\StockOutController::class, 'updateExpedition']);
    Route::get('/transfers/track-expedition', [\App\Http\Controllers\StockOutController::class, 'trackExpedition']);

    // Failed Transfers (Gagal Kirim/OTW)
    Route::get('/transfers/failed', [FailedTransferController::class, 'indexFailed']);
    Route::post('/transfers/{id}/confirm-return', [FailedTransferController::class, 'confirmReturn']);

    // System Status
    Route::get('/system-status', [\App\Http\Controllers\SystemStatusController::class, 'index']);
    Route::post('/system-status/block-ip', [\App\Http\Controllers\SystemStatusController::class, 'blockIp']);
    Route::post('/system-status/unblock-ip', [\App\Http\Controllers\SystemStatusController::class, 'unblockIp']);
    Route::post('/system-status/toggle-defender', [\App\Http\Controllers\SystemStatusController::class, 'toggleDefender']);

    // Reports
    Route::get('/reports/brand', [\App\Http\Controllers\ReportController::class, 'getBrandReport']);
    Route::get('/reports/type', [\App\Http\Controllers\ReportController::class, 'getTypeReport']);
    Route::get('/reports/sales', [\App\Http\Controllers\ReportController::class, 'getSalesReport']);
    Route::get('/reports/profit', [\App\Http\Controllers\AuditController::class, 'profit']);
    Route::get('/reports/ranking', [\App\Http\Controllers\ReportController::class, 'getRankingReport']);
    Route::get('/reports/filters', [\App\Http\Controllers\ReportController::class, 'getReportFilters']);
    Route::get('/reports/stock-history', [\App\Http\Controllers\ReportController::class, 'getStockHistory']);
    Route::get('/reports/export-sales', [\App\Http\Controllers\ReportController::class, 'exportSales']);
    Route::get('/reports/export-stock-movement', [\App\Http\Controllers\ReportController::class, 'exportStockMovement']);
    Route::get('/reports/download-history', [\App\Http\Controllers\ReportController::class, 'getDownloadHistory']);

    // Audit
    Route::prefix('audit')->group(function () {
        Route::get('/sales', [\App\Http\Controllers\AuditController::class, 'sales']);
        Route::get('/sales/download-proof', [\App\Http\Controllers\AuditController::class, 'downloadProof']);
        Route::get('/sales/export', [\App\Http\Controllers\AuditController::class, 'exportSales']);
        Route::get('/inventory', [\App\Http\Controllers\AuditController::class, 'inventory']);
        Route::get('/track', [\App\Http\Controllers\AuditController::class, 'track']);
        Route::get('/analysis', [\App\Http\Controllers\AuditController::class, 'analysis']);
        Route::get('/checklist/{stockOutId}', [\App\Http\Controllers\AuditController::class, 'getChecklist']);
        Route::post('/checklist/{stockOutId}', [\App\Http\Controllers\AuditController::class, 'saveChecklist']);

        // Profit
        Route::get('/profit', [\App\Http\Controllers\AuditController::class, 'profit']);
        Route::post('/profit/{stockOutId}', [\App\Http\Controllers\AuditController::class, 'saveProfitData']);
        Route::get('/profit-checklist/{stockOutId}', [\App\Http\Controllers\AuditController::class, 'getProfitChecklist']);
        Route::post('/profit-checklist/{stockOutId}', [\App\Http\Controllers\AuditController::class, 'saveProfitChecklist']);

        // Stock In
        Route::get('/stock-in', [\App\Http\Controllers\AuditController::class, 'stockIn']);
        Route::get('/stock-in-checklist/{stockOutId}', [\App\Http\Controllers\AuditController::class, 'getStockInChecklist']);
        Route::post('/stock-in-checklist/{stockOutId}', [\App\Http\Controllers\AuditController::class, 'saveStockInChecklist']);

        // Stock Out
        Route::get('/stock-out', [\App\Http\Controllers\AuditController::class, 'stockOut']);
        Route::get('/stock-out-checklist/{stockOutId}', [\App\Http\Controllers\AuditController::class, 'getStockOutChecklist']);
        Route::post('/stock-out-checklist/{stockOutId}', [\App\Http\Controllers\AuditController::class, 'saveStockOutChecklist']);
    });


    // Settings
    Route::apiResource('payment-methods', \App\Http\Controllers\PaymentMethodController::class);
    // WhatsApp GDrive Share (Livewire style)
    Route::match(['get', 'post'], '/receipts/{id}/share-wa', [\App\Http\Controllers\WhatsAppShareController::class, 'share']);
});
