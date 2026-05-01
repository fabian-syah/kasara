<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\Distributor;
use App\Models\StockOut;
use App\Models\StockOutNonHpItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use App\Traits\VerifiesPin;
use App\Utils\SimpleXLSXGen;

class InventoryController extends Controller
{
    use VerifiesPin;
    // List Inventory
    // List Inventory (Granular / Unit based)
    // Filtered by branch - only super_admin can see all
    public function index(Request $request)
    {
        $user = Auth::user();
        $type = $request->type ?? 'hp';

        // 1. Accessibility
        $osIds = (array) ($user->getAccessibleOnlineShopIds() ?: []);
        $bIds = (array) ($user->getAccessibleBranchIds() ?: []);
        $wIds = (array) ($user->getAccessibleWarehouseIds() ?: []);

        if ($user->online_shop_id)
            $osIds[] = $user->online_shop_id;
        if ($user->branch_id)
            $bIds[] = $user->branch_id;
        if ($user->warehouse_id)
            $wIds[] = $user->warehouse_id;

        $osIds = array_unique(array_filter($osIds));
        $bIds = array_unique(array_filter($bIds));
        $wIds = array_unique(array_filter($wIds));
        $dIds = array_unique(array_filter((array) ($user->getAccessibleDistributorIds() ?: [])));

        $unrestricted = $user->hasRole(['super_admin', 'admin_produk', 'owner', 'analist']);

        // 2. Base Query
        if ($type === 'non-hp') {
            $query = Inventory::with(['product', 'user', 'user.distributor', 'latestLog', 'latestLog.distributor', 'placement'])
                ->select(
                    'product_id',
                    'placement_type',
                    'placement_id',
                    'user_id',
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('MAX(id) as id'), 
                    DB::raw('MAX(distributor_id) as distributor_id'),
                    DB::raw('MAX(cost_price) as cost_price') // Aggregated HPP
                )
                ->where('quantity', '>', 0)
                ->whereHas('product', function ($q) {
                    $q->where('type', 'non-hp');
                })
                ->groupBy('product_id', 'placement_type', 'placement_id', 'user_id');
        } else {
            $query = ProductDetail::with([
                'product',
                'distributor',
                'user',
                'refund',
                'refund.paymentMethod',
                'tradeIn',
                'tradeIn.paymentMethod',
                'placement',
                'stockOuts' => function ($q) {
                    $q->where('category', 'retur');
                }
            ])
                ->whereHas('product', function ($q) {
                    $q->where('type', 'hp')->orWhere('has_imei', true);
                });
        }

        // 3. Security Filter
        // Special case: When fetching retur/service items, warehouse users can see ALL returned items
        // (because returs originate from branches/shops but need to be accepted by warehouse)
        $isReturRequest = ($request->status === 'service') && !empty($wIds);

        if (!$unrestricted && !$isReturRequest) {
            $query->where(function ($q) use ($osIds, $bIds, $wIds, $dIds) {
                $hasConstraint = false;
                if (!empty($osIds)) {
                    $q->orWhere(fn($sq) => $sq->where('placement_type', 'online_shop')->whereIn('placement_id', $osIds));
                    $hasConstraint = true;
                }
                if (!empty($bIds)) {
                    $q->orWhere(fn($sq) => $sq->where('placement_type', 'branch')->whereIn('placement_id', $bIds));
                    $hasConstraint = true;
                }
                if (!empty($wIds)) {
                    $q->orWhere(fn($sq) => $sq->where('placement_type', 'warehouse')->whereIn('placement_id', $wIds));
                    $hasConstraint = true;
                }
                if (!empty($dIds)) {
                    $q->orWhere(fn($sq) => $sq->where('placement_type', 'distributor')->whereIn('placement_id', $dIds));
                    $hasConstraint = true;
                }
                if (!$hasConstraint)
                    $q->whereRaw('0 = 1');
            });
        }

        // 3.1 Analist Exclusion (Hide trial/testing branches)
        if ($user->hasRole('analist') && !$user->hasRole('super_admin')) {
            $excludedKeywords = ['trial', 'anu', 'testing', 'huft', 'test'];
            $query->where(function ($q) use ($excludedKeywords) {
                foreach (['branch', 'online_shop', 'warehouse', 'distributor'] as $pType) {
                    $q->whereNot(function ($sq) use ($pType, $excludedKeywords) {
                        $sq->where('placement_type', $pType);
                        $modelClass = match ($pType) {
                            'branch' => \App\Models\Branch::class,
                            'online_shop' => \App\Models\OnlineShop::class,
                            'warehouse' => \App\Models\Warehouse::class,
                            'distributor' => \App\Models\Distributor::class,
                        };
                        $sq->whereHasMorph('placement', [$modelClass], function ($pq) use ($excludedKeywords) {
                            $pq->where(function ($nq) use ($excludedKeywords) {
                                foreach ($excludedKeywords as $kw) $nq->orWhere('name', 'ilike', "%$kw%");
                            });
                        });
                    });
                }
            });
        }

        // 4. Frontend Filters
        if ($request->filled('online_shop_id'))
            $query->where('placement_type', 'online_shop')->where('placement_id', $request->online_shop_id);
        if ($request->filled('branch_id'))
            $query->where('placement_type', 'branch')->where('placement_id', $request->branch_id);
        if ($request->filled('warehouse_id'))
            $query->where('placement_type', 'warehouse')->where('placement_id', $request->warehouse_id);

        if ($request->filled('placement_type')) {
            $query->where('placement_type', $request->placement_type);
        }

        if ($request->filled('distributor_id')) {
            $query->where('placement_type', 'distributor')->where('placement_id', $request->distributor_id);
        }

        if ($request->filled('brand')) {
            $brands = explode(',', $request->brand);
            $query->whereHas('product', fn($q) => $q->whereIn('brand', $brands));
        }

        if ($request->filled('product')) {
            $products = explode(',', $request->product);
            $query->whereHas('product', fn($q) => $q->whereIn('name', $products));
        }

        if ($type === 'hp') {
            if ($request->filled('capacity')) {
                $caps = explode(',', $request->capacity);
                $query->where(function ($q) use ($caps) {
                    foreach ($caps as $cap) {
                        $cap = trim($cap);
                        if (str_contains($cap, '/')) {
                            [$ram, $storage] = explode('/', $cap);
                            $q->orWhere(fn($sq) => $sq->where('ram', $ram)->where('storage', $storage));
                        } else {
                            $q->orWhere('storage', $cap);
                        }
                    }
                });
            }

            if ($request->filled('condition') && $request->condition !== 'all')
                $query->where('condition', $request->condition);

            $status = $request->status ?? $request->stock_status;
            if ($status && $status !== 'all') {
                $query->where('status', $status);
            } else {
                $query->whereIn('status', ['available', 'booking', 'returned', 'process']);
            }
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s, $type) {
                if ($type === 'hp') {
                    $q->where('imei', 'like', "%$s%");
                }

                $q->orWhereHas('product', function ($pq) use ($s) {
                    $pq->where('name', 'like', "%$s%")
                        ->orWhere('brand', 'like', "%$s%");

                    if (\Schema::hasColumn('products', 'non_imei_category')) {
                        $pq->orWhere('non_imei_category', 'like', "%$s%");
                    } else {
                        $pq->orWhereExists(function ($eq) use ($s) {
                            $eq->select(\DB::raw(1))
                                ->from('product_types')
                                ->whereColumn('product_types.name', 'products.name')
                                ->where('product_types.category', 'like', "%$s%");
                        });
                    }
                });
            });
        }

        // 5. Pagination & Response
        $perPage = $request->per_page ?? 20;
        if ($perPage == -1)
            $perPage = 999999;

        // REMOVED CACHE FOR REAL-TIME UPDATES
        $items = $query->latest('id')->paginate($perPage);

        $items->getCollection()->transform(function ($item) use ($type, $request) {
                $placement = $item->placement;
                $item->placement_name = $placement ? $placement->name : ($item->placement_type . ' #' . $item->placement_id);

                if ($type === 'non-hp') {
                    $item->quantity = $item->total_quantity ?? $item->quantity;

                    // Priority for distributor name:
                    // 1. Existing distributor relationship on the inventory record
                    // 2. Latest log distributor
                    // 3. User distributor fallback
                    $distName = null;
                    if ($item->distributor_id) {
                        $distName = \App\Models\Distributor::find($item->distributor_id)?->name;
                    }

                    if (!$distName) {
                        // Find the LATEST 'in' log for this specific product and location to get the distributor/supplier
                        $lastInLog = \App\Models\InventoryLog::where('product_id', $item->product_id)
                            ->where(function($q) use ($item) {
                                if ($item->placement_type === 'branch') $q->where('branch_id', $item->placement_id);
                                elseif ($item->placement_type === 'warehouse') $q->where('warehouse_id', $item->placement_id);
                                elseif ($item->placement_type === 'online_shop') $q->where('online_shop_id', $item->placement_id);
                            })
                            ->where('type', 'in')
                            ->latest()
                            ->first();

                        $distName = $lastInLog && $lastInLog->distributor ? $lastInLog->distributor->name : ($lastInLog->supplier_name ?? null);
                    }
                    
                    if (!$distName && $item->user && $item->user->distributor) {
                        $distName = $item->user->distributor->name;
                    }

                    $item->latest_distributor = $distName ?? '-';
                    $item->latest_supplier = $item->latestLog ? $item->latestLog->supplier_name : null;

                    // Set prices for Detail Modal
                    $item->selling_price = $item->product->price ?? ($item->product->selling_price ?? 0);
                    $item->price = $item->selling_price;
                }

                if ($request->status === 'service' && $type === 'hp') {
                    $returStockOut = $item->stockOuts->first();
                    if ($returStockOut) {
                        $item->retur_data = [
                            'receipt_id' => $returStockOut->receipt_id,
                            'customer_name' => $returStockOut->customer_name,
                            'customer_phone' => $returStockOut->customer_phone,
                            'retur_officer' => $returStockOut->retur_officer,
                            'retur_issue' => $returStockOut->retur_issue,
                            'retur_seal' => $returStockOut->retur_seal,
                            'proof_image' => $returStockOut->proof_image ? asset('storage/' . $returStockOut->proof_image) : null,
                            'selling_price' => $returStockOut->selling_price,
                            'notes' => $returStockOut->notes,
                            'created_at' => $returStockOut->created_at?->toDateTimeString(),
                        ];
                    }
                }
                return $item;
            });

            // If non-hp, we group by resolved name to avoid split rows (e.g. 14+1 Arcis)
            if ($type === 'non-hp') {
                $uniqueCollection = $items->getCollection()->groupBy(function ($item) {
                    // Group by product and placement AND distributor ID to prevent merging different distributor stock
                    return $item->product_id . '_' . $item->placement_type . '_' . $item->placement_id . '_' . ($item->distributor_id ?? '0') . '_' . $item->latest_distributor;
                })->map(function ($group) {
                    $first = $group->first();
                    $first->quantity = $group->sum('quantity');
                    return $first;
                })->values();
                $items->setCollection($uniqueCollection);
            }

            $res = $items->toArray();
            $res['total_value'] = ($type === 'hp') ? (clone $query)->sum('selling_price') : 0;

            return response()->json($res);
        }

    public function export(Request $request)
    {
        $user = Auth::user();
        
        // --- PREPARE DATA HP ---
        $hpQuery = ProductDetail::with(['product', 'distributor', 'user', 'placement']);
        $this->applyInventoryFilters($hpQuery, $request, 'hp');
        $hpItems = $hpQuery->join('products', 'product_details.product_id', '=', 'products.id')
            ->orderBy('products.brand')
            ->orderBy('products.name')
            ->select('product_details.*')
            ->get();

        $hpSheet = [['No', 'Merek', 'Produk', 'Kapasitas', 'Kondisi', 'IMEI', 'Lokasi', 'Distributor', 'Harga Jual', 'Status', 'Akun Inventory']];
        $totalHpPrice = 0;
        foreach ($hpItems as $idx => $item) {
            $price = $item->selling_price > 0 ? $item->selling_price : ($item->product->price ?? ($item->product->selling_price ?? 0));
            $totalHpPrice += $price;
            $hpSheet[] = [
                $idx + 1,
                $item->product->brand ?? '-',
                $item->product->name ?? '-',
                implode('/', array_filter([$item->ram, $item->storage])),
                $item->condition === 'new' ? 'Baru' : ($item->condition === 'ex_ibox' ? 'Ex iBox' : 'Bekas'),
                str_replace("'", "", $item->imei ?? '-'),
                $item->placement ? $item->placement->name : ($item->placement_type . ' #' . $item->placement_id),
                $item->distributor->name ?? ($item->supplier_name ?? '-'),
                $price,
                strtoupper($item->status),
                $item->user->name ?? '-',
            ];
        }
        $hpSheet[] = ['TOTAL', '', '', '', '', '', '', '', $totalHpPrice, '', ''];

        // --- PREPARE DATA NON-HP ---
        $nonHpQuery = Inventory::with(['product', 'user', 'placement']);
        $this->applyInventoryFilters($nonHpQuery, $request, 'non-hp');
        $nonHpItems = $nonHpQuery->join('products', 'inventories.product_id', '=', 'products.id')
            ->orderBy('products.brand')
            ->orderBy('products.name')
            ->select('inventories.*')
            ->get();

        $nonHpSheet = [['No', 'Merek', 'Produk', 'Lokasi', 'Stok', 'Distributor / Supplier', 'Akun Inventory', 'Catatan']];
        $totalNonHpQty = 0;
        foreach ($nonHpItems as $idx => $item) {
            $stok = $item->quantity ?? 0;
            $totalNonHpQty += $stok;
            $distName = $item->distributor->name ?? ($item->supplier_name ?? '-');
            $nonHpSheet[] = [
                $idx + 1,
                $item->product->brand ?? '-',
                $item->product->name ?? '-',
                $item->placement ? $item->placement->name : ($item->placement_type . ' #' . $item->placement_id),
                $stok,
                $distName,
                $item->user->name ?? '-',
                $item->notes ?? '-',
            ];
        }
        $nonHpSheet[] = ['TOTAL', '', '', '', $totalNonHpQty, '', '', ''];

        $filename = 'LAPORAN_INVENTORY_' . now()->format('Y-m-d_H-i') . '.xlsx';
        
        // Log Export
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

    private function applyInventoryFilters($query, $request, $type)
    {
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
                if (!empty($branchIds)) $q->orWhere(fn($sq) => $sq->where('placement_type', 'branch')->whereIn('placement_id', $branchIds));
                if (!empty($warehouseIds)) $q->orWhere(fn($sq) => $sq->where('placement_type', 'warehouse')->whereIn('placement_id', $warehouseIds));
                if (!empty($shopIds)) $q->orWhere(fn($sq) => $sq->where('placement_type', 'online_shop')->whereIn('placement_id', $shopIds));
            });
        }
    }

    public function exportStockInHistory(Request $request)
    {
        $user = Auth::user();
        
        // 1. HP STOCK IN (ProductDetail)
        $hpQuery = ProductDetail::with(['product', 'distributor', 'user', 'placement']);
        $this->applyStockHistoryFilters($hpQuery, $request, 'hp', 'in');
        $hpItems = $hpQuery->latest()->get();

        $hpSheet = [['No', 'Waktu', 'Merek', 'Produk', 'Kapasitas', 'Kondisi', 'IMEI', 'Lokasi', 'Distributor / Supplier', 'HPP', 'Akun Inventory']];
        foreach ($hpItems as $idx => $item) {
            $hpSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                $item->product->brand ?? '-',
                $item->product->name ?? '-',
                implode('/', array_filter([$item->ram, $item->storage])),
                $item->condition,
                str_replace("'", "", $item->imei ?? '-'),
                $item->placement ? $item->placement->name : '-',
                $item->distributor->name ?? ($item->supplier_name ?? '-'),
                (float)($item->cost_price ?? 0),
                $item->user->name ?? '-',
            ];
        }

        // 2. NON-HP STOCK IN (InventoryLog)
        $nonHpQuery = InventoryLog::with(['product', 'user', 'distributor'])->where('type', 'in');
        $this->applyStockHistoryFilters($nonHpQuery, $request, 'non-hp', 'in');
        $nonHpItems = $nonHpQuery->latest()->get();

        $nonHpSheet = [['No', 'Waktu', 'Merek', 'Produk', 'Lokasi', 'Qty Masuk', 'Distributor / Supplier', 'HPP', 'Akun Inventory', 'Catatan']];
        foreach ($nonHpItems as $idx => $item) {
            $locationName = '-';
            if ($item->branch_id) $locationName = \App\Models\Branch::find($item->branch_id)?->name;
            elseif ($item->warehouse_id) $locationName = \App\Models\Warehouse::find($item->warehouse_id)?->name;
            elseif ($item->online_shop_id) $locationName = \App\Models\OnlineShop::find($item->online_shop_id)?->name;

            $nonHpSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                $item->product->brand ?? '-',
                $item->product->name ?? '-',
                $locationName,
                (int)$item->quantity,
                $item->distributor->name ?? ($item->supplier_name ?? '-'),
                (float)($item->cost_price ?? 0),
                $item->user->name ?? '-',
                $item->description ?? '-',
            ];
        }

        $dateSuffix = $request->date ? "_{$request->date}" : "_" . now()->format('Y-m-d_H-i');
        $filename = 'RIWAYAT_STOK_MASUK' . $dateSuffix . '.xlsx';
        \App\Models\ExportLog::create([
            'user_id' => $user->id,
            'report_name' => 'Riwayat Stok Masuk',
            'filename' => $filename,
            'params' => $request->all()
        ]);

        $xlsx = SimpleXLSXGen::fromSheets([
            'Riwayat HP' => $hpSheet,
            'Riwayat Non-HP' => $nonHpSheet
        ]);

        return response((string)$xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportStockOutHistory(Request $request)
    {
        $user = Auth::user();

        // 1. HP STOCK OUT (InventoryLog where product->type == 'hp')
        $hpQuery = InventoryLog::with(['product', 'user', 'distributor'])
            ->where('type', 'out')
            ->whereHas('product', fn($q) => $q->where('type', 'hp'));
        $this->applyStockHistoryFilters($hpQuery, $request, 'hp', 'out');
        $hpItems = $hpQuery->latest()->get();

        $hpSheet = [['No', 'Waktu', 'Merek', 'Produk', 'IMEI', 'Lokasi', 'Tujuan / Catatan', 'Akun Inventory']];
        foreach ($hpItems as $idx => $item) {
            $locationName = '-';
            if ($item->branch_id) $locationName = \App\Models\Branch::find($item->branch_id)?->name;
            elseif ($item->warehouse_id) $locationName = \App\Models\Warehouse::find($item->warehouse_id)?->name;
            elseif ($item->online_shop_id) $locationName = \App\Models\OnlineShop::find($item->online_shop_id)?->name;

            // Extract IMEI from description or reference if possible
            $imei = '-';
            if (preg_match('/\(([\d]+)\)/', $item->description, $matches)) {
                $imei = $matches[1];
            }

            $hpSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                $item->product->brand ?? '-',
                $item->product->name ?? '-',
                str_replace("'", "", $imei),
                $locationName,
                $item->description ?? '-',
                $item->user->name ?? '-',
            ];
        }

        // 2. NON-HP STOCK OUT (InventoryLog where product->type == 'non-hp')
        $nonHpQuery = InventoryLog::with(['product', 'user', 'distributor'])
            ->where('type', 'out')
            ->whereHas('product', fn($q) => $q->where('type', 'non-hp'));
        $this->applyStockHistoryFilters($nonHpQuery, $request, 'non-hp', 'out');
        $nonHpItems = $nonHpQuery->latest()->get();

        $nonHpSheet = [['No', 'Waktu', 'Merek', 'Produk', 'Lokasi', 'Qty Keluar', 'Tujuan / Catatan', 'Akun Inventory']];
        foreach ($nonHpItems as $idx => $item) {
            $locationName = '-';
            if ($item->branch_id) $locationName = \App\Models\Branch::find($item->branch_id)?->name;
            elseif ($item->warehouse_id) $locationName = \App\Models\Warehouse::find($item->warehouse_id)?->name;
            elseif ($item->online_shop_id) $locationName = \App\Models\OnlineShop::find($item->online_shop_id)?->name;

            $nonHpSheet[] = [
                $idx + 1,
                $item->created_at->format('d/m/Y H:i'),
                $item->product->brand ?? '-',
                $item->product->name ?? '-',
                $locationName,
                (int)$item->quantity,
                $item->description ?? '-',
                $item->user->name ?? '-',
            ];
        }

        $dateSuffix = $request->date ? "_{$request->date}" : "_" . now()->format('Y-m-d_H-i');
        $filename = 'RIWAYAT_STOK_KELUAR' . $dateSuffix . '.xlsx';
        
        // Log Export
        \App\Models\ExportLog::create([
            'user_id' => $user->id,
            'report_name' => 'Riwayat Stok Keluar',
            'filename' => $filename,
            'params' => $request->all()
        ]);

        $xlsx = SimpleXLSXGen::fromSheets([
            'Riwayat HP' => $hpSheet,
            'Riwayat Non-HP' => $nonHpSheet
        ]);

        return response((string)$xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function applyStockHistoryFilters($query, $request, $type, $mode)
    {
        $user = Auth::user();
        
        // Filter by Branch/Shop/Warehouse
        if ($request->branch_id) {
            if ($type === 'hp' && $mode === 'out') $query->where('branch_id', $request->branch_id);
            else $query->where('branch_id', $request->branch_id); // Simplified
        }
        if ($request->online_shop_id) {
             if ($type === 'hp' && $mode === 'out') $query->where('online_shop_id', $request->online_shop_id);
             else $query->where('online_shop_id', $request->online_shop_id);
        }
        if ($request->warehouse_id) {
             if ($type === 'hp' && $mode === 'out') $query->where('warehouse_id', $request->warehouse_id);
             else $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by Date
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        }

        // Accessibility (Simplified for now)
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner', 'analist'];
        if (!$user->hasRole($unrestrictedRoles)) {
            // Add access scoping if needed
        }
    }

    // Stock In History
    public function stockInHistory(Request $request)
    {
        $user = Auth::user();
        $type = $request->type ?? 'hp';

        if ($type === 'non-hp') {
            $query = InventoryLog::with(['product', 'user', 'distributor'])
                ->where('type', 'in');

            // SEARCH
            if ($request->search) {
                $search = $request->search;
                $keywords = explode(' ', $search);

                $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $lowKeyword = strtolower($keyword);
                        $q->where(function ($sub) use ($lowKeyword) {
                            $sub->whereHas('product', function ($sq) use ($lowKeyword) {
                                $sq->whereRaw('LOWER(name) LIKE ?', ["%{$lowKeyword}%"])
                                    ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$lowKeyword}%"]);

                                if (\Schema::hasColumn('products', 'non_imei_category')) {
                                    $sq->orWhereRaw('LOWER(non_imei_category) LIKE ?', ["%{$lowKeyword}%"]);
                                }
                            })
                                ->orWhereRaw('LOWER(description) LIKE ?', ["%{$lowKeyword}%"]);
                        });
                    }
                });
            }
        } else {
            // HP (Product Details created) - This is logically Stock In too
            $query = ProductDetail::with(['product', 'distributor', 'user']);

            // SEARCH
            if ($request->search) {
                $search = $request->search;
                $keywords = explode(' ', $search);

                $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $lowKeyword = strtolower($keyword);
                        $q->where(function ($sub) use ($lowKeyword) {
                            $sub->whereRaw('LOWER(imei) LIKE ?', ["%{$lowKeyword}%"])
                                ->orWhereHas('product', function ($sq) use ($lowKeyword) {
                                    $sq->whereRaw('LOWER(name) LIKE ?', ["%{$lowKeyword}%"])
                                        ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$lowKeyword}%"]);
                                });
                        });
                    }
                });
            }
        }

        // PLACEMENT FILTER (Same logic as index)
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'analist', 'owner'];
        if (!$user->hasRole($unrestrictedRoles)) {
            $query->where(function ($q) use ($user, $type) {
                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $onlineShopIds = $user->getAccessibleOnlineShopIds();
                $dIds = [];
                if ($user->distributor_id) {
                    $dIds[] = $user->distributor_id;
                }

                if ($type === 'non-hp') {
                    // For non-hp, check if the inventory log references a product/user combo that exists in an allowed placement in the `inventories` table
                    $q->whereExists(function ($query) use ($branchIds, $warehouseIds, $onlineShopIds, $dIds) {
                        $query->select(\DB::raw(1))
                            ->from('inventories')
                            ->whereColumn('inventories.product_id', 'inventory_logs.product_id')
                            ->whereColumn('inventories.user_id', 'inventory_logs.user_id')
                            ->where(function ($sq) use ($branchIds, $warehouseIds, $onlineShopIds, $dIds) {
                                $hasC = false;
                                if (!empty($branchIds)) {
                                    $sq->orWhere(function ($sub) use ($branchIds) {
                                        $sub->where('placement_type', 'branch')->whereIn('placement_id', $branchIds);
                                    });
                                    $hasC = true;
                                }
                                if (!empty($warehouseIds)) {
                                    $sq->orWhere(function ($sub) use ($warehouseIds) {
                                        $sub->where('placement_type', 'warehouse')->whereIn('placement_id', $warehouseIds);
                                    });
                                    $hasC = true;
                                }
                                if (!empty($onlineShopIds)) {
                                    $sq->orWhere(function ($sub) use ($onlineShopIds) {
                                        $sub->where('placement_type', 'online_shop')->whereIn('placement_id', $onlineShopIds);
                                    });
                                    $hasC = true;
                                }
                                if (!empty($dIds)) {
                                    $sq->orWhere(function ($sub) use ($dIds) {
                                        $sub->where('placement_type', 'distributor')->whereIn('placement_id', $dIds);
                                    });
                                    $hasC = true;
                                }
                                if (!$hasC) {
                                    $sq->whereRaw('0 = 1');
                                }
                            });
                    });
                } else {
                    $hasConstraint = false;
                    if (!empty($branchIds)) {
                        $q->orWhere(function ($sub) use ($branchIds) {
                            $sub->where('placement_type', 'branch')->whereIn('placement_id', $branchIds);
                        });
                        $hasConstraint = true;
                    }

                    if (!empty($warehouseIds)) {
                        $q->orWhere(function ($sub) use ($warehouseIds) {
                            $sub->where('placement_type', 'warehouse')->whereIn('placement_id', $warehouseIds);
                        });
                        $hasConstraint = true;
                    }

                    if (!empty($onlineShopIds)) {
                        $q->orWhere(function ($sub) use ($onlineShopIds) {
                            $sub->where('placement_type', 'online_shop')->whereIn('placement_id', $onlineShopIds);
                        });
                        $hasConstraint = true;
                    }

                    if (!empty($dIds)) {
                        $q->orWhere(function ($sub) use ($dIds) {
                            $sub->where('placement_type', 'distributor')->whereIn('placement_id', $dIds);
                        });
                        $hasConstraint = true;
                    }

                    if (!$hasConstraint) {
                        $q->whereRaw('0 = 1');
                    }
                }
            });
        }

        // Analist Exclusion for Stock In History
        if ($user->hasRole('analist') && !$user->hasRole('super_admin')) {
            $excludedKeywords = ['trial', 'anu', 'testing', 'huft', 'test'];
            $query->where(function ($q) use ($excludedKeywords, $type) {
                if ($type === 'non-hp') {
                    // For logs, we check the placement of the product/user combo
                    $q->whereNotExists(function ($sub) use ($excludedKeywords) {
                        $sub->select(\DB::raw(1))
                            ->from('inventories')
                            ->whereColumn('inventories.product_id', 'inventory_logs.product_id')
                            ->whereColumn('inventories.user_id', 'inventory_logs.user_id')
                            ->where(function ($inner) use ($excludedKeywords) {
                                foreach (['branch', 'online_shop', 'warehouse', 'distributor'] as $pType) {
                                    $inner->orWhere(function ($sq) use ($pType, $excludedKeywords) {
                                        $sq->where('placement_type', $pType);
                                        $tableName = $pType === 'branch' ? 'branches' : ($pType === 'online_shop' ? 'online_shops' : 'warehouses');
                                        $sq->whereExists(function ($exq) use ($tableName, $excludedKeywords) {
                                            $exq->select(\DB::raw(1))->from($tableName)->whereColumn("$tableName.id", "inventories.placement_id")
                                                ->where(function ($nq) use ($excludedKeywords) {
                                                    foreach ($excludedKeywords as $kw) $nq->orWhere('name', 'ilike', "%$kw%");
                                                });
                                        });
                                    });
                                }
                            });
                    });
                } else {
                    foreach (['branch', 'online_shop', 'warehouse', 'distributor'] as $pType) {
                        $q->whereNot(function ($sq) use ($pType, $excludedKeywords) {
                            $sq->where('placement_type', $pType);
                            $modelClass = match ($pType) {
                                'branch' => \App\Models\Branch::class,
                                'online_shop' => \App\Models\OnlineShop::class,
                                'warehouse' => \App\Models\Warehouse::class,
                                'distributor' => \App\Models\Distributor::class,
                            };
                            $sq->whereHasMorph('placement', [$modelClass], function ($pq) use ($excludedKeywords) {
                                $pq->where(function ($nq) use ($excludedKeywords) {
                                    foreach ($excludedKeywords as $kw) $nq->orWhere('name', 'ilike', "%$kw%");
                                });
                            });
                        });
                    }
                }
            });
        }

        // AUDIT BRANCH FILTER
        if ($request->branch_id && $user->hasRole($unrestrictedRoles)) {
            $query->where(function ($q) use ($request, $type) {
                if ($type === 'non-hp') {
                    $q->whereExists(function ($query) use ($request) {
                        $query->select(\DB::raw(1))
                            ->from('inventories')
                            ->whereColumn('inventories.product_id', 'inventory_logs.product_id')
                            ->whereColumn('inventories.user_id', 'inventory_logs.user_id')
                            ->where('placement_type', 'branch')
                            ->where('placement_id', $request->branch_id);
                    });
                } else {
                    $q->where('placement_type', 'branch')
                        ->where('placement_id', $request->branch_id);
                }
            });
        }

        // AUDIT ONLINE SHOP FILTER
        if ($request->online_shop_id && $user->hasRole($unrestrictedRoles)) {
            $query->where(function ($q) use ($request, $type) {
                if ($type === 'non-hp') {
                    $q->whereExists(function ($query) use ($request) {
                        $query->select(\DB::raw(1))
                            ->from('inventories')
                            ->whereColumn('inventories.product_id', 'inventory_logs.product_id')
                            ->whereColumn('inventories.user_id', 'inventory_logs.user_id')
                            ->where('placement_type', 'online_shop')
                            ->where('placement_id', $request->online_shop_id);
                    });
                } else {
                    $q->where('placement_type', 'online_shop')
                        ->where('placement_id', $request->online_shop_id);
                }
            });
        }

        // DATE FILTER
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        if ($request->date) {
            $d = $request->date;
            if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
                $today = $logicalNow->toDateString();
                $sevenDaysAgo = $logicalNow->copy()->subDays(7)->toDateString();
                if ($d < $sevenDaysAgo)
                    $d = $today;
            }
            $query->whereDate('created_at', $d);
        } elseif ($request->month && $request->year) {
            $m = (int) $request->month;
            $y = (int) $request->year;
            if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
                $currentMonth = (int) $logicalNow->format('m');
                $currentYear = (int) $logicalNow->format('Y');
                $lastMonthTemp = $logicalNow->copy()->subMonth();
                $lastMonth = (int) $lastMonthTemp->format('m');
                if ($y < $currentYear) {
                    $m = $currentMonth;
                    $y = $currentYear;
                } elseif ($y == $currentYear && $m < $lastMonth && !($currentMonth == 1 && $m == 12)) {
                    $m = $currentMonth;
                }
            }
            $query->whereMonth('created_at', $m)
                ->whereYear('created_at', $y);
        }

        // DATE FILTER FOR INVENTORY ROLE (Current & Last Month Only)
        if ($user->hasRole('inventory')) {
            $startDate = \Carbon\Carbon::now()->subMonth()->startOfMonth();
            $query->where('created_at', '>=', $startDate);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function stockOutHistory(Request $request)
    {
        $user = Auth::user();
        // Since HP Stock Out is handled by StockOutController (Receipt based), 
        // this method primarily serves Non-HP (Inventory Log based) history.
        // However, if we wanted to unify, we could... but let's stick to the pattern.

        // This is ONLY for Non-HP logs for now, as HP logs are in StockOut model/table
        $query = InventoryLog::with(['product', 'user', 'distributor'])
            ->where('type', 'out');

        // SEARCH
        if ($request->search) {
            $search = $request->search;
            $keywords = explode(' ', $search);

            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $lowKeyword = strtolower($keyword);
                    $q->where(function ($sub) use ($lowKeyword) {
                        $sub->whereHas('product', function ($sq) use ($lowKeyword) {
                            $sq->whereRaw('LOWER(name) LIKE ?', ["%{$lowKeyword}%"])
                                ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$lowKeyword}%"]);

                            if (\Schema::hasColumn('products', 'non_imei_category')) {
                                $sq->orWhereRaw('LOWER(non_imei_category) LIKE ?', ["%{$lowKeyword}%"]);
                            }
                        })
                            ->orWhereRaw('LOWER(description) LIKE ?', ["%{$lowKeyword}%"]);
                    });
                }
            });
        }

        // PLACEMENT FILTER
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'analist', 'owner'];
        if (!$user->hasRole($unrestrictedRoles)) {
            $query->where(function ($q) use ($user) {
                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $onlineShopIds = $user->getAccessibleOnlineShopIds();

                $hasConstraint = false;
                if (!empty($branchIds)) {
                    $q->orWhereIn('branch_id', $branchIds);
                    $hasConstraint = true;
                }
                if (!empty($warehouseIds)) {
                    $q->orWhereIn('warehouse_id', $warehouseIds);
                    $hasConstraint = true;
                }
                if (!empty($onlineShopIds)) {
                    $q->orWhereIn('online_shop_id', $onlineShopIds);
                    $hasConstraint = true;
                }
                if ($user->distributor_id) {
                    $q->orWhere('user_id', $user->id);
                    $hasConstraint = true;
                }

                if (!$hasConstraint) {
                    $q->whereRaw('0 = 1');
                }
            });
        }

        // Analist Exclusion for Stock Out History
        if ($user->hasRole('analist') && !$user->hasRole('super_admin')) {
            $excludedKeywords = ['trial', 'anu', 'testing', 'huft', 'test'];
            $query->where(function ($q) use ($excludedKeywords) {
                foreach (['branch', 'online_shop', 'warehouse', 'distributor'] as $pType) {
                    $q->whereNot(function ($sq) use ($pType, $excludedKeywords) {
                        $tableName = match ($pType) {
                            'branch' => 'branches',
                            'online_shop' => 'online_shops',
                            'warehouse' => 'warehouses',
                            'distributor' => 'distributors',
                        };
                        $colName = match ($pType) {
                            'branch' => 'branch_id',
                            'online_shop' => 'online_shop_id',
                            'warehouse' => 'warehouse_id',
                            'distributor' => 'distributor_id',
                        };
                        
                        $sq->whereNotNull($colName)
                           ->whereExists(function ($exq) use ($tableName, $colName, $excludedKeywords) {
                               $exq->select(\DB::raw(1))->from($tableName)->whereColumn("$tableName.id", "inventory_logs.$colName")
                                   ->where(function ($nq) use ($excludedKeywords) {
                                       foreach ($excludedKeywords as $kw) $nq->orWhere('name', 'ilike', "%$kw%");
                                   });
                           });
                    });
                }
            });
        }

        // DATE FILTER
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        if ($request->date) {
            $d = $request->date;
            if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
                $today = $logicalNow->toDateString();
                $yesterday = $logicalNow->copy()->subDay()->toDateString();
                if ($d < $yesterday)
                    $d = $today;
            }
            $query->whereDate('created_at', $d);
        } elseif ($request->month && $request->year) {
            $m = (int) $request->month;
            $y = (int) $request->year;
            if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'])) {
                $currentMonth = (int) $logicalNow->format('m');
                $currentYear = (int) $logicalNow->format('Y');
                $lastMonthTemp = $logicalNow->copy()->subMonth();
                $lastMonth = (int) $lastMonthTemp->format('m');
                if ($y < $currentYear) {
                    $m = $currentMonth;
                    $y = $currentYear;
                } elseif ($y == $currentYear && $m < $lastMonth && !($currentMonth == 1 && $m == 12)) {
                    $m = $currentMonth;
                }
            }
            $query->whereMonth('created_at', $m)
                ->whereYear('created_at', $y);
        }

        // DATE FILTER FOR INVENTORY ROLE (Current & Last Month Only)
        if ($user->hasRole('inventory')) {
            $startDate = \Carbon\Carbon::now()->subMonth()->startOfMonth();
            $query->where('created_at', '>=', $startDate);
        }

        return response()->json($query->latest()->paginate(20));
    }


    public function stockIn(Request $request)
    {
        $request->validate([
            'product_id' => 'required_if:type,hp|nullable|exists:products,id',
            'distributor_id' => 'nullable|exists:distributors,id',
            'new_distributor_name' => 'nullable|string|max:255',
            'type' => 'required|in:hp,non-hp,HP,NON-HP',
            'transaction_pin' => 'nullable|string|min:4|max:6',

            'placement_type' => 'required|in:branch,warehouse,online_shop,distributor',
            'placement_id' => 'required|integer',
            'inventory_user_id' => 'nullable|integer|exists:users,id',

            // Multi-item support for Non-HP
            'items' => 'required_if:type,non-hp|array',
            'items.*.brand_name' => 'nullable|string',
            'items.*.brand_id' => 'nullable|integer|exists:brands,id',
            'items.*.type_name' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.cost_price' => 'nullable|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',

            // Fallback for Legacy/Single Input
            'quantity' => 'required_without:items|nullable|integer|min:1',

            // For HP
            'imeis' => 'required_if:type,hp|array',
            'imeis.*.imei' => ['required_if:type,hp', 'string', 'distinct', 'max:40', 'regex:/^[a-zA-Z0-9]+$/'],
            'imeis.*.ram' => 'nullable|string',
            'imeis.*.storage' => 'nullable|string',
            'storage' => 'nullable|string',
            'imeis.*.condition' => 'required_if:type,hp|in:new,second,ex_ibox,ex_inter,refurbished',
            'imeis.*.cost_price' => 'nullable|numeric|min:0',
            'imeis.*.selling_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
            'category' => 'nullable|string|in:pembelian,retur_customer,pindah_cabang,salah_input,cancel_penjualan',
        ]);

        $request->merge(['type' => strtolower($request->type)]);
        $user = Auth::user();

        // Determine Ownership User (Who 'owns' the stock)
        // If inventory_user_id is passed (from shared account selection), use that.
        // Otherwise use logged in user.
        $ownerUserId = $user->id;
        if ($request->has('inventory_user_id') && $request->inventory_user_id) {
            $ownerUserId = $request->inventory_user_id;
        }

        $targetUser = \App\Models\User::find($ownerUserId);

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request, $ownerUserId);
        if ($pinError)
            return $pinError;

        DB::beginTransaction();

        try {
            $distributorId = $request->distributor_id;
            $supplierName = null;

            if (!$distributorId && $request->new_distributor_name) {
                $supplierName = $request->new_distributor_name;
                // Try to find if this name exists in our distributors list
                $matchingDist = \App\Models\Distributor::where('name', 'ilike', trim($supplierName))->first();
                if ($matchingDist) {
                    $distributorId = $matchingDist->id;
                } else {
                    $distributorId = null;
                }
            }

            if (!$distributorId && !$supplierName) {
                throw new \Exception("Distributor harus dipilih atau diisi manual.");
            }
            $product = null;
            if ($request->product_id) {
                $product = Product::find($request->product_id);
            }

            if (!$product && strtolower($request->type) === 'hp') {
                return response()->json(['message' => 'Produk tidak ditemukan. Pastikan nama Tipe sesuai.'], 404);
            }

            // 1. Handle Non-HP (Quantity Based)
            if ($request->type === 'non-hp') {
                $items = $request->items ?? [
                    [
                        'product_id' => $request->product_id,
                        'quantity' => $request->quantity,
                        'selling_price' => $request->selling_price,
                        // Brand/Type for auto-creation if product_id is null
                        'brand_id' => $request->brand_id,
                        'brand_name' => $request->brand_name,
                        'type_name' => $request->type_name,
                    ]
                ];

                $results = [];

                foreach ($items as $item) {
                    $pId = $item['product_id'] ?? null;

                    if (!$pId && !empty($item['type_name'])) {
                        // AUTO CREATE PRODUCT IF NOT EXISTS
                        $brandName = $item['brand_name'] ?? 'Unknown';
                        $typeName = $item['type_name'];

                        $foundType = \App\Models\ProductType::where('name', $typeName)
                            ->where('brand_id', $item['brand_id'])
                            ->first();
                        $nonImeiCat = $foundType ? $foundType->non_imei_category : null;

                        $productParams = [
                            'name' => $typeName,
                            'brand' => $brandName,
                            'type' => 'non-hp'
                        ];
                        if (\Schema::hasColumn('products', 'non_imei_category')) {
                            $productParams['non_imei_category'] = $nonImeiCat;
                        }

                        $prod = Product::firstOrCreate(
                            $productParams,
                            [
                                'sku' => 'NHP-' . strtoupper(\Illuminate\Support\Str::random(8)),
                                'category' => 'NON HP / NON IMEI',
                                'has_imei' => false,
                                'price' => $item['selling_price'] ?? 0,
                                'brand_id' => $item['brand_id'] ?? null
                            ]
                        );

                        // If product existed but category was null, update it
                        if ($prod->wasRecentlyCreated === false && $nonImeiCat) {
                            if (\Schema::hasColumn('products', 'non_imei_category') && is_null($prod->non_imei_category)) {
                                $prod->update(['non_imei_category' => $nonImeiCat]);
                            }
                        }
                        $pId = $prod->id;
                    }

                    if ($pId) {
                        $p = Product::find($pId);
                        if ($p && isset($item['selling_price']) && $item['selling_price'] > 0) {
                            $updatePrices = ['price' => $item['selling_price']];
                            if (\Schema::hasColumn('products', 'selling_price')) {
                                $updatePrices['selling_price'] = $item['selling_price'];
                            }
                            $p->update($updatePrices);
                        }
                    }

                    if (!$pId)
                        continue;

                    $distributorId = $item['distributor_id'] ?? $request->distributor_id;
                    $sellingPrice = floatval($item['selling_price'] ?? 0);
                    $costPrice = floatval($item['cost_price'] ?? $sellingPrice); // Default to selling price if HPP is 0/missing

                    $inventory = Inventory::firstOrCreate(
                        [
                            'product_id' => $pId,
                            'placement_type' => $request->placement_type,
                            'placement_id' => $request->placement_id,
                            'distributor_id' => $distributorId,
                            'cost_price' => $costPrice,
                            'user_id' => $ownerUserId
                        ],
                        ['quantity' => 0]
                    );

                    $quantity = $item['quantity'] ?? 1;
                    $inventory->increment('quantity', $quantity);

                    // Log
                    $log = InventoryLog::create([
                        'product_id' => $pId,
                        'branch_id' => $request->placement_type === 'branch' ? $request->placement_id : null,
                        'warehouse_id' => $request->placement_type === 'warehouse' ? $request->placement_id : null,
                        'online_shop_id' => $request->placement_type === 'online_shop' ? $request->placement_id : null,
                        'user_id' => $ownerUserId,
                        'distributor_id' => $distributorId,
                        'supplier_name' => $supplierName,
                        'type' => 'in',
                        'quantity' => $quantity,
                        'balance_after' => $inventory->quantity,
                        'description' => "Stock In Batch from " . ($supplierName ?: "Distributor"),
                        'reference_id' => 'STOCK-IN-NHP-' . time() . '-' . $pId,
                        'notes' => $request->notes,
                    ]);

                    // Audit StockOut Record
                    $stockOutAudit = StockOut::create([
                        'receipt_id' => 'IN-NHP-' . strtoupper(\Illuminate\Support\Str::random(6)),
                        'category' => $request->category ?? 'barang_masuk',
                        'user_id' => Auth::id(),
                        'inventory_user_id' => $ownerUserId,
                        'status' => 'received',
                        'notes' => $request->notes,
                    ]);

                    StockOutNonHpItem::create([
                        'stock_out_id' => $stockOutAudit->id,
                        'product_id' => $pId,
                        'quantity' => $quantity,
                        'received_quantity' => $quantity,
                        'selling_price' => $item['selling_price'] ?? 0,
                    ]);

                    $results[] = $inventory->load(['product', 'user']);
                }

                DB::commit();

                // Dispatch events outside transaction
                foreach ($results as $inv) {
                    try {
                        event(new \App\Events\StockInEvent($inv));
                    } catch (\Exception $e) {
                        \Log::error("Event fail: " . $e->getMessage());
                    }
                }
                
                // Bust Inventory Cache
                \Illuminate\Support\Facades\Cache::increment('inv_version');

                return response()->json(['message' => 'Multiple stock in successful', 'count' => count($results)], 201);
            }

            // 2. Handle HP (IMEI Based)
            // 2. Handle HP (IMEI Based)
            else {
                // Determine details array key
                $details = $request->imeis ?? $validated['details'] ?? [];

                $inserted_count = 0;
                $duplicates = [];

                $newDetails = []; // Capture for events

                foreach ($details as $item) {
                    // Check Duplicate IMEI globally (including soft deleted)
                    $existing = ProductDetail::withTrashed()->where('imei', $item['imei'])->first();

                    if ($existing) {
                        // If it is currently AVAILABLE, then it is a duplicate.
                        if ($existing->status === 'available' && !$existing->trashed()) {
                            $duplicates[] = $item['imei'];
                            continue;
                        }

                        // If it is NOT available (Sold, Out, etc.) OR it is Trashed -> We can Reuse/Restore it.
                        if ($existing->trashed()) {
                            $existing->restore();
                        }

                        // UPDATE properties to reflect new Stock In (FRESH ENTRY)
                        $existing->fill([
                            // Update core fields - Mass Assignable
                            'product_id' => $product->id,
                            'ram' => $request->ram ?? $existing->ram, // Keep existing spec if not provided
                            'storage' => $request->storage ?? $existing->storage,
                            'condition' => $item['condition'],
                            'status' => 'available',
                            'placement_type' => $request->placement_type,
                            'placement_id' => $request->placement_id,
                            'cost_price' => $item['cost_price'] ?? null,
                            'selling_price' => $item['selling_price'] ?? null,
                            'distributor_id' => $distributorId,
                            'supplier_name' => $supplierName,
                            'user_id' => $ownerUserId,
                            'notes' => $request->notes,
                        ]);

                        // FORCE UPDATE created_at (Bypass Mass Assignment Protection if not fillable)
                        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
                        $existing->created_at = $logicalNow;
                        $existing->updated_at = $logicalNow;
                        $existing->save();

                        $newDetails[] = $existing;
                        $inserted_count++;
                        continue;
                    }

                    $detail = ProductDetail::create([
                        'product_id' => $product->id,
                        'imei' => $item['imei'],
                        'ram' => $request->ram ?? null, // Use parent spec
                        'storage' => $request->storage ?? null, // Use parent spec
                        'condition' => $item['condition'],
                        'status' => 'available',
                        'placement_type' => $request->placement_type,
                        'placement_id' => $request->placement_id,
                        'cost_price' => $item['cost_price'] ?? null,
                        'selling_price' => $item['selling_price'] ?? null,
                        'distributor_id' => $distributorId,
                        'supplier_name' => $supplierName,
                        'user_id' => $ownerUserId,
                        'notes' => $request->notes,
                    ]);

                    $newDetails[] = $detail;
                    $inserted_count++;

                    // Create individual Log for each unit (Better for reporting)
                    InventoryLog::create([
                        'product_id' => $product->id,
                        'branch_id' => $request->placement_type === 'branch' ? $request->placement_id : null,
                        'warehouse_id' => $request->placement_type === 'warehouse' ? $request->placement_id : null,
                        'online_shop_id' => $request->placement_type === 'online_shop' ? $request->placement_id : null,
                        'user_id' => $ownerUserId,
                        'distributor_id' => $distributorId,
                        'supplier_name' => $supplierName,
                        'type' => 'in',
                        'quantity' => 1,
                        'balance_after' => ProductDetail::where('product_id', $product->id)->where('status', 'available')->count(),
                        'description' => "Stock In: {$product->name} ({$detail->imei}) dari " . ($supplierName ?: "Distributor"),
                        'reference_id' => (string)$detail->id,
                        'notes' => $request->notes,
                    ]);
                }

                // Create StockOut Record for Audit Purposes (Manual Stock In)
                if ($inserted_count > 0) {
                    $stockOutAudit = StockOut::create([
                        'receipt_id' => 'IN-HP-' . strtoupper(\Illuminate\Support\Str::random(6)),
                        'category' => $request->category ?? 'barang_masuk',
                        'user_id' => Auth::id(),
                        'inventory_user_id' => $ownerUserId,
                        'status' => 'received',
                        'notes' => $request->notes,
                    ]);

                    // Attach HP items
                    $stockOutAudit->items()->attach(collect($newDetails)->pluck('id'));
                }

                // Update Master Product Price (Sync with latest Stock In Selling Price)
                if (count($request->imeis) > 0 && isset($request->imeis[0]['selling_price'])) {
                    $product->update(['price' => $request->imeis[0]['selling_price']]);
                }

                DB::commit();

                // Dispatch Events for HP Items
                foreach ($newDetails as $detail) {
                    try {
                        // Load relationships to match what frontend expects
                        $detail->load(['product', 'distributor', 'user']);
                        event(new \App\Events\StockInEvent($detail));
                    } catch (\Exception $e) {
                        \Log::error("Failed to broadcast StockInEvent for HP item: " . $e->getMessage());
                    }
                }

                return response()->json([
                    'message' => 'Stock in processed',
                    'success' => true,
                    'inserted_count' => $inserted_count,
                    'duplicates' => $duplicates
                ], 201);
            }

            DB::commit();

            // Dispatch Event for Non-HP
            if ($request->type === 'non-hp') {
                try {
                    event(new \App\Events\StockInEvent($inventory->load(['product', 'user'])));
                } catch (\Exception $e) {
                    \Log::error("Failed to broadcast StockInEvent for Non-HP item: " . $e->getMessage());
                }
            }

            return response()->json(['message' => 'Stock in successful'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // Helper to get products for dropdown
    public function getProducts(Request $request)
    {
        $query = Product::query();
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->name) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        return response()->json($query->select('id', 'name', 'type', 'sku', 'brand', 'price')->limit(20)->get());
    }

    // Update item status (e.g., accept return: service -> available)
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:available,sold,returned,deleted,out',
            'inventory_user_id' => 'nullable|exists:users,id',
            'transaction_pin' => 'nullable|string|size:4',
        ]);

        $targetUser = Auth::user();
        if ($request->has('inventory_user_id') && $request->inventory_user_id) {
            $targetUser = \App\Models\User::find($request->inventory_user_id);
        }

        // PIN Verification using Trait
        $pinError = $this->verifyPin($request);
        if ($pinError)
            return $pinError;

        $item = ProductDetail::findOrFail($id);
        $oldStatus = $item->status;

        $updateData = ['status' => $request->status];

        // If accepting a return and an inventory account is specified,
        // record who accepted it by updating user_id
        if ($request->status === 'available' && $request->inventory_user_id) {
            $updateData['user_id'] = $request->inventory_user_id;

            // Get the warehouse placement from the inventory user
            $invUser = \App\Models\User::find($request->inventory_user_id);
            if ($invUser && $invUser->warehouse_id) {
                $updateData['placement_type'] = 'warehouse';
                $updateData['placement_id'] = $invUser->warehouse_id;
            }
        }

        $item->update($updateData);

        // If this was a retur acceptance (service -> available),
        // mark the retur stock_out as confirmed so it shows as MASUK in Lacak Barang
        if ($oldStatus === 'service' && $request->status === 'available') {
            $returStockOut = $item->stockOuts()
                ->where('category', 'retur')
                ->whereNull('confirmed_at')
                ->latest()
                ->first();

            if ($returStockOut) {
                $returStockOut->update([
                    'status' => 'received',
                    'confirmed_at' => now(),
                    'confirmed_by' => $request->inventory_user_id ?? Auth::id(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diubah',
            'data' => $item
        ]);
    }
    // Create Dedicated Inventory Account
    public function createAccount(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Entering createAccount', ['user_id' => Auth::id(), 'request' => $request->all()]);

        $request->validate([
            'name' => 'required|string|max:50',
            'transaction_pin' => 'nullable|string|size:4'
        ]);

        $user = Auth::user();
        if (!$user->branch_id && !$user->warehouse_id && !$user->online_shop_id && !$user->distributor_id && !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Anda tidak memiliki lokasi fisik untuk membuat akun inventory.'], 403);
        }

        // Generate Credentials
        $username = 'inv.' . strtolower(Str::random(8)) . '.' . rand(100, 999);
        $email = $username . '@apex-inventory.com';
        $password = 'inventory123'; // Default password

        DB::beginTransaction();
        try {
            // Ensure Role Exists
            $roleName = 'inventory';
            if (!\Spatie\Permission\Models\Role::where('name', $roleName)->exists()) {
                \Spatie\Permission\Models\Role::create(['name' => $roleName, 'guard_name' => 'web']);
            }

            $newUser = \App\Models\User::create([
                'name' => $request->name,
                'full_name' => $request->name,
                'username' => $username,
                'code_id' => 'INV-' . strtoupper(Str::random(10)),
                'email' => $email,
                'password' => $password,
                'branch_id' => $request->branch_id ?? $user->branch_id,
                'warehouse_id' => $request->warehouse_id ?? $user->warehouse_id,
                'online_shop_id' => $request->online_shop_id ?? $user->online_shop_id,
                'distributor_id' => $request->distributor_id ?? $user->distributor_id,
                'created_by' => $user->id, // Mark ownership
                'is_active' => true,
                'theme_color' => 'default',
                'transaction_pin' => $request->transaction_pin ?? '0000', // Auto-hashed by Model casts
                'pin_enabled' => false, // Initially disabled for new accounts
            ]);

            // Auto-create distribution location if needed? No, user just picks branch. 
            // The inventory account acts within the branch.

            $newUser->assignRole($roleName);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Akun inventory berhasil dibuat.',
                'data' => $newUser
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Create Inventory Account Error: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateAccount(Request $request, $id)
    {
        $user = Auth::user();
        $account = \App\Models\User::findOrFail($id);

        // Security Check: Only the creator can edit (unless they have high roles)
        $unrestrictedRoles = ['super_admin', 'owner', 'admin_produk'];
        $userRole = strtolower($user->roles->first()->name ?? '');

        if ($account->created_by !== $user->id && !in_array($userRole, $unrestrictedRoles)) {
            return response()->json(['message' => 'Unauthorized action. Hanya pembuat akun yang bisa mengedit.'], 403);
        }

        $request->validate([
            'name' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'branch_id' => 'nullable|integer',
            'warehouse_id' => 'nullable|integer',
            'online_shop_id' => 'nullable|integer',
            'distributor_id' => 'nullable|integer',
            'photo_inventory' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:10240',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:10240',
            'transaction_pin' => 'nullable|string|size:4',
            'pin_enabled' => 'nullable|boolean'
        ]);

        // Name is locked after creation - do not update name/full_name here

        if ($request->has('branch_id'))
            $account->branch_id = $request->branch_id;
        if ($request->has('warehouse_id'))
            $account->warehouse_id = $request->warehouse_id;
        if ($request->has('online_shop_id'))
            $account->online_shop_id = $request->online_shop_id;
        if ($request->has('distributor_id'))
            $account->distributor_id = $request->distributor_id;

        $account->phone = $request->phone;

        // Support both 'photo' and 'photo_inventory' field names
        $photoField = $request->hasFile('photo') ? 'photo' : ($request->hasFile('photo_inventory') ? 'photo_inventory' : null);

        if ($photoField) {
            $path = $request->file($photoField)->store('account-photos', 'public');

            // Logic: Jika sudah ada foto, kirim ke pending dulu. 
            if ($account->photo_inventory || $account->photo) {
                $account->pending_photo_inventory = $path;
            } else {
                $account->photo_inventory = $path;
                $account->photo = $path; // Sync
            }
        }

        if ($request->has('transaction_pin')) {
            $account->transaction_pin = $request->transaction_pin;
        }

        if ($request->has('pin_enabled')) {
            $account->pin_enabled = (bool) $request->pin_enabled;
        }

        $account->load(['roles', 'createdBy']);
        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Akun inventory berhasil diupdate.',
            'data' => $account
        ]);
    }

    public function togglePin(Request $request, $id)
    {
        $user = Auth::user();
        /** @var \App\Models\User $account */
        $account = User::where('id', $id)->where('created_by', $user->id)->firstOrFail();

        $request->validate(['transaction_pin' => 'required|string|size:4']);

        if (!\Illuminate\Support\Facades\Hash::check($request->transaction_pin, $account->transaction_pin)) {
            return response()->json(['success' => false, 'message' => 'PIN salah.'], 422);
        }

        $account->pin_enabled = !$account->pin_enabled;
        $account->save();

        return response()->json(['success' => true, 'data' => $account->load(['roles', 'createdBy'])]);
    }

    public function requestResetPin(Request $request, $id)
    {
        $user = Auth::user();
        $account = User::where('id', $id)->where('created_by', $user->id)->firstOrFail();

        $account->pin_reset_requested_at = now();
        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Permintaan reset PIN telah dicatat.'
        ]);
    }


    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->hasRole(['super_admin', 'audit', 'owner', 'admin_produk'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $detail = ProductDetail::findOrFail($id);

        $request->validate([
            'imei' => 'required|string|max:40|regex:/^[a-zA-Z0-9]+$/|unique:product_details,imei,' . $id,
            'storage' => 'nullable|string',
            'cost_price' => 'required|numeric',
            'selling_price' => 'numeric',
            'status' => 'required|in:available,sold,retur,missing',
            'notes' => 'nullable|string',
        ]);

        $detail->update($request->only([
            'imei',
            'storage',
            'cost_price',
            'selling_price',
            'status',
            'notes'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Detail inventory updated',
            'data' => $detail
        ]);
    }

    // FIXER: Split merged IMEIs (Temporary Tool)
    public function fixMergedImeis()
    {
        \Illuminate\Support\Facades\Log::info("!!! PUBLIC DIAGNOSTIC HIT !!!", ['time' => now()->toDateTimeString()]);
        $details = ProductDetail::where(function ($q) {
            $q->where('imei', 'like', "%\n%")
                ->orWhere('imei', 'like', "% %")
                ->orWhere('imei', 'like', "%,%");
        })->get();

        $fixedCount = 0;
        $newRowsCount = 0;

        DB::beginTransaction();
        try {
            foreach ($details as $detail) {
                /** @var \App\Models\ProductDetail $detail */
                // Split by newline, comma, space
                $imeis = preg_split('/[\s,\n]+/', $detail->imei, -1, PREG_SPLIT_NO_EMPTY);
                $imeis = array_values(array_unique($imeis));

                if (count($imeis) > 1) {
                    // Valid details extracted
                    foreach ($imeis as $singleImei) {
                        // Check if this single IMEI already exists globally
                        $exists = ProductDetail::where('imei', $singleImei)->exists();

                        if (!$exists) {
                            // Create new row
                            $newDetail = $detail->replicate();
                            $newDetail->imei = $singleImei;
                            $logicalNow = now()->hour < 5 ? now()->subDay() : now();
                            $newDetail->created_at = $logicalNow;
                            $newDetail->updated_at = $logicalNow;
                            $newDetail->save();
                            $newRowsCount++;
                        }
                    }

                    // Delete the original corrupted row
                    $detail->forceDelete();
                    $fixedCount++;
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Fixer ANTIGRAVITY VERSION 1.0',
                'corrupted_rows_removed' => $fixedCount,
                'new_valid_rows_created' => $newRowsCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Get My Inventory Accounts
    public function getMyInventoryUsers()
    {
        // One-time sync for any desynced photos across the platform
        // If photo_inventory exists but photo is null, sync it.
        // If photo exists but photo_inventory is null on an inventory role, sync it.
        $syncNeeded = \App\Models\User::role('inventory')
            ->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNotNull('photo_inventory')->whereNull('photo');
                })->orWhere(function ($sq) {
                    $sq->whereNotNull('photo')->whereNull('photo_inventory');
                })->orWhereRaw('photo != photo_inventory');
            })->get();

        foreach ($syncNeeded as $u) {
            if ($u->photo_inventory && !$u->photo) {
                $u->photo = $u->photo_inventory;
                $u->save();
            } else if ($u->photo && !$u->photo_inventory) {
                $u->photo_inventory = $u->photo;
                $u->save();
            } else if ($u->photo && $u->photo_inventory && $u->photo != $u->photo_inventory) {
                // If both exist but different, we'll favor photo_inventory for inventory accounts
                $u->photo = $u->photo_inventory;
                $u->save();
            }
        }

        $user = Auth::user();
        $inventoryUsers = \App\Models\User::role(['inventory', 'toko_offline'])
            ->with([
                'roles',
                'createdBy' => function ($q) {
                    $q->select('id', 'name', 'full_name');
                }
            ])
            ->where('created_by', $user->id) // Only show accounts created by this user (staff)
            ->where('id', '!=', $user->id)   // Double check to exclude self
            ->where('is_active', true)
            ->select('id', 'name', 'full_name', 'username', 'code_id', 'created_by', 'pin_enabled', 'transaction_pin', 'pin_reset_requested_at', 'photo', 'photo_inventory')
            ->get()
            ->map(function ($u) {
                $u->has_pin = !empty($u->transaction_pin);
                return $u;
            });

        return response()->json($inventoryUsers);

    }
    // Get Filter Options for Faceted Search
    public function getFilterOptions(Request $request)
    {
        $user = Auth::user();
        $type = $request->type ?? 'hp';

        $productNames = [];
        $capacities = [];

        // Common Restriction Logic (Same as Index)
        $applyLocationFilter = function ($query, $tablePrefix = '') use ($user) {
            $unrestrictedRoles = ['super_admin', 'admin_produk', 'owner', 'analist'];
            if (!$user->hasRole($unrestrictedRoles)) {
                $query->where(function ($q) use ($user, $tablePrefix) {
                    $branchIds = $user->getAccessibleBranchIds();
                    $warehouseIds = $user->getAccessibleWarehouseIds();
                    $onlineShopIds = $user->getAccessibleOnlineShopIds();

                    $hasConstraint = false;
                    $colType = $tablePrefix ? $tablePrefix . '.placement_type' : 'placement_type';
                    $colId = $tablePrefix ? $tablePrefix . '.placement_id' : 'placement_id';

                    if (!empty($branchIds)) {
                        $q->orWhere(function ($sub) use ($colType, $colId, $branchIds) {
                            $sub->where($colType, 'branch')
                                ->whereIn($colId, $branchIds);
                        });
                        $hasConstraint = true;
                    }
                    if (!empty($warehouseIds)) {
                        $q->orWhere(function ($sub) use ($colType, $colId, $warehouseIds) {
                            $sub->where($colType, 'warehouse')
                                ->whereIn($colId, $warehouseIds);
                        });
                        $hasConstraint = true;
                    }
                    if (!empty($onlineShopIds)) {
                        $q->orWhere(function ($sub) use ($colType, $colId, $onlineShopIds) {
                            $sub->where($colType, 'online_shop')
                                ->whereIn($colId, $onlineShopIds);
                        });
                        $hasConstraint = true;
                    }

                    if (!$hasConstraint) {
                        $q->whereRaw('0 = 1');
                    }
                });
            }
        };

        if ($type === 'hp') {
            // HP: Query ProductDetail
            // Use distinct on product_id to get product names
            $query = ProductDetail::where('product_details.status', '!=', 'sold')
                ->join('products', 'product_details.product_id', '=', 'products.id')
                ->whereNull('products.deleted_at') // Disambiguate
                ->whereNull('product_details.deleted_at'); // Disambiguate


            $applyLocationFilter($query, 'product_details');

            $productNames = (clone $query)
                ->select('products.name')
                ->distinct()
                ->pluck('products.name')
                ->sort()
                ->values();

            // For capacities, we need RAM and Storage
            // We use select distinct to avoid fetching full objects
            $capacitiesRaw = (clone $query)
                ->select('product_details.ram', 'product_details.storage')
                ->distinct()
                ->get();

            $capacities = $capacitiesRaw->map(function ($item) {
                // Check for dummy RAM '1'
                $ram = ($item->ram == '1') ? null : $item->ram;

                if ($ram && $item->storage)
                    return "{$ram}/{$item->storage}";
                return $item->storage ?: $ram;
            })
                ->filter()
                ->unique()
                ->sort(function ($a, $b) {
                    return strnatcmp($a, $b);
                })
                ->values();

            // Brands
            $brands = (clone $query)
                ->select('products.brand')
                ->distinct()
                ->pluck('products.brand')
                ->filter()
                ->sort()
                ->values();

        } else {
            // Non-HP: Query Inventory
            $query = Inventory::where('quantity', '>', 0)
                ->join('products', 'inventories.product_id', '=', 'products.id');

            $applyLocationFilter($query, 'inventories');

            $productNames = (clone $query)
                ->select('products.name')
                ->distinct()
                ->pluck('products.name')
                ->sort()
                ->values();

            $brands = (clone $query)
                ->select('products.brand')
                ->distinct()
                ->pluck('products.brand')
                ->filter()
                ->sort()
                ->values();
        }

        return response()->json([
            'products' => $productNames,
            'capacities' => $capacities,
            'brands' => $brands
        ]);
    }

    public function getMetaLocations(Request $request)
    {
        $user = Auth::user();
        $isAnalistOnly = $user->hasRole('analist') && !$user->hasRole('super_admin');
        $excludedKeywords = ['trial', 'anu', 'testing', 'huft', 'test'];

        // 1. Accessibility Restrictions
        $osIds = (array) ($user->getAccessibleOnlineShopIds() ?: []);
        $bIds = (array) ($user->getAccessibleBranchIds() ?: []);
        $wIds = (array) ($user->getAccessibleWarehouseIds() ?: []);
        $dIds = (array) ($user->getAccessibleDistributorIds() ?: []);

        if ($user->online_shop_id)
            $osIds[] = $user->online_shop_id;
        if ($user->branch_id)
            $bIds[] = $user->branch_id;
        if ($user->warehouse_id)
            $wIds[] = $user->warehouse_id;

        $osIds = array_unique(array_filter($osIds));
        $bIds = array_unique(array_filter($bIds));
        $wIds = array_unique(array_filter($wIds));
        $dIds = array_unique(array_filter($dIds));

        $unrestricted = $user->hasRole(['super_admin', 'admin_produk', 'owner', 'analist']);

        $fetcher = function ($modelClass, $ids) use ($unrestricted, $isAnalistOnly, $excludedKeywords) {
            return $modelClass::where('is_active', true)
                ->when(!$unrestricted, fn($q) => $q->whereIn('id', $ids))
                ->when($isAnalistOnly, function ($q) use ($excludedKeywords) {
                    foreach ($excludedKeywords as $kw) {
                        $q->where('name', 'not ilike', "%$kw%");
                    }
                })
                ->get(['id', 'name']);
        };

        if (app()->bound(\Laravel\Octane\Contracts\DispatchesTasks::class)) {
            [$branches, $shops, $warehouses, $distributors] = \Laravel\Octane\Facades\Octane::concurrently([
                fn() => $fetcher(\App\Models\Branch::class, $bIds),
                fn() => $fetcher(\App\Models\OnlineShop::class, $osIds),
                fn() => $fetcher(\App\Models\Warehouse::class, $wIds),
                fn() => $fetcher(\App\Models\Distributor::class, $dIds),
            ]);
        } else {
            $branches = $fetcher(\App\Models\Branch::class, $bIds);
            $shops = $fetcher(\App\Models\OnlineShop::class, $osIds);
            $warehouses = $fetcher(\App\Models\Warehouse::class, $wIds);
            $distributors = $fetcher(\App\Models\Distributor::class, $dIds);
        }

        return response()->json([
            'branches' => $branches,
            'online_shops' => $shops,
            'warehouses' => $warehouses,
            'distributors' => $distributors
        ]);
    }

    /**
     * Monitoring Stok Online Shop
     * For leaders assigned to online shops — shows stock grouped by online shop.
     */
    public function monitoringOnlineShop(Request $request)
    {
        $user = $request->user();
        $userRole = strtolower($user->roles->first()->name ?? '');

        $accessibleIds = $user->getAccessibleOnlineShopIds();

        // Only leader/super_admin can access
        if ($userRole === 'leader') {
            if (empty($accessibleIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda belum dikaitkan dengan toko online manapun.'
                ], 403);
            }
        }

        // 1. HP Items (IMEI-based, available)
        $hpQuery = ProductDetail::with(['product'])
            ->where('status', 'available')
            ->where('placement_type', 'online_shop');

        if ($userRole === 'leader') {
            $hpQuery->whereIn('placement_id', $accessibleIds);
        } elseif ($userRole === 'super_admin' && $request->online_shop_id) {
            $hpQuery->where('placement_id', $request->online_shop_id);
        }

        $hpItems = $hpQuery->get();

        // 2. Non-HP Items (quantity-based, qty > 0)
        $nonHpQuery = Inventory::with(['product'])
            ->where('quantity', '>', 0)
            ->where('placement_type', 'online_shop')
            ->whereHas('product', function ($q) {
                $q->where('type', 'non-hp')->orWhere('has_imei', false);
            });

        if ($userRole === 'leader') {
            $nonHpQuery->whereIn('placement_id', $accessibleIds);
        } elseif ($userRole === 'super_admin' && $request->online_shop_id) {
            $nonHpQuery->where('placement_id', $request->online_shop_id);
        }

        $nonHpItems = $nonHpQuery->get();

        // Group by online shop
        $onlineShopNames = \App\Models\OnlineShop::pluck('name', 'id');
        $grouped = [];

        // Process HP
        foreach ($hpItems as $item) {
            $locationName = $onlineShopNames[$item->placement_id] ?? 'Unknown';

            if (!isset($grouped[$locationName])) {
                $grouped[$locationName] = ['location' => $locationName, 'products' => []];
            }

            $brandName = $item->product->brand ?? 'Unknown';
            $typeName = $item->product->name ?? 'Unknown';
            $spec = [];
            if ($item->ram)
                $spec[] = $item->ram;
            if ($item->storage)
                $spec[] = $item->storage;
            $specStr = !empty($spec) ? ' ' . implode('/', $spec) : '';
            $cond = ($item->condition === 'new') ? 'New' : (($item->condition === 'ex_ibox') ? 'Ex iBox' : 'Second');
            $productKey = trim("{$brandName} {$typeName}{$specStr} - {$cond}");

            if (!isset($grouped[$locationName]['products'][$productKey])) {
                $grouped[$locationName]['products'][$productKey] = [
                    'name' => $productKey,
                    'brand' => $brandName,
                    'type_name' => $typeName,
                    'capacity' => implode('/', $spec),
                    'condition_label' => $cond,
                    'qty' => 0,
                    'type' => $item->product->type ?? 'hp',
                    'has_imei' => $item->product->has_imei ?? true,
                    'items' => []
                ];
            }

            $grouped[$locationName]['products'][$productKey]['qty'] += 1;
            $grouped[$locationName]['products'][$productKey]['items'][] = [
                'id' => $item->id,
                'imei' => $item->imei,
                'color' => $item->color,
                'notes' => $item->notes,
                'condition' => $item->condition,
            ];
        }

        // Process Non-HP
        foreach ($nonHpItems as $item) {
            $locationName = $onlineShopNames[$item->placement_id] ?? 'Unknown';

            if (!isset($grouped[$locationName])) {
                $grouped[$locationName] = ['location' => $locationName, 'products' => []];
            }

            $brandName = $item->product->brand ?? 'Unknown';
            $typeName = $item->product->name ?? 'Unknown';
            $cond = 'New';
            $productKey = trim("{$brandName} {$typeName} - {$cond}");

            if (!isset($grouped[$locationName]['products'][$productKey])) {
                $grouped[$locationName]['products'][$productKey] = [
                    'name' => $productKey,
                    'brand' => $brandName,
                    'type_name' => $typeName,
                    'capacity' => null,
                    'condition_label' => $cond,
                    'qty' => 0,
                    'type' => $item->product->type ?? 'non-hp',
                    'has_imei' => false,
                    'items' => []
                ];
            }

            $grouped[$locationName]['products'][$productKey]['qty'] += $item->quantity;
        }

        // Sort and format
        $result = array_values($grouped);
        usort($result, fn($a, $b) => strcmp($a['location'], $b['location']));
        foreach ($result as &$loc) {
            $prodArr = array_values($loc['products']);
            usort($prodArr, fn($a, $b) => strcmp($a['name'], $b['name']));
            $loc['products'] = $prodArr;
        }

        $totalUnits = 0;
        foreach ($result as $loc) {
            foreach ($loc['products'] as $p) {
                $totalUnits += $p['qty'];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'stock' => $result,
                'total_units' => $totalUnits
            ]
        ]);
    }

    /**
     * Monitoring Stok Gudang (Warehouse)
     * For leaders assigned to warehouses — shows stock grouped by warehouse.
     */
    public function monitoringWarehouse(Request $request)
    {
        $user = $request->user();
        $userRole = strtolower($user->roles->first()->name ?? '');

        $accessibleIds = $user->getAccessibleWarehouseIds();

        if ($userRole === 'leader') {
            if (empty($accessibleIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda belum dikaitkan dengan gudang manapun.'
                ], 403);
            }
        }

        // 1. HP Items
        $hpQuery = ProductDetail::with(['product'])
            ->where('status', 'available')
            ->where('placement_type', 'warehouse');

        if ($userRole === 'leader') {
            $hpQuery->whereIn('placement_id', $accessibleIds);
        } elseif ($userRole === 'super_admin' && $request->warehouse_id) {
            $hpQuery->where('placement_id', $request->warehouse_id);
        }

        $hpItems = $hpQuery->get();

        // 2. Non-HP Items
        $nonHpQuery = Inventory::with(['product'])
            ->where('quantity', '>', 0)
            ->where('placement_type', 'warehouse')
            ->whereHas('product', function ($q) {
                $q->where('type', 'non-hp')->orWhere('has_imei', false);
            });

        if ($userRole === 'leader') {
            $nonHpQuery->whereIn('placement_id', $accessibleIds);
        } elseif ($userRole === 'super_admin' && $request->warehouse_id) {
            $nonHpQuery->where('placement_id', $request->warehouse_id);
        }

        $nonHpItems = $nonHpQuery->get();

        // Group by warehouse
        $warehouseNames = \App\Models\Warehouse::pluck('name', 'id');
        $grouped = [];

        // Process HP
        foreach ($hpItems as $item) {
            $locationName = $warehouseNames[$item->placement_id] ?? 'Unknown';

            if (!isset($grouped[$locationName])) {
                $grouped[$locationName] = ['location' => $locationName, 'products' => []];
            }

            $brandName = $item->product->brand ?? 'Unknown';
            $typeName = $item->product->name ?? 'Unknown';
            $spec = [];
            if ($item->ram)
                $spec[] = $item->ram;
            if ($item->storage)
                $spec[] = $item->storage;
            $specStr = !empty($spec) ? ' ' . implode('/', $spec) : '';
            $cond = ($item->condition === 'new') ? 'New' : (($item->condition === 'ex_ibox') ? 'Ex iBox' : 'Second');
            $productKey = trim("{$brandName} {$typeName}{$specStr} - {$cond}");

            if (!isset($grouped[$locationName]['products'][$productKey])) {
                $grouped[$locationName]['products'][$productKey] = [
                    'name' => $productKey,
                    'brand' => $brandName,
                    'type_name' => $typeName,
                    'capacity' => implode('/', $spec),
                    'condition_label' => $cond,
                    'qty' => 0,
                    'type' => $item->product->type ?? 'hp',
                    'has_imei' => $item->product->has_imei ?? true,
                    'items' => []
                ];
            }

            $grouped[$locationName]['products'][$productKey]['qty'] += 1;
            $grouped[$locationName]['products'][$productKey]['items'][] = [
                'id' => $item->id,
                'imei' => $item->imei,
                'color' => $item->color,
                'notes' => $item->notes,
                'condition' => $item->condition,
            ];
        }

        // Process Non-HP
        foreach ($nonHpItems as $item) {
            $locationName = $warehouseNames[$item->placement_id] ?? 'Unknown';

            if (!isset($grouped[$locationName])) {
                $grouped[$locationName] = ['location' => $locationName, 'products' => []];
            }

            $brandName = $item->product->brand ?? 'Unknown';
            $typeName = $item->product->name ?? 'Unknown';
            $cond = 'New';
            $productKey = trim("{$brandName} {$typeName} - {$cond}");

            if (!isset($grouped[$locationName]['products'][$productKey])) {
                $grouped[$locationName]['products'][$productKey] = [
                    'name' => $productKey,
                    'brand' => $brandName,
                    'type_name' => $typeName,
                    'capacity' => null,
                    'condition_label' => $cond,
                    'qty' => 0,
                    'type' => $item->product->type ?? 'non-hp',
                    'has_imei' => false,
                    'items' => []
                ];
            }

            $grouped[$locationName]['products'][$productKey]['qty'] += $item->quantity;
        }

        // Sort and format
        $result = array_values($grouped);
        usort($result, fn($a, $b) => strcmp($a['location'], $b['location']));
        foreach ($result as &$loc) {
            $prodArr = array_values($loc['products']);
            usort($prodArr, fn($a, $b) => strcmp($a['name'], $b['name']));
            $loc['products'] = $prodArr;
        }

        $totalUnits = 0;
        foreach ($result as $loc) {
            foreach ($loc['products'] as $p) {
                $totalUnits += $p['qty'];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'stock' => $result,
                'total_units' => $totalUnits
            ]
        ]);
    }

    public function stockSummary(Request $request)
    {
        $user = Auth::user();

        $osIds = (array) ($user->getAccessibleOnlineShopIds() ?: []);
        $bIds = (array) ($user->getAccessibleBranchIds() ?: []);
        $wIds = (array) ($user->getAccessibleWarehouseIds() ?: []);

        if ($user->online_shop_id)
            $osIds[] = $user->online_shop_id;
        if ($user->branch_id)
            $bIds[] = $user->branch_id;
        if ($user->warehouse_id)
            $wIds[] = $user->warehouse_id;

        $osIds = array_unique(array_filter($osIds));
        $bIds = array_unique(array_filter($bIds));
        $wIds = array_unique(array_filter($wIds));
        $dIds = array_unique(array_filter((array) ($user->getAccessibleDistributorIds() ?: [])));

        $unrestricted = $user->hasRole(['super_admin', 'admin_produk', 'owner', 'analist']);

        // Non-HP Query
        $nonHpQuery = Inventory::with('product')
            ->where('quantity', '>', 0)
            ->whereHas('product', fn($q) => $q->where('type', 'non-hp')->orWhere('has_imei', false));

        // HP Query
        $hpQuery = ProductDetail::with('product')
            ->whereIn('status', ['available', 'booking', 'returned', 'process'])
            ->whereHas('product', fn($q) => $q->where('type', 'hp')->orWhere('has_imei', true));

        // Apply security filter
        $applySecurity = function ($query) use ($unrestricted, $osIds, $bIds, $wIds, $dIds) {
            if (!$unrestricted) {
                $query->where(function ($q) use ($osIds, $bIds, $wIds, $dIds) {
                    $hasConstraint = false;
                    if (!empty($osIds)) {
                        $q->orWhere(fn($sq) => $sq->where('placement_type', 'online_shop')->whereIn('placement_id', $osIds));
                        $hasConstraint = true;
                    }
                    if (!empty($bIds)) {
                        $q->orWhere(fn($sq) => $sq->where('placement_type', 'branch')->whereIn('placement_id', $bIds));
                        $hasConstraint = true;
                    }
                    if (!empty($wIds)) {
                        $q->orWhere(fn($sq) => $sq->where('placement_type', 'warehouse')->whereIn('placement_id', $wIds));
                        $hasConstraint = true;
                    }
                    if (!empty($dIds)) {
                        $q->orWhere(fn($sq) => $sq->where('placement_type', 'distributor')->whereIn('placement_id', $dIds));
                        $hasConstraint = true;
                    }
                    if (!$hasConstraint)
                        $q->whereRaw('0 = 1');
                });
            }
        };

        $applySecurity($nonHpQuery);
        $applySecurity($hpQuery);

        $nonHpItems = $nonHpQuery->get();
        $hpItems = $hpQuery->get();

        $combined = [];
        $totalQty = 0;

        foreach ($nonHpItems as $item) {
            $product = $item->product;
            if (!$product)
                continue;

            $key = 'nonhp_' . $product->id;

            if (!isset($combined[$key])) {
                $combined[$key] = [
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'storage' => null,
                    'condition' => null,
                    'quantity' => 0,
                ];
            }
            $combined[$key]['quantity'] += $item->quantity;
            $totalQty += $item->quantity;
        }

        foreach ($hpItems as $item) {
            $product = $item->product;
            if (!$product)
                continue;

            $storageLabel = implode('/', array_filter([$item->ram, $item->storage]));
            if (empty($storageLabel))
                $storageLabel = null;

            $conditionLabel = $item->condition === 'new' ? 'Baru' : ($item->condition === 'second' ? 'Second' : ($item->condition === 'ex_ibox' ? 'Ex iBox' : $item->condition));

            $key = 'hp_' . $product->id . '_' . $storageLabel . '_' . $conditionLabel;

            if (!isset($combined[$key])) {
                $combined[$key] = [
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'storage' => $storageLabel,
                    'condition' => $conditionLabel,
                    'quantity' => 0,
                ];
            }
            $combined[$key]['quantity'] += 1;
            $totalQty += 1;
        }

        $combinedValues = array_values($combined);
        usort($combinedValues, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return response()->json([
            'success' => true,
            'stats' => [
                'totalItems' => count($combinedValues),
                'totalQuantity' => $totalQty
            ],
            'items' => $combinedValues
        ]);
    }

    public function pendingPhotos()
    {
        // Only return users who have pending_photo_inventory to avoid duplication with standard user photos
        $users = User::with(['roles', 'branch', 'warehouse', 'onlineShop'])
            ->whereNotNull('pending_photo_inventory')
            ->select('id', 'name', 'full_name', 'username', 'photo_inventory', 'pending_photo', 'pending_photo_inventory', 'branch_id', 'warehouse_id', 'online_shop_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function approvePhoto($id)
    {
        $user = User::findOrFail($id);

        // Pilih salah satu yang ada datanya
        $pendingPath = $user->pending_photo_inventory ?: $user->pending_photo;

        if (!$pendingPath) {
            return response()->json(['message' => 'Tidak ada foto yang menunggu persetujuan.'], 400);
        }

        // Hapus foto lama dari storage
        if ($user->photo_inventory && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->photo_inventory)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo_inventory);
        }
        if ($user->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->photo) && $user->photo !== $user->photo_inventory) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo);
        }

        // Pindahkan pending ke asli (Sync keduanya)
        $user->photo_inventory = $pendingPath;
        $user->photo = $pendingPath;

        // Kosongkan semua pending
        $user->pending_photo_inventory = null;
        $user->pending_photo = null;

        $user->save();

        return response()->json(['success' => true, 'message' => 'Foto berhasil disetujui.']);
    }

    public function rejectPhoto($id)
    {
        $user = User::findOrFail($id);
        if ($user->pending_photo_inventory) {
            // Hapus file pending dari storage
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($user->pending_photo_inventory)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->pending_photo_inventory);
            }
            $user->pending_photo_inventory = null;
            $user->save();
        }

        return response()->json(['success' => true, 'message' => 'Perubahan foto inventory ditolak.']);
    }

    public function destroyAccount($id)
    {
        $user = Auth::user();
        $account = \App\Models\User::findOrFail($id);

        // Security Check: Only creator or high roles
        $unrestrictedRoles = ['super_admin', 'owner', 'admin_produk', 'analist'];
        if ($account->created_by !== $user->id && !$user->hasRole($unrestrictedRoles)) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        // Check for history
        $hasHistory = \App\Models\InventoryLog::where('user_id', $account->id)->exists() ||
            \App\Models\ProductDetail::where('user_id', $account->id)->exists() ||
            \App\Models\StockOut::where('inventory_user_id', $account->id)->exists() ||
            \App\Models\StockOut::where('confirmed_by', $account->id)->exists();

        if ($hasHistory) {
            // Soft delete/Archive by deactivating
            $account->update(['is_active' => false]);
            return response()->json(['message' => 'Akun dinonaktifkan karena memiliki riwayat transaksi.', 'status' => 'archived']);
        }

        // Hard delete if clean
        $account->delete();
        return response()->json(['message' => 'Akun berhasil dihapus permanen.', 'status' => 'deleted']);
    }

    /**
     * Void a Stock In transaction.
     * For HP: Deletes the ProductDetail (soft delete).
     * For Non-HP: Decrements Inventory quantity and removes the Log.
     */
    public function voidStockIn(Request $request, $id)
    {
        $user = Auth::user();
        $type = $request->input('type', 'hp');

        DB::beginTransaction();
        try {
            if ($type === 'hp') {
                $item = ProductDetail::findOrFail($id);

                // Check authorization
                $unrestrictedRoles = ['super_admin', 'owner', 'audit', 'admin_produk', 'analist'];
                if ($item->user_id !== $user->id && !$user->hasRole($unrestrictedRoles)) {
                    throw new \Exception('Anda tidak memiliki izin untuk menghapus item ini.');
                }

                if ($item->status !== 'available') {
                    throw new \Exception('Hanya barang dengan status "Available" yang dapat dihapus.');
                }

                // Check if it has been used in ANY stock out that isn't the audit one
                $usageCount = $item->stockOuts()->where('category', '!=', 'barang_masuk')->count();
                if ($usageCount > 0) {
                    throw new \Exception('Barang ini sudah memiliki riwayat transaksi lain dan tidak dapat dihapus.');
                }

                // Find audit stock out and mark as cancelled
                $auditStockOut = $item->stockOuts()->where('category', 'barang_masuk')->first();
                if ($auditStockOut) {
                    $auditStockOut->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $user->id]);
                }

                $item->delete(); // Soft delete
            } else {
                $log = InventoryLog::findOrFail($id);

                if ($log->type !== 'in') {
                    throw new \Exception('Hanya log "Stock In" yang dapat dihapus melalui menu ini.');
                }

                // Check authorization
                $unrestrictedRoles = ['super_admin', 'owner', 'audit', 'admin_produk', 'analist'];
                if ($log->user_id !== $user->id && !$user->hasRole($unrestrictedRoles)) {
                    throw new \Exception('Anda tidak memiliki izin untuk menghapus log ini.');
                }

                // Identify Inventory Source to decrement
                $invQuery = Inventory::where('product_id', $log->product_id)
                    ->where('user_id', $log->user_id);

                if ($log->branch_id)
                    $invQuery->where('placement_type', 'branch')->where('placement_id', $log->branch_id);
                elseif ($log->warehouse_id)
                    $invQuery->where('placement_type', 'warehouse')->where('placement_id', $log->warehouse_id);
                elseif ($log->online_shop_id)
                    $invQuery->where('placement_type', 'online_shop')->where('placement_id', $log->online_shop_id);

                $inventory = $invQuery->first();

                if (!$inventory || $inventory->quantity < $log->quantity) {
                    throw new \Exception('Stok saat ini tidak mencukupi untuk melakukan pembatalan (barang mungkin sudah terjual/keluar).');
                }

                // Revert quantity
                $inventory->decrement('quantity', $log->quantity);

                // Create a reverse log for audit trail (instead of just deleting)
                InventoryLog::create([
                    'product_id' => $log->product_id,
                    'type' => 'out',
                    'quantity' => $log->quantity,
                    'balance_after' => $inventory->quantity,
                    'description' => "Void Stock In (Ref Log ID: {$log->id})",
                    'reference_id' => 'VOID-IN-' . time(),
                    'user_id' => $user->id,
                    'branch_id' => $log->branch_id,
                    'warehouse_id' => $log->warehouse_id,
                    'online_shop_id' => $log->online_shop_id,
                    'notes' => 'Pembatalan stok masuk'
                ]);

                // We can also mark the original log as voided if we had a column, 
                // but setting description or deleting is common. Let's soft-delete it.
                $log->delete();
            }

            DB::commit();
            return response()->json(['message' => 'Stok masuk berhasil dibatalkan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
