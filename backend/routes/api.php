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
use App\Http\Controllers\Inventory\StockInController;
use App\Http\Controllers\Inventory\InventoryAccountController;
use App\Http\Controllers\Inventory\InventoryExportController;
use App\Http\Controllers\TradeInController;
use App\Http\Controllers\UnitExchangeController;
use App\Http\Controllers\FailedTransferController;
use App\Http\Controllers\ReceiptSettingController;

// ... (previous routes)

Route::get('/health-check', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbStatus = 'CONNECTED';
    } catch (\Exception $e) {
        $dbStatus = 'DISCONNECTED';
    }

    $uptimeStr = 'Unknown';
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $uptime = @file_get_contents('/proc/uptime');
        if ($uptime) {
            $uptime = explode(' ', $uptime)[0];
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            $uptimeStr = $days . 'd ' . $hours . 'h';
        }
    }

    return response()->json([
        'status' => 'ONLINE',
        'database' => $dbStatus,
        'memory_usage' => round(memory_get_usage(true) / 1048576, 2) . ' MB',
        'uptime' => $uptimeStr,
        'server_time' => now()->format('H:i:s'),
        'server_date' => now()->format('d M Y'),
        'active_personnel' => \App\Models\User::with('branch')
            ->where('last_seen', '>=', now()->subMinutes(5))
            ->limit(5)
            ->get()
            ->map(function ($u) {
                return [
                    'username' => $u->username ?? $u->code_id ?? 'unknown',
                    'name' => $u->name,
                    'branch' => $u->branch ? $u->branch->name : 'HQ',
                    'tz' => 'WIB',
                    'last_seen' => $u->last_seen ? $u->last_seen->diffForHumans() : 'Just now'
                ];
            })
    ]);
});


Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Public storage proxy with CORS headers (for screenshot capture)
Route::get('/storage-proxy/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) {
        return response()->json(['message' => 'File not found'], 404);
    }
    $mime = mime_content_type($fullPath);
    return response()->file($fullPath, [
        'Content-Type' => $mime,
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');

// Return image as base64 JSON (resized for mobile screenshot capture)
Route::get('/storage-base64/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) {
        return response()->json(['error' => 'not found'], 404);
    }
    $mime = mime_content_type($fullPath);
    
    // Resize image to max 400px width to keep base64 small for iOS
    $maxWidth = 400;
    $resized = false;
    
    if (str_starts_with($mime, 'image/') && extension_loaded('gd')) {
        try {
            $info = getimagesize($fullPath);
            if ($info && $info[0] > $maxWidth) {
                $origWidth = $info[0];
                $origHeight = $info[1];
                $newWidth = $maxWidth;
                $newHeight = (int)($origHeight * ($maxWidth / $origWidth));
                
                $source = null;
                switch ($info[2]) {
                    case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($fullPath); break;
                    case IMAGETYPE_PNG: $source = imagecreatefrompng($fullPath); break;
                    case IMAGETYPE_WEBP: $source = imagecreatefromwebp($fullPath); break;
                }
                
                if ($source) {
                    $thumb = imagecreatetruecolor($newWidth, $newHeight);
                    // Preserve transparency for PNG
                    if ($info[2] === IMAGETYPE_PNG) {
                        imagealphablending($thumb, false);
                        imagesavealpha($thumb, true);
                    }
                    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                    
                    ob_start();
                    imagejpeg($thumb, null, 75);
                    $imageData = ob_get_clean();
                    
                    imagedestroy($source);
                    imagedestroy($thumb);
                    
                    $data = base64_encode($imageData);
                    $resized = true;
                    $mime = 'image/jpeg';
                }
            }
        } catch (\Throwable $e) {
            // Fall through to raw file
        }
    }
    
    if (!$resized) {
        $data = base64_encode(file_get_contents($fullPath));
    }
    
    return response()->json(['data' => "data:{$mime};base64,{$data}"], 200, [
        'Access-Control-Allow-Origin' => '*',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index']);
    // ... other protected routes
});

// Protected routes

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/verify-password', [AuthController::class, 'verifyPassword']);
    Route::post('/verify-pin', [AuthController::class, 'verifyPin']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/settings/font-size', [AuthController::class, 'updateFontSize']);
    
    // Admin-only maintenance/debug routes
    Route::middleware(['role:super_admin'])->prefix('admin')->group(function () {
        Route::get('/fix-data', [InventoryController::class, 'fixMergedImeis']);
        Route::get('/fix-logs', [InventoryController::class, 'fixInventoryLogs']);
        Route::get('/debug-pending', [UserController::class, 'debugPendingDump']);
    });

    // Branch League Management (Super Admin only)
    Route::prefix('leagues')->group(function () {
        Route::get('/', [\App\Http\Controllers\BranchLeagueController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\BranchLeagueController::class, 'store']);
        Route::post('/bulk', [\App\Http\Controllers\BranchLeagueController::class, 'bulkAssign']);
        Route::post('/copy', [\App\Http\Controllers\BranchLeagueController::class, 'copyFromPrevious']);
        Route::post('/update-rank', [\App\Http\Controllers\BranchLeagueController::class, 'updateRank']);
        Route::delete('/{id}', [\App\Http\Controllers\BranchLeagueController::class, 'destroy']);
    });

    // Receipt Settings
    Route::get('/receipt-settings', [ReceiptSettingController::class, 'show']);
    Route::post('/receipt-settings', [ReceiptSettingController::class, 'update']);

    // PIN Management (Removed as per user request to replace with password)

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
    Route::get('/inventory/stock-analysis', [InventoryController::class, 'stockAnalysis']);
    Route::get('/inventory/stock-analysis/filters', [InventoryController::class, 'stockAnalysisFilters']);
    Route::get('/inventory/sold-analysis', [InventoryController::class, 'soldAnalysis']);
    Route::get('/inventory/sold-analysis/filters', [InventoryController::class, 'soldAnalysisFilters']);
    Route::get('/inventory/stock-summary', [InventoryController::class, 'stockSummary']);
    Route::get('/inventory/opname-bulk', [InventoryController::class, 'opnameBulk']);
    Route::get('/inventory/history/in', [StockInController::class, 'stockInHistory']);
    Route::get('/inventory/history/out', [StockInController::class, 'stockOutHistory']);
    Route::get('/inventory/history/in/export', [StockInController::class, 'exportStockInHistory'])->middleware('throttle:exports');
    Route::get('/inventory/history/out/export', [StockInController::class, 'exportStockOutHistory'])->middleware('throttle:exports');
    Route::get('/inventory/history/export', [StockInController::class, 'exportStockHistoryCombined'])->middleware('throttle:exports');
    Route::get('/inventory/failed-inputs', [StockInController::class, 'failedInputHistory']);
    Route::get('/inventory/export', [InventoryExportController::class, 'export'])->middleware('throttle:exports');
    Route::get('/inventory/filter-options', [InventoryController::class, 'getFilterOptions']);
    Route::get('/inventory/meta-locations', [InventoryController::class, 'getMetaLocations']);
    Route::get('/inventory/products-lookup', [InventoryController::class, 'getProducts']);
    Route::get('/inventory/my-accounts', [InventoryAccountController::class, 'getMyInventoryUsers']);
    Route::get('/inventory/accounts/pending-photos', [InventoryController::class, 'pendingPhotos']);

    Route::post('/inventory/stock-in', [StockInController::class, 'stockIn']);
    Route::post('/inventory/account', [InventoryAccountController::class, 'createAccount']);
    Route::post('/inventory/account/{id}/update', [InventoryAccountController::class, 'updateAccount']);
    Route::post('/inventory/account/{id}/approve-photo', [InventoryController::class, 'approvePhoto']);
    Route::post('/inventory/account/{id}/reject-photo', [InventoryController::class, 'rejectPhoto']);
    Route::delete('/inventory/account/{id}', [InventoryAccountController::class, 'destroyAccount']);
    Route::delete('/inventory/history/in/{id}/void', [StockInController::class, 'voidStockIn']);

    // Inventory Dynamic Routes (Must be below static ones)
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::match(['post', 'put'], '/inventory/{id}', [InventoryController::class, 'update']);
    Route::patch('/inventory/{id}/reject-return', [InventoryController::class, 'rejectReturn']);
    Route::patch('/inventory/{id}/status', [InventoryController::class, 'updateStatus']);

    // Stock Out (Pengeluaran Stok)
    Route::get('/stock-outs', [\App\Http\Controllers\StockOutController::class, 'index']);
    Route::post('/stock-outs', [\App\Http\Controllers\StockOutController::class, 'store']);
    Route::get('/stock-outs/check-resi', [\App\Http\Controllers\StockOutController::class, 'checkResi']);
    Route::get('/stock-outs/active-dps', [\App\Http\Controllers\StockOutController::class, 'getActiveDps']);
    Route::get('/stock-outs/shopee-history', [\App\Http\Controllers\StockOutController::class, 'shopeeHistory']);
    Route::get('/stock-outs/{id}', [\App\Http\Controllers\StockOutController::class, 'show']);
    Route::post('/stock-outs/{id}/cancel', [\App\Http\Controllers\StockOutController::class, 'cancel']);
    Route::get('/track', [\App\Http\Controllers\StockOutController::class, 'track']);
    Route::post('/trade-ins', [TradeInController::class, 'store']);
    Route::post('/refunds', [\App\Http\Controllers\RefundController::class, 'store']);
    Route::post('/dp-refunds', [\App\Http\Controllers\DpRefundController::class, 'store']);
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
    
    // Security Scan
    Route::get('/security-checks/history', [\App\Http\Controllers\SecurityCheckController::class, 'history']);
    Route::post('/security-checks', [\App\Http\Controllers\SecurityCheckController::class, 'store']);
    Route::delete('/security-checks/{id}', [\App\Http\Controllers\SecurityCheckController::class, 'destroy']);

    // Failed Transfers (Gagal Kirim/OTW)
    Route::get('/transfers/failed', [FailedTransferController::class, 'indexFailed']);
    Route::post('/transfers/{id}/confirm-return', [FailedTransferController::class, 'confirmReturn']);

    // System Status
    Route::get('/system-status', [\App\Http\Controllers\SystemStatusController::class, 'index']);
    Route::post('/system-status/block-ip', [\App\Http\Controllers\SystemStatusController::class, 'blockIp'])->middleware('throttle:sensitive');
    Route::post('/system-status/unblock-ip', [\App\Http\Controllers\SystemStatusController::class, 'unblockIp'])->middleware('throttle:sensitive');
    Route::post('/system-status/toggle-defender', [\App\Http\Controllers\SystemStatusController::class, 'toggleDefender'])->middleware('throttle:sensitive');
    Route::post('/system-status/reset-integrity', [\App\Http\Controllers\SystemStatusController::class, 'resetIntegrityBaseline'])->middleware('throttle:sensitive');
    Route::get('/system-status/backup-info', [\App\Http\Controllers\SystemStatusController::class, 'backupInfo']);
    Route::get('/system-status/backup-download', [\App\Http\Controllers\SystemStatusController::class, 'backupDatabase'])->middleware('throttle:exports');
    Route::get('/system-status/logs', [\App\Http\Controllers\SystemStatusController::class, 'logFiles']);
    Route::get('/system-status/logs/view', [\App\Http\Controllers\SystemStatusController::class, 'logView']);
    Route::get('/system-status/cleanup-info', [\App\Http\Controllers\SystemStatusController::class, 'cleanupInfo']);
    Route::post('/system-status/cleanup', [\App\Http\Controllers\SystemStatusController::class, 'cleanupExecute'])->middleware('throttle:sensitive');

    // Reports
    Route::get('/reports/brand', [\App\Http\Controllers\ReportController::class, 'getBrandReport']);
    Route::get('/reports/type', [\App\Http\Controllers\ReportController::class, 'getTypeReport']);
    Route::get('/reports/sales', [\App\Http\Controllers\ReportController::class, 'getSalesReport']);
    Route::get('/reports/profit', [\App\Http\Controllers\AuditController::class, 'profit']);
    Route::get('/reports/ranking', [\App\Http\Controllers\ReportController::class, 'getRankingReport']);
    Route::get('/reports/ranking/export-excel', [\App\Http\Controllers\ReportController::class, 'exportRankingExcel']);
    Route::get('/reports/filters', [\App\Http\Controllers\ReportController::class, 'getReportFilters']);
    Route::get('/reports/stock-history', [\App\Http\Controllers\ReportController::class, 'getStockHistory']);
    Route::get('/reports/export-sales', [\App\Http\Controllers\ReportController::class, 'exportSales'])->middleware('throttle:exports');
    Route::get('/reports/export-stock-movement', [\App\Http\Controllers\ReportController::class, 'exportStockMovement'])->middleware('throttle:exports');
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

    // Balancing (Super Admin only)
    // Balancing (Super Admin only)
    Route::prefix('balancing')->group(function () {
        Route::get('/branches', function () {
            $branches = \App\Models\Branch::where('is_active', true)
                ->where('type', 'physical')
                ->orderBy('name')
                ->get(['id', 'name', 'address', 'timezone']);
            return response()->json(['data' => $branches]);
        });

        Route::get('/branch-users', function (\Illuminate\Http\Request $request) {
            $branchId = $request->query('branch_id');
            if (!$branchId) return response()->json(['data' => []]);
            
            $branchUserIds = \App\Models\User::where('branch_id', $branchId)->pluck('id');
            
            $users = \App\Models\User::where(function($q) use ($branchId, $branchUserIds) {
                    $q->where('branch_id', $branchId)
                      ->orWhereIn('created_by', $branchUserIds);
                })
                ->where('is_active', true)
                ->select('id', 'name', 'username')
                ->orderBy('name')
                ->get();
            return response()->json(['data' => $users]);
        });

        Route::get('/customers', function (\Illuminate\Http\Request $request) {
            $search = $request->query('search');
            $branchId = $request->query('branch_id');
            $date = $request->query('date');
            
            $query = \App\Models\StockOut::whereNotNull('customer_name')
                ->where('customer_name', '!=', '');
                
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
            
            if ($date) {
                $query->whereDate('reporting_date', $date);
            }

            if ($search && strlen($search) >= 2) {
                $query->where(function($q) use ($search) {
                    $q->where('customer_name', 'ilike', "%{$search}%")
                      ->orWhere('customer_phone', 'ilike', "%{$search}%");
                });
            }

            $transactions = $query->with(['items.product'])
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($trx) {
                    return [
                        'customer_name' => $trx->customer_name,
                        'customer_phone' => $trx->customer_phone,
                        'receipt_id' => $trx->receipt_id ?? $trx->invoice_number,
                        'total_amount' => $trx->selling_price ?? $trx->total_amount,
                        'items' => $trx->items->map(function ($item) {
                            return $item->product ? $item->product->name : 'Item';
                        })->toArray(),
                        'time' => $trx->created_at->format('H:i'),
                        'category' => $trx->category,
                    ];
                });
                
            $grouped = $transactions->groupBy('customer_name')->map(function ($trxs, $name) {
                return [
                    'name' => $name,
                    'phone' => $trxs->first()['customer_phone'],
                    'transactions' => $trxs
                ];
            })->values();

            return response()->json(['data' => $grouped]);
        });

        Route::get('/payment-methods', function () {
            $methods = \App\Models\PaymentMethod::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']); // Only select id and name to avoid SQL errors on missing columns
            return response()->json(['data' => $methods]);
        });

        Route::post('/payment-method', function (\Illuminate\Http\Request $request) {
            $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'date' => 'required|date',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'customer_service_id' => 'required|exists:users,id',
                'notes' => 'required|string',
                'photo' => 'nullable|image|max:20480',
                'payment_methods' => 'required|array|min:1',
                'payment_methods.*.method_id' => 'required|exists:payment_methods,id',
                'payment_methods.*.amount' => 'required|numeric',
                'password' => 'required|string',
            ]);

            $user = \Illuminate\Support\Facades\Auth::user();
            if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
                return response()->json(['success' => false, 'message' => 'Password salah.'], 403);
            }

            try {
                \Illuminate\Support\Facades\DB::beginTransaction();

                $photoPath = $request->file('photo')->store('balancing', 'public');
                $totalAmount = collect($request->payment_methods)->sum('amount');
                
                $stockOut = new \App\Models\StockOut();
                $branch = \App\Models\Branch::find($request->branch_id);
                $branchCode = $branch->code ? substr(strtoupper(str_replace(' ', '', $branch->code)), 0, 3) : 'XX';
                $count = \App\Models\StockOut::whereDate('created_at', today())->count() + 1;
                $stockOut->receipt_id = "BAL-{$branchCode}-" . date('ymd') . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
                $stockOut->branch_id = $request->branch_id;
                $stockOut->user_id = $user->id; // Using user_id instead of creator_id
                $stockOut->balancing_cs_user_id = $request->customer_service_id; // Using the dedicated column
                $stockOut->inventory_user_id = $request->customer_service_id;
                if ($request->customer_service_id) {
                    $csUser = \App\Models\User::find($request->customer_service_id);
                    $stockOut->sales_account = $csUser ? $csUser->name : null;
                }
                $stockOut->customer_name = $request->customer_name;
                $stockOut->customer_phone = $request->customer_phone;
                $stockOut->customer_wa = $request->customer_phone;
                
                $stockOut->category = 'balancing'; 
                $stockOut->sub_category = 'balancing_metode_pembayaran';
                $stockOut->status = 'completed';
                $stockOut->selling_price = $totalAmount; // Using selling_price instead of total_amount
                $stockOut->reporting_date = $request->date; 
                $stockOut->notes = $request->notes;
                $stockOut->payment_proof_image = $photoPath; // Assuming proof_image or payment_proof_image. StockOut has both, let's use payment_proof_image
                $processedSplits = collect($request->payment_methods)->map(function ($pm) {
                    return [
                        'payment_method_id' => $pm['method_id'],
                        'amount' => $pm['amount']
                    ];
                })->toArray();
                
                $stockOut->split_payments = $processedSplits;
                $stockOut->payment_method_id = $processedSplits[0]['payment_method_id'] ?? null;
                
                $stockOut->save();

                \Illuminate\Support\Facades\DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Balancing metode pembayaran berhasil disimpan.',
                    'data' => $stockOut->load('paymentMethod')
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                \Illuminate\Support\Facades\Log::error('Balancing Payment Error: ' . $e->getMessage());
                return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
            }
        });

        // Inventory for a specific branch (for Balancing Penjualan Terlewat)
        Route::get('/branch-inventory', function (\Illuminate\Http\Request $request) {
            $branchId = $request->query('branch_id');
            if (!$branchId) return response()->json(['data' => []]);

            // HP items (product_details) placed at this branch
            $hpItems = \App\Models\ProductDetail::where('status', 'available')
                ->where('placement_type', 'branch')
                ->where('placement_id', $branchId)
                ->with(['product.brandRelation', 'distributor'])
                ->get()
                ->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'type' => 'hp',
                        'product' => [
                            'name' => $detail->product?->name ?? 'Unknown',
                            'brand' => $detail->product?->brand ?? '-',
                        ],
                        'name' => $detail->product?->name ?? 'Unknown',
                        'imei' => $detail->imei,
                        'ram' => $detail->ram,
                        'storage' => $detail->storage,
                        'condition' => $detail->condition ?? 'new',
                        'selling_price' => $detail->selling_price ?? 0,
                        'price' => $detail->selling_price ?? 0,
                        'distributor' => [
                            'name' => $detail->distributor?->name ?? null,
                        ],
                    ];
                });

            // Non-HP items (inventory) at this branch
            $nonHpItems = \App\Models\Inventory::where('placement_type', 'branch')
                ->where('placement_id', $branchId)
                ->where('quantity', '>', 0)
                ->with(['product.brandRelation'])
                ->get()
                ->map(function ($inv) {
                    return [
                        'id' => $inv->id,
                        'type' => 'non_hp',
                        'is_non_hp' => true,
                        'product_id' => $inv->product_id,
                        'product' => [
                            'name' => $inv->product?->name ?? 'Unknown',
                            'brand' => $inv->product?->brand ?? '-',
                        ],
                        'name' => $inv->product?->name ?? 'Unknown',
                        'imei' => null,
                        'ram' => null,
                        'storage' => null,
                        'condition' => 'new',
                        'selling_price' => $inv->product?->selling_price ?? $inv->product?->price ?? 0,
                        'price' => $inv->product?->selling_price ?? $inv->product?->price ?? 0,
                        'stock' => $inv->quantity,
                        'quantity' => $inv->quantity,
                        'distributor' => [
                            'name' => $inv->distributor?->name ?? null,
                        ],
                    ];
                });

            return response()->json(['data' => $hpItems->concat($nonHpItems)->values()]);
        });

        // Store Missed Sale Balancing
        Route::post('/missed-sale', function (\Illuminate\Http\Request $request) {
            $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'reporting_date' => 'required|date',
                'password' => 'required|string',
                'customer_name' => 'nullable|string|max:255',
                'customer_wa' => 'nullable|string|max:50',
                'customer_service_id' => 'nullable|exists:users,id',
                'notes' => 'nullable|string',
                'photo' => 'nullable|image|max:20480',
                'selling_price' => 'required|numeric|min:0',
                'payment_method_id' => 'nullable|exists:payment_methods,id',
                'split_payments' => 'nullable|string', // JSON string
                'product_detail_ids' => 'nullable|array',
                'product_detail_ids.*' => 'exists:product_details,id',
                'non_hp_items' => 'nullable|array',
                'non_hp_items.*.product_id' => 'required|exists:products,id',
                'non_hp_items.*.quantity' => 'required|integer|min:1',
                'non_hp_items.*.selling_price' => 'nullable|numeric|min:0',
                'is_bundle' => 'nullable',
                'bundle_description' => 'nullable|string',
                'global_discount_value' => 'nullable|numeric|min:0',
                'global_discount_type' => 'nullable|string|in:fixed,percentage',
                'total_discount' => 'nullable|numeric|min:0',
                'hp_items_meta' => 'nullable|string', // JSON string
            ]);

            $user = \Illuminate\Support\Facades\Auth::user();
            if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
                return response()->json(['success' => false, 'message' => 'Password salah.'], 403);
            }

            try {
                \Illuminate\Support\Facades\DB::beginTransaction();

                $branchId = $request->branch_id;

                // Process HP items — verify availability and mark as sold
                $productDetails = collect();
                if ($request->product_detail_ids && count($request->product_detail_ids) > 0) {
                    $uniqueIds = array_unique($request->product_detail_ids);
                    $productDetails = \App\Models\ProductDetail::whereIn('id', $uniqueIds)
                        ->where('status', 'available')
                        ->where('placement_type', 'branch')
                        ->where('placement_id', $branchId)
                        ->lockForUpdate()
                        ->get();

                    if ($productDetails->count() !== count($uniqueIds)) {
                        throw new \Exception('Beberapa barang HP sudah tidak tersedia atau sudah keluar stok.');
                    }
                }

                // Process Non-HP items — deduct inventory
                $nonHpDistMap = [];
                if ($request->non_hp_items) {
                    foreach ($request->non_hp_items as $item) {
                        $product = \App\Models\Product::findOrFail($item['product_id']);

                        $invQuery = \App\Models\Inventory::where('product_id', $item['product_id'])
                            ->where('placement_type', 'branch')
                            ->where('placement_id', $branchId);

                        $inventories = $invQuery->where('quantity', '>', 0)->orderBy('quantity', 'asc')->get();
                        $totalAvailable = $inventories->sum('quantity');

                        if ($totalAvailable < $item['quantity']) {
                            throw new \Exception("Stok tidak cukup untuk produk: {$product->name}. Tersedia: $totalAvailable");
                        }

                        $remainingToDeduct = $item['quantity'];
                        foreach ($inventories as $inventory) {
                            if ($remainingToDeduct <= 0) break;
                            $deductAmount = min($inventory->quantity, $remainingToDeduct);
                            $inventory->decrement('quantity', $deductAmount);
                            $remainingToDeduct -= $deductAmount;

                            if (!isset($nonHpDistMap[$item['product_id']])) {
                                $nonHpDistMap[$item['product_id']] = $inventory->distributor_id;
                            }

                            \App\Models\InventoryLog::create([
                                'product_id' => $item['product_id'],
                                'type' => 'out',
                                'quantity' => $deductAmount,
                                'balance_after' => $inventory->quantity,
                                'description' => "BALANCING PENJUALAN TERLEWAT",
                                'reference_id' => 'BMS-' . time(),
                                'user_id' => $user->id,
                                'distributor_id' => $inventory->distributor_id,
                                'branch_id' => $branchId,
                                'reporting_date' => $request->reporting_date,
                            ]);
                        }
                    }
                }

                // Handle photo upload
                $photoPath = null;
                if ($request->hasFile('photo')) {
                    $photoPath = $request->file('photo')->store('balancing', 'public');
                }

                // Generate receipt ID
                $branch = \App\Models\Branch::find($branchId);
                $branchCode = $branch->code ? substr(strtoupper(str_replace(' ', '', $branch->code)), 0, 3) : 'XX';
                $count = \App\Models\StockOut::whereDate('created_at', today())->count() + 1;
                $receiptId = "BMS-{$branchCode}-" . date('ymd') . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);

                // Parse split_payments
                $splitPayments = null;
                if ($request->split_payments) {
                    $splitPayments = is_string($request->split_payments) ? json_decode($request->split_payments, true) : $request->split_payments;
                }

                // Parse hp_items_meta
                $hpItemsMeta = null;
                if ($request->hp_items_meta) {
                    $hpItemsMeta = is_string($request->hp_items_meta) ? json_decode($request->hp_items_meta, true) : $request->hp_items_meta;
                }

                // Create StockOut record
                $stockOut = new \App\Models\StockOut();
                $stockOut->receipt_id = $receiptId;
                $stockOut->branch_id = $branchId;
                $stockOut->user_id = $user->id;
                $stockOut->balancing_cs_user_id = $request->customer_service_id;
                $stockOut->inventory_user_id = $request->customer_service_id;
                if ($request->customer_service_id) {
                    $csUser = \App\Models\User::find($request->customer_service_id);
                    $stockOut->sales_account = $csUser ? $csUser->name : null;
                }
                $stockOut->customer_name = $request->customer_name;
                $stockOut->customer_wa = $request->customer_wa;
                $stockOut->category = 'balancing';
                $stockOut->sub_category = 'balancing_penjualan_terlewat';
                $stockOut->status = 'completed';
                $stockOut->selling_price = $request->selling_price;
                $stockOut->reporting_date = $request->reporting_date;
                $stockOut->notes = $request->notes;
                $stockOut->payment_proof_image = $photoPath;
                $stockOut->payment_method_id = $request->payment_method_id;
                $stockOut->split_payments = $splitPayments;
                $stockOut->is_bundle = filter_var($request->is_bundle, FILTER_VALIDATE_BOOLEAN);
                $stockOut->bundle_description = $request->bundle_description;
                $stockOut->global_discount_value = $request->global_discount_value ?? 0;
                $stockOut->global_discount_type = $request->global_discount_type ?? 'fixed';
                $stockOut->total_discount = $request->total_discount ?? 0;
                $stockOut->save();

                // Attach HP items
                foreach ($productDetails as $detail) {
                    $meta = $hpItemsMeta[$detail->id] ?? null;
                    $finalPrice = $meta['selling_price'] ?? $detail->selling_price;

                    $stockOut->items()->attach($detail->id, [
                        'selling_price' => $finalPrice,
                        'item_discount' => $meta['item_discount'] ?? 0,
                        'distributed_discount' => $meta['distributed_discount'] ?? 0,
                        'distributor_id' => $detail->distributor_id,
                        'notes' => $meta['bundle_name'] ?? null,
                        'snapshot_product_id' => $detail->product_id,
                        'snapshot_product_name' => $detail->product ? $detail->product->name : null,
                    ]);

                    // Mark HP item as sold
                    $detail->update(['status' => 'sold']);

                    // Log HP movement
                    \App\Models\InventoryLog::create([
                        'product_id' => $detail->product_id,
                        'type' => 'out',
                        'quantity' => 1,
                        'balance_after' => 0,
                        'description' => "BALANCING PENJUALAN TERLEWAT (" . ($detail->imei ?? '-') . ")",
                        'reference_id' => $receiptId,
                        'user_id' => $user->id,
                        'distributor_id' => $detail->distributor_id,
                        'branch_id' => $branchId,
                        'reporting_date' => $request->reporting_date,
                    ]);
                }

                // Create Non-HP detail records
                if ($request->non_hp_items) {
                    foreach ($request->non_hp_items as $item) {
                        $prod = \App\Models\Product::find($item['product_id']);
                        if ($prod) {
                            $distId = $nonHpDistMap[$item['product_id']] ?? $prod->distributor_id ?? null;
                            \App\Models\StockOutNonHpItem::create([
                                'stock_out_id' => $stockOut->id,
                                'product_id' => $item['product_id'],
                                'quantity' => $item['quantity'],
                                'selling_price' => isset($item['selling_price']) && is_numeric($item['selling_price']) ? floatval($item['selling_price']) : floatval($prod->price ?? 0),
                                'item_discount' => $item['item_discount'] ?? 0,
                                'distributed_discount' => $item['distributed_discount'] ?? 0,
                                'received_quantity' => $item['quantity'],
                                'returned_quantity' => 0,
                                'distributor_id' => $distId,
                                'notes' => $item['bundle_name'] ?? null,
                            ]);
                        }
                    }
                }

                \Illuminate\Support\Facades\DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Balancing penjualan terlewat berhasil disimpan.',
                    'data' => $stockOut->load(['items.product', 'nonHpDetails.product', 'paymentMethod', 'branch'])
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                \Illuminate\Support\Facades\Log::error('Balancing Missed Sale Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
            }
        });

        Route::post('/{id}/cancel', function (\Illuminate\Http\Request $request, $id) {
            $request->validate(['password' => 'required|string', 'reason' => 'required|string']);
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
                return response()->json(['success' => false, 'message' => 'Password salah.'], 403);
            }
            $stockOut = \App\Models\StockOut::where('category', 'balancing')->findOrFail($id);
            if ($stockOut->status === 'cancelled') {
                return response()->json(['success' => false, 'message' => 'Transaksi sudah dibatalkan.'], 422);
            }
            $stockOut->status = 'cancelled';
            $stockOut->notes .= "\n[DIBATALKAN: {$request->reason}]";
            $stockOut->save();
            return response()->json(['success' => true, 'message' => 'Balancing berhasil dibatalkan.']);
        });
    });

    // WhatsApp GDrive Share (Livewire style)
    Route::match(['get', 'post'], '/receipts/{id}/share-wa', [\App\Http\Controllers\WhatsAppShareController::class, 'share']);
});

