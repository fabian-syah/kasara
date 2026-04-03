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

// ... (previous routes)



Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index']);
    // ... other protected routes
});

// Protected routes
// Public Fixer Route (Temporary)
Route::get('/inventory/fix-data', [InventoryController::class, 'fixMergedImeis']);
Route::get('/inventory/fix-logs', [InventoryController::class, 'fixInventoryLogs']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/verify-password', [AuthController::class, 'verifyPassword']);
    Route::get('/user', [AuthController::class, 'me']);

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

    // Direct POST for updates (Fix for 422 issues with file uploads)
    Route::post('/users/{user}', [UserController::class, 'update']);
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

    // Inventory
    Route::get('/inventory/stock-summary', [InventoryController::class, 'stockSummary']);
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/history/in', [InventoryController::class, 'stockInHistory']);
    Route::get('/inventory/history/out', [InventoryController::class, 'stockOutHistory']);
    Route::get('/inventory/history/in/export', [InventoryController::class, 'exportStockInHistory']);
    Route::get('/inventory/history/out/export', [InventoryController::class, 'exportStockOutHistory']);
    Route::get('/inventory/export', [InventoryController::class, 'export']);
    Route::get('/inventory/filter-options', [InventoryController::class, 'getFilterOptions']);

    // DEBUG ROUTE
    Route::get('/debug-stock/{receipt}', function ($receipt) {
        $stockOut = \App\Models\StockOut::with(['destination'])->where('receipt_id', $receipt)->first();
        if (!$stockOut)
            return response()->json(['error' => 'Not found']);
        return response()->json([
            'id' => $stockOut->id,
            'receipt_id' => $stockOut->receipt_id,
            'category' => $stockOut->category,
            'destination_type' => $stockOut->destination_type,
            'destination_id' => $stockOut->destination_id,
            'destination_relation' => $stockOut->destination,
            'morph_map' => \Illuminate\Database\Eloquent\Relations\Relation::morphMap(),
            'user' => auth()->user()
        ]);
    });

    Route::post('/inventory/stock-in', [InventoryController::class, 'stockIn']);
    Route::post('/inventory/{id}', [InventoryController::class, 'update']);
    Route::put('/inventory/{id}', [InventoryController::class, 'update']);
    Route::patch('/inventory/{id}/status', [InventoryController::class, 'updateStatus']);
    Route::get('/inventory/products-lookup', [InventoryController::class, 'getProducts']);
    Route::post('/inventory/account', [InventoryController::class, 'createAccount']);
    Route::post('/inventory/account/{id}/update', [InventoryController::class, 'updateAccount']);
    Route::get('/inventory/my-accounts', [InventoryController::class, 'getMyInventoryUsers']);
    Route::post('/inventory/account/{id}/toggle-pin', [InventoryController::class, 'togglePin']);
    Route::post('/inventory/account/{id}/request-reset', [InventoryController::class, 'requestResetPin']);

    // Inventory Account Photo Approvals
    Route::get('/inventory/accounts/pending-photos', [InventoryController::class, 'pendingPhotos']);
    Route::post('/inventory/account/{id}/approve-photo', [InventoryController::class, 'approvePhoto']);
    Route::post('/inventory/account/{id}/reject-photo', [InventoryController::class, 'rejectPhoto']);

    // Stock Out (Pengeluaran Stok)
    Route::get('/stock-outs', [\App\Http\Controllers\StockOutController::class, 'index']);
    Route::post('/stock-outs', [\App\Http\Controllers\StockOutController::class, 'store']);
    Route::get('/stock-outs/shopee-history', [\App\Http\Controllers\StockOutController::class, 'shopeeHistory']);
    Route::get('/stock-outs/{id}', [\App\Http\Controllers\StockOutController::class, 'show']);
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
    Route::post('/transfers/{id}/confirm', [\App\Http\Controllers\StockOutController::class, 'confirm']);

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

    // Audit
    Route::prefix('audit')->group(function () {
        Route::get('/sales', [\App\Http\Controllers\AuditController::class, 'sales']);
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
    Route::get('/receipts/{id}/share-wa', [\App\Http\Controllers\WhatsAppShareController::class, 'share']);
});
