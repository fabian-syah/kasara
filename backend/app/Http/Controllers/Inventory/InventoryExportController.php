<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\ProductDetail;
use App\Utils\SimpleXLSXGen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryExportController extends Controller
{
    public function export(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        // --- PREPARE DATA HP ---
        $hpQuery = ProductDetail::with(['product', 'distributor', 'user', 'placement']);
        $this->applyInventoryFilters($hpQuery, $request, 'hp');
        $hpItems = $hpQuery->join('products', 'product_details.product_id', '=', 'products.id')
            ->orderBy('products.brand')
            ->orderBy('products.name')
            ->select('product_details.*')
            ->get();

        $hpSheet = [['No', 'Tanggal Masuk', 'Jam Masuk', 'Sumber Masuk', 'Merek', 'Produk', 'Kapasitas', 'Kondisi', 'IMEI', 'Lokasi', 'Distributor', 'Harga Jual', 'Status', 'Akun Inventory', 'Catatan']];
        $totalHpPrice = 0;
        foreach ($hpItems as $idx => $item) {
            $price = $item->selling_price !== null ? (float)$item->selling_price : 0.0;
            $totalHpPrice += $price;

            $source = 'Masuk Manual';
            if ($item->trade_in_id) {
                $source = 'Angkat Barang';
            } elseif ($item->tukar_tambah_id) {
                $source = 'Tukar Tambah';
            } elseif ($item->refund_id) {
                $source = 'Refund';
            } elseif ($item->unit_exchange_id) {
                $source = 'Tukar Unit';
            } elseif ($item->downgrade_id) {
                $source = 'Downgrade';
            }

            $hpSheet[] = [
                $idx + 1,
                $item->created_at ? $item->created_at->format('d/m/Y') : '-',
                $item->created_at ? $item->created_at->format('H:i') : '-',
                $source,
                $item->product->brand ?? '-',
                $item->product->name ?? '-',
                implode('/', array_filter([$item->ram, $item->storage])),
                $item->condition === 'new' ? 'Baru' : ($item->condition === 'ex_ibox' ? 'Ex iBox' : 'Bekas'),
                str_replace("'", "", $item->imei ?? '-'),
                $item->placement ? $item->placement->name : ($item->placement_type . ' #' . $item->placement_id),
                $item->distributor?->name ?? ($item->supplier_name ?? '-'),
                $price,
                strtoupper($item->status),
                $item->user->name ?? '-',
                $item->notes ?? '-',
            ];
        }
        $hpSheet[] = ['TOTAL', '', '', '', '', '', '', '', '', '', '', $totalHpPrice, '', '', ''];

        // --- PREPARE DATA NON-HP ---
        $nonHpQuery = Inventory::with(['product', 'user', 'user.distributor', 'distributor', 'placement', 'latestLog', 'latestLog.distributor']);
        $this->applyInventoryFilters($nonHpQuery, $request, 'non-hp');
        $nonHpItems = $nonHpQuery->join('products', 'inventories.product_id', '=', 'products.id')
            ->orderBy('products.brand')
            ->orderBy('products.name')
            ->select('inventories.*')
            ->get();

        $nonHpSheet = [['No', 'Tanggal Masuk', 'Jam Masuk', 'Sumber Masuk', 'Merek', 'Produk', 'Lokasi', 'Stok', 'Distributor / Supplier', 'Harga Jual', 'Akun Inventory', 'Catatan']];
        $totalNonHpQty = 0;
        $totalNonHpPrice = 0;
        foreach ($nonHpItems as $idx => $item) {
            $stok = $item->quantity ?? 0;
            $totalNonHpQty += $stok;

            $distName = null;
            if ($item->distributor_id) {
                $distName = $item->distributor?->name;
            }

            if (!$distName) {
                $lastInLog = $item->latestLog;
                $distName = $lastInLog && $lastInLog->distributor ? $lastInLog->distributor->name : ($lastInLog->supplier_name ?? null);
            }
            
            if (!$distName && $item->user && $item->user->distributor) {
                $distName = $item->user->distributor->name;
            }

            $distName = $distName ?? '-';

            $price = ($item->selling_price !== null && (float)$item->selling_price > 0) 
                ? (float)$item->selling_price 
                : ($item->product->price !== null ? (float)$item->product->price : 0.0);
            $totalNonHpPrice += ($price * $stok);

            $source = 'Masuk Manual';
            $logToUse = $item->latestLog;

            if ($logToUse) {
                $desc = strtolower($logToUse->description ?? '');
                if (str_contains($desc, 'angkat barang') || ($logToUse->reference_id && str_contains(strtolower($logToUse->reference_id), 'trade-in'))) {
                    $source = 'Angkat Barang';
                } elseif (str_contains($desc, 'tukar tambah')) {
                    $source = 'Tukar Tambah';
                } elseif (str_contains($desc, 'refund')) {
                    $source = 'Refund';
                } elseif (str_contains($desc, 'tukar unit') || str_contains($desc, 'exchange')) {
                    $source = 'Tukar Unit';
                } elseif (str_contains($desc, 'downgrade')) {
                    $source = 'Downgrade';
                } elseif (str_contains($desc, 'pindah cabang') || str_contains($desc, 'transfer')) {
                    $source = 'Pindah Cabang';
                }
            }

            $entryDate = '-';
            $entryTime = '-';
            if ($logToUse && $logToUse->created_at) {
                $entryDate = $logToUse->created_at->format('d/m/Y');
                $entryTime = $logToUse->created_at->format('H:i');
            } elseif ($item->created_at) {
                $entryDate = $item->created_at->format('d/m/Y');
                $entryTime = $item->created_at->format('H:i');
            }

            $nonHpSheet[] = [
                $idx + 1,
                $entryDate,
                $entryTime,
                $source,
                $item->product->brand ?? '-',
                $item->product->name ?? '-',
                $item->placement ? $item->placement->name : ($item->placement_type . ' #' . $item->placement_id),
                $stok,
                $distName,
                $price,
                $item->user->name ?? '-',
                $item->notes ?? '-',
            ];
        }
        $nonHpSheet[] = ['TOTAL', '', '', '', '', '', '', $totalNonHpQty, '', $totalNonHpPrice, '', ''];

        $filename = 'LAPORAN_INVENTORY_' . now()->format('Y-m-d_H-i') . '.xlsx';
        
        \App\Models\ExportLog::create([
            'user_id' => $user->id,
            'report_name' => 'Laporan Data Inventory',
            'filename' => $filename,
            'params' => $request->all()
        ]);

        $xlsx = SimpleXLSXGen::fromSheets([
            'Data IMEI' => $hpSheet,
            'Data Non-IMEI' => $nonHpSheet
        ]);

        return response((string)$xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Apply inventory filters for export (same logic as InventoryController::applyInventoryFilters).
     * Kept here to maintain identical behavior with the main controller's export.
     */
    private function applyInventoryFilters($query, $request, $type)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($request->search) {
            $search = $request->search;
            if ($type === 'hp') {
                $query->where(function ($q) use ($search) {
                    $q->where('imei', 'like', "%{$search}%")->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%");
                    });
                });
            } else {
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
                });
            }
        }

        if ($request->branch_id) $query->where('placement_type', 'branch')->where('placement_id', $request->branch_id);
        if ($request->online_shop_id) $query->where('placement_type', 'online_shop')->where('placement_id', $request->online_shop_id);
        if ($request->warehouse_id) $query->where('placement_type', 'warehouse')->where('placement_id', $request->warehouse_id);
        
        if ($request->brand) {
            $brandArr = explode(',', $request->brand);
            $query->whereHas('product', fn($q) => $q->whereIn('brand', $brandArr));
        }
        
        if ($type === 'hp') {
            $status = $request->status ?? $request->stock_status;
            if ($status && $status !== 'all') $query->where('status', $status);
            else $query->whereIn('status', ['available', 'booking', 'returned', 'process']);
        } else {
            $query->where('quantity', '>', 0);
        }

        $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner', 'analist'];
        if (!$user->hasRole($unrestrictedRoles)) {
            $query->where(function ($q) use ($user) {
                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $shopIds = $user->getAccessibleOnlineShopIds();
                $distributorIds = (array) ($user->getAccessibleDistributorIds() ?: []);
                $hasConstraint = false;
                if (!empty($branchIds)) { $q->orWhere(fn($sq) => $sq->where('placement_type', 'branch')->whereIn('placement_id', $branchIds)); $hasConstraint = true; }
                if (!empty($warehouseIds)) { $q->orWhere(fn($sq) => $sq->where('placement_type', 'warehouse')->whereIn('placement_id', $warehouseIds)); $hasConstraint = true; }
                if (!empty($shopIds)) { $q->orWhere(fn($sq) => $sq->where('placement_type', 'online_shop')->whereIn('placement_id', $shopIds)); $hasConstraint = true; }
                if (!empty($distributorIds)) { $q->orWhere(fn($sq) => $sq->whereIn('distributor_id', $distributorIds)->orWhere(fn($ssq) => $ssq->where('placement_type', 'distributor')->whereIn('placement_id', $distributorIds))); $hasConstraint = true; }
                if (!$hasConstraint) $q->whereRaw('0 = 1');
            });
        }
    }
}
