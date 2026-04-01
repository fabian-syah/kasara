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

class InventoryController extends Controller
{
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

        $unrestricted = $user->hasRole(['super_admin', 'admin_produk', 'audit', 'analist', 'owner']);

        // 2. Base Query
        if ($type === 'non-hp') {
            $query = Inventory::with(['product', 'user', 'user.distributor', 'latestLog', 'latestLog.distributor'])
                ->where('quantity', '>', 0)
                ->whereHas('product', function ($q) {
                    $q->where('type', 'non-hp')->orWhere('has_imei', false);
                });
        } else {
            $query = ProductDetail::with(['product', 'distributor', 'user', 'refund', 'refund.paymentMethod', 'tradeIn', 'tradeIn.paymentMethod'])
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

        // 4. Frontend Filters
        if ($request->filled('online_shop_id'))
            $query->where('placement_type', 'online_shop')->where('placement_id', $request->online_shop_id);
        if ($request->filled('branch_id'))
            $query->where('placement_type', 'branch')->where('placement_id', $request->branch_id);
        if ($request->filled('warehouse_id'))
            $query->where('placement_type', 'warehouse')->where('placement_id', $request->warehouse_id);

        // Explicit placement_type filter (used for Monitoring pages)
        if ($request->filled('placement_type')) {
            $query->where('placement_type', $request->placement_type);
        }

        // Explicit distributor_id filter (used for Distributor Monitoring)
        if ($request->filled('distributor_id')) {
            $query->where('placement_type', 'distributor')->where('placement_id', $request->distributor_id);
        }

        if ($request->filled('brand')) {
            $brands = explode(',', $request->brand);
            $query->whereHas('product', fn($q) => $q->whereIn('brand', $brands));
        }

        // NEW: Product Name Filter
        if ($request->filled('product')) {
            $products = explode(',', $request->product);
            $query->whereHas('product', fn($q) => $q->whereIn('name', $products));
        }

        if ($type === 'hp') {
            // FIXED: Smart Capacity Filter (Handles RAM/Storage or just Storage)
            if ($request->filled('capacity')) {
                $caps = explode(',', $request->capacity);
                $query->where(function ($q) use ($caps) {
                    foreach ($caps as $cap) {
                        $cap = trim($cap);
                        if (str_contains($cap, '/')) {
                            // Format: RAM/Storage (e.g. "8GB/128GB")
                            [$ram, $storage] = explode('/', $cap);
                            $q->orWhere(fn($sq) => $sq->where('ram', $ram)->where('storage', $storage));
                        } else {
                            // Fallback: Check storage usually
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
                if ($type === 'hp')
                    $q->where('imei', 'like', "%$s%");
                $q->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%$s%")->orWhere('brand', 'like', "%$s%"));
            });
        }

        // 5. Diagnostics
        if ($request->has('debug')) {
            return response()->json([
                'diagnostic' => [
                    'os_ids' => $osIds,
                    'hp_count' => ($type === 'hp') ? (clone $query)->count() : 0,
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings()
                ]
            ]);
        }

        // 6. Pagination & Response
        if ($type === 'non-hp') {
            $query->orderBy('id', 'desc');
            $rawItems = $query->get();
            $grouped = collect();

            foreach ($rawItems as $item) {
                // Generate unique key for grouping
                $key = "{$item->product_id}-{$item->placement_type}-{$item->placement_id}-{$item->user_id}";
                if (!$grouped->has($key)) {
                    $grouped->put($key, clone $item);
                } else {
                    $existing = $grouped->get($key);
                    $existing->quantity += $item->quantity;
                    $existing->id = max($existing->id, $item->id);
                }
            }
            $grouped = $grouped->sortByDesc('id')->values();

            // Manual pagination
            $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
            $perPage = 20;
            $items = new \Illuminate\Pagination\LengthAwarePaginator(
                $grouped->forPage($page, $perPage)->values(),
                $grouped->count(),
                $perPage,
                $page,
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => $request->query()]
            );
        } else {
            $items = $query->latest()->paginate(20);
        }

        $items->getCollection()->transform(function ($item) use ($type, $request) {
            $item->placement_name = $item->placement ? $item->placement->name : ($item->placement_type . ' #' . $item->placement_id);

            if ($type === 'non-hp') {
                // Fetch latest log manually to guarantee accuracy against user_id and product_id
                $log = \App\Models\InventoryLog::with('distributor')
                    ->where('product_id', $item->product_id)
                    ->where('user_id', $item->user_id)
                    ->where('type', 'in')
                    ->latest()
                    ->first();

                $item->latest_distributor = $log && $log->distributor ? $log->distributor->name : null;
                $item->latest_supplier = $log ? $log->supplier_name : null;

                // Fallback to user's distributor if nothing found in log
                if (!$item->latest_distributor && !$item->latest_supplier && $item->user && $item->user->distributor) {
                    $item->latest_distributor = $item->user->distributor->name;
                }
            }

            // Attach retur stock-out data when fetching service/retur items
            if ($request->status === 'service' && $type === 'hp') {
                $returStockOut = $item->stockOuts()->where('category', 'retur')->latest()->first();
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

        $res = $items->toArray();
        $res['total_value'] = $type === 'hp' ? (clone $query)->sum('selling_price') : 0; // Simplified for safety

        return response()->json($res);
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        $type = $request->type ?? 'hp';

        if ($type === 'non-hp') {
            $query = Inventory::with(['product', 'user', 'placement']);
            // Apply Filters (logic similar to index)
            if ($request->search) {
                $search = $request->search;
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
                });
            }
        } else {
            $query = ProductDetail::with(['product', 'distributor', 'user']);
            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('imei', 'like', "%{$search}%")->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")->orWhere('brand', 'like', "%{$search}%");
                    });
                });
            }
        }

        // Common Filters (simplified for export)
        if ($request->branch_id) {
            $query->where('placement_type', 'branch')->where('placement_id', $request->branch_id);
        }
        if ($request->online_shop_id) {
            $query->where('placement_type', 'online_shop')->where('placement_id', $request->online_shop_id);
        }
        if ($request->warehouse_id) {
            $query->where('placement_type', 'warehouse')->where('placement_id', $request->warehouse_id);
        }
        if ($request->brand) {
            $brandArr = explode(',', $request->brand);
            $query->whereHas('product', function ($q) use ($brandArr) {
                $q->whereIn('brand', $brandArr);
            });
        }
        if ($request->product) {
            $prodArr = explode(',', $request->product);
            $query->whereHas('product', function ($q) use ($prodArr) {
                $q->whereIn('name', $prodArr);
            });
        }
        if ($request->condition && $request->condition !== 'all' && $type === 'hp') {
            $query->where('condition', $request->condition);
        }
        // Always filter for available stock if not explicitly searching for something else
        if ($type === 'non-hp') {
            $query->where('status', 'available')->where('quantity', '>', 0);
        } else {
            $query->where('status', 'available');
        }

        if ($request->stock_status && $request->stock_status !== 'all') {
            $query->where('status', $request->stock_status);
        }

        // Role restriction (Unrestricted can see all, others only their accessible ones)
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'analist', 'owner', 'audit'];
        if (!$user->hasRole($unrestrictedRoles)) {
            $query->where(function ($q) use ($user) {
                $branchIds = $user->getAccessibleBranchIds();
                $warehouseIds = $user->getAccessibleWarehouseIds();
                $shopIds = $user->getAccessibleOnlineShopIds();
                if (!empty($branchIds))
                    $q->orWhere(fn($sq) => $sq->where('placement_type', 'branch')->whereIn('placement_id', $branchIds));
                if (!empty($warehouseIds))
                    $q->orWhere(fn($sq) => $sq->where('placement_type', 'warehouse')->whereIn('placement_id', $warehouseIds));
                if (!empty($shopIds))
                    $q->orWhere(fn($sq) => $sq->where('placement_type', 'online_shop')->whereIn('placement_id', $shopIds));
            });
        }

        $items = $query->latest()->get();

        $callback = function () use ($items, $type) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            if ($type === 'hp') {
                fputcsv($file, ['Merek', 'Produk', 'Kapasitas', 'Kondisi', 'IMEI', 'Lokasi', 'Distributor', 'Harga Jual', 'Status', 'Akun Inventory']);
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->product->brand ?? '-',
                        $item->product->name ?? '-',
                        $item->storage ?? '-',
                        $item->condition === 'new' ? 'Baru' : ($item->condition === 'ex_ibox' ? 'Ex iBox' : 'Bekas'),
                        "'" . ($item->imei ?? '-'), // Prefix with ' to prevent Excel scientific notation
                        $item->placement_type . ' #' . $item->placement_id,
                        $item->distributor->name ?? ($item->supplier_name ?? '-'),
                        $item->selling_price ?? 0,
                        $item->status,
                        $item->user->name ?? '-',
                    ]);
                }
            } else {
                fputcsv($file, ['Merek', 'Produk', 'Lokasi', 'Stok', 'Distributor / Supplier', 'Akun Inventory', 'Catatan']);
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->product->brand ?? '-',
                        $item->product->name ?? '-',
                        $item->placement_type . ' #' . $item->placement_id,
                        $item->quantity ?? 0,
                        $item->latest_distributor ?? ($item->latest_supplier ?? '-'),
                        $item->user->name ?? '-',
                        $item->notes ?? '-',
                    ]);
                }
            }
            fclose($file);
        };

        $filename = 'inventory-' . $type . '-' . now()->format('Y-m-d') . '.csv';
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        } elseif ($request->month && $request->year) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
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

        // DATE FILTER
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        } elseif ($request->month && $request->year) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
        }

        // DATE FILTER FOR INVENTORY ROLE (Current & Last Month Only)
        if ($user->hasRole('inventory')) {
            $startDate = \Carbon\Carbon::now()->subMonth()->startOfMonth();
            $query->where('created_at', '>=', $startDate);
        }

        return response()->json($query->latest()->paginate(20));
    }

    // Export Stock In History as CSV
    public function exportStockInHistory(Request $request)
    {
        $user = Auth::user();
        $type = $request->type ?? 'hp';

        if ($type === 'non-hp') {
            $query = InventoryLog::with(['product', 'user', 'distributor'])->where('type', 'in');
            if ($request->search) {
                $lowKeyword = strtolower($request->search);
                $query->where(function ($q) use ($lowKeyword) {
                    $q->whereHas('product', function ($pq) use ($lowKeyword) {
                        $pq->whereRaw('LOWER(name) LIKE ?', ["%{$lowKeyword}%"])
                            ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$lowKeyword}%"]);
                    })
                        ->orWhereRaw('LOWER(description) LIKE ?', ["%{$lowKeyword}%"]);
                });
            }
        } else {
            $query = ProductDetail::with(['product', 'distributor', 'user']);
            if ($request->search) {
                $lowKeyword = strtolower($request->search);
                $query->where(function ($q) use ($lowKeyword) {
                    $q->whereRaw('LOWER(imei) LIKE ?', ["%{$lowKeyword}%"])
                        ->orWhereHas('product', function ($sq) use ($lowKeyword) {
                            $sq->whereRaw('LOWER(name) LIKE ?', ["%{$lowKeyword}%"])
                                ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$lowKeyword}%"]);
                        });
                });
            }
        }

        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        } elseif ($request->month && $request->year) {
            $query->whereMonth('created_at', $request->month)->whereYear('created_at', $request->year);
        }

        $items = $query->latest()->get();

        $callback = function () use ($items, $type) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($type === 'hp') {
                fputcsv($file, ['Tanggal', 'Produk', 'SKU', 'IMEI', 'RAM', 'Storage', 'Kondisi', 'Harga Modal', 'Harga Jual', 'Distributor', 'Diinput Oleh']);
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->created_at->format('Y-m-d H:i'),
                        $item->product->name ?? '-',
                        $item->product->sku ?? '-',
                        $item->imei ?? '-',
                        $item->ram ?? '-',
                        $item->storage ?? '-',
                        $item->condition === 'new' ? 'Baru' : ($item->condition === 'ex_ibox' ? 'Ex iBox' : 'Bekas'),
                        $item->cost_price ?? 0,
                        $item->selling_price ?? 0,
                        $item->distributor->name ?? ($item->supplier_name ?? '-'),
                        $item->user->name ?? '-',
                    ]);
                }
            } else {
                fputcsv($file, ['Tanggal', 'Produk', 'SKU', 'Quantity', 'Deskripsi', 'Distributor', 'Diinput Oleh']);
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->created_at->format('Y-m-d H:i'),
                        $item->product->name ?? '-',
                        $item->product->sku ?? '-',
                        $item->quantity ?? 0,
                        $item->description ?? '-',
                        $item->distributor->name ?? ($item->supplier_name ?? '-'),
                        $item->user->name ?? '-',
                    ]);
                }
            }
            fclose($file);
        };

        $filename = 'stok-masuk-' . ($type) . '-' . now()->format('Y-m-d') . '.csv';
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // Export Stock Out History as CSV
    public function exportStockOutHistory(Request $request)
    {
        $query = InventoryLog::with(['product', 'user', 'distributor'])->where('type', 'out');

        if ($request->search) {
            $lowKeyword = strtolower($request->search);

            $query->where(function ($q) use ($lowKeyword) {
                $q->whereHas('product', function ($pq) use ($lowKeyword) {
                    $pq->whereRaw('LOWER(name) LIKE ?', ["%{$lowKeyword}%"])
                        ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$lowKeyword}%"]);
                })
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$lowKeyword}%"]);
            });
        }

        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        } elseif ($request->month && $request->year) {
            $query->whereMonth('created_at', $request->month)->whereYear('created_at', $request->year);
        }

        $items = $query->latest()->get();

        $callback = function () use ($items) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Tanggal', 'Produk', 'SKU', 'Quantity', 'Deskripsi', 'Diinput Oleh']);
            foreach ($items as $item) {
                fputcsv($file, [
                    $item->created_at->format('Y-m-d H:i'),
                    $item->product->name ?? '-',
                    $item->product->sku ?? '-',
                    $item->quantity ?? 0,
                    $item->description ?? '-',
                    $item->user->name ?? '-',
                ]);
            }
            fclose($file);
        };

        $filename = 'stok-keluar-' . now()->format('Y-m-d') . '.csv';
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function stockIn(Request $request)
    {
        $request->validate([
            'product_id' => 'required_if:type,hp|nullable|exists:products,id',
            'distributor_id' => 'nullable|exists:distributors,id',
            'type' => 'required|in:hp,non-hp,HP,NON-HP',
            'transaction_pin' => 'nullable|string|size:4',

            'placement_type' => 'required|in:branch,warehouse,online_shop,distributor',
            'placement_id' => 'required|integer',

            // Multi-item support for Non-HP
            'items' => 'required_if:type,non-hp|array',
            'items.*.brand_name' => 'nullable|string',
            'items.*.type_name' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.selling_price' => 'nullable|numeric|min:0',

            // Fallback for Legacy/Single Input
            'quantity' => 'required_without:items|nullable|integer|min:1',

            // For HP
            'imeis' => 'required_if:type,hp|array',
            'imeis.*.imei' => ['required_if:type,hp', 'string', 'distinct', 'max:20', 'regex:/^[0-9]+$/'], 
            'imeis.*.ram' => 'nullable|string',
            'imeis.*.storage' => 'nullable|string',
            'storage' => 'nullable|string', 
            'imeis.*.condition' => 'required_if:type,hp|in:new,second,ex_ibox',
            'imeis.*.cost_price' => 'nullable|numeric|min:0',
            'imeis.*.selling_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
        ]);

        $request->merge(['type' => strtolower($request->type)]);
        $user = Auth::user();

        // PIN Verification - Only for Sales role if enabled
        if ($user->hasRole('sales') && $user->pin_enabled) {
            if (!$request->transaction_pin || !\Illuminate\Support\Facades\Hash::check($request->transaction_pin, $user->transaction_pin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN transaksi salah atau diperlukan.'
                ], 422);
            }
        }

        // Determine Ownership User (Who 'owns' the stock)
        // If inventory_user_id is passed (from shared account selection), use that.
        // Otherwise use logged in user.
        $ownerUserId = $user->id;
        if ($request->has('inventory_user_id') && $request->inventory_user_id) {
            // Verify access? For now assume if they can see it they can use it (filtered by UI)
            $ownerUserId = $request->inventory_user_id;
        }

        DB::beginTransaction();

        try {
            $distributorId = $request->distributor_id;
            $supplierName = null;

            if (!$distributorId && $request->new_distributor_name) {
                // Use manual name, do not create distributor record
                $supplierName = $request->new_distributor_name;
                $distributorId = null;
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
                $items = $request->items ?? [[
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'selling_price' => $request->selling_price,
                    // Brand/Type for auto-creation if product_id is null
                    'brand_id' => $request->brand_id,
                    'brand_name' => $request->brand_name,
                    'type_name' => $request->type_name,
                ]];

                $results = [];

                foreach ($items as $item) {
                    $pId = $item['product_id'] ?? null;
                    
                    if (!$pId && !empty($item['type_name'])) {
                        // AUTO CREATE PRODUCT IF NOT EXISTS
                        $brandName = $item['brand_name'] ?? 'Unknown';
                        $typeName = $item['type_name'];
                        
                        $prod = Product::firstOrCreate(
                            [
                                'name' => $typeName,
                                'brand' => $brandName,
                                'type' => 'non-hp'
                            ],
                            [
                                'sku' => 'NHP-' . strtoupper(\Illuminate\Support\Str::random(8)),
                                'category' => 'NON HP / NON IMEI',
                                'has_imei' => false,
                                'price' => $item['selling_price'] ?? 0,
                                'brand_id' => $item['brand_id'] ?? null
                            ]
                        );
                        $pId = $prod->id;
                    }

                    if (!$pId) continue;

                    $inventory = Inventory::firstOrCreate(
                        [
                            'product_id' => $pId,
                            'placement_type' => $request->placement_type,
                            'placement_id' => $request->placement_id,
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
                        'category' => 'barang_masuk',
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
                    } catch (\Exception $e) { \Log::error("Event fail: " . $e->getMessage()); }
                }

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
                        $existing->created_at = now();
                        $existing->updated_at = now();
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
                }

                // Log
                if ($inserted_count > 0) {
                    InventoryLog::create([
                        'product_id' => $product->id,
                        'branch_id' => $request->placement_type === 'branch' ? $request->placement_id : null,
                        'warehouse_id' => $request->placement_type === 'warehouse' ? $request->placement_id : null,
                        'online_shop_id' => $request->placement_type === 'online_shop' ? $request->placement_id : null,
                        'user_id' => $ownerUserId, // Use Owner User ID
                        'distributor_id' => $distributorId,
                        'supplier_name' => $supplierName,
                        'type' => 'in',
                        'quantity' => $inserted_count,
                        'balance_after' => ProductDetail::where('product_id', $product->id)->where('status', 'available')->count(),
                        'description' => "Stock In {$inserted_count} units (HP) from " . ($supplierName ?: "Distributor"),
                        'reference_id' => 'STOCK-IN-HP-' . time(),
                        'notes' => $request->notes,
                    ]);

                    // Create StockOut Record for Audit Purposes (Manual Stock In)
                    $stockOutAudit = StockOut::create([
                        'receipt_id' => 'IN-HP-' . strtoupper(\Illuminate\Support\Str::random(6)),
                        'category' => 'barang_masuk',
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

        $user = Auth::user();

        // PIN Verification - Only for Sales role if enabled
        if ($user->hasRole('sales') && $user->pin_enabled) {
            if (!$request->transaction_pin || !\Illuminate\Support\Facades\Hash::check($request->transaction_pin, $user->transaction_pin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN transaksi salah atau diperlukan.'
                ], 422);
            }
        }

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
        ]);

        $user = Auth::user();
        if (!$user->branch_id && !$user->warehouse_id && !$user->online_shop_id && !$user->distributor_id && !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'Anda tidak memiliki lokasi fisik untuk membuat akun inventory.'], 403);
        }

        // Generate Credentials
        // Use microtime to collision avoidance
        $timestamp = microtime(true);
        $random = rand(100, 999);
        // Normalize timestamp for string
        $tsString = str_replace('.', '', (string) $timestamp);

        $username = 'inv.' . substr($tsString, -8) . '.' . $random;
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
                'code_id' => 'INV-' . substr($tsString, -10) . $random,
                'email' => $email,
                'password' => $password,
                'branch_id' => $request->branch_id ?? $user->branch_id,
                'warehouse_id' => $request->warehouse_id ?? $user->warehouse_id,
                'online_shop_id' => $request->online_shop_id ?? $user->online_shop_id,
                'distributor_id' => $request->distributor_id ?? $user->distributor_id,
                'created_by' => $user->id, // Mark ownership
                'is_active' => true,
                'theme_color' => 'default',
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

        // Security Check: Only the creator can edit
        if ($account->created_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'name' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'branch_id' => 'nullable|integer',
            'warehouse_id' => 'nullable|integer',
            'online_shop_id' => 'nullable|integer',
            'distributor_id' => 'nullable|integer',
            'photo_inventory' => 'nullable|image|max:2048', // 2MB Max
        ]);

        if ($request->has('name')) {
            $account->name = $request->name;
            $account->full_name = $request->name;
        }

        if ($request->has('branch_id'))
            $account->branch_id = $request->branch_id;
        if ($request->has('warehouse_id'))
            $account->warehouse_id = $request->warehouse_id;
        if ($request->has('online_shop_id'))
            $account->online_shop_id = $request->online_shop_id;
        if ($request->has('distributor_id'))
            $account->distributor_id = $request->distributor_id;

        $account->phone = $request->phone;

        if ($request->hasFile('photo_inventory')) {
            // Delete old photo if exists
            if ($account->photo_inventory && \Illuminate\Support\Facades\Storage::exists('public/' . $account->photo_inventory)) {
                \Illuminate\Support\Facades\Storage::delete('public/' . $account->photo_inventory);
            }
            $path = $request->file('photo_inventory')->store('account-photos', 'public');
            $account->photo_inventory = $path;

            // Sync with photo to ensure User Management views match
            $account->photo = $path;
        }

        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Akun inventory berhasil diupdate.',
            'data' => $account
        ]);
    }


    public function update(Request $request, $id)
    {
        $detail = ProductDetail::findOrFail($id);

        $request->validate([
            'imei' => 'required|string|max:20|regex:/^[a-zA-Z0-9]+$/|unique:product_details,imei,' . $id,
            'storage' => 'nullable|string',
            'cost_price' => 'required|numeric',
            'selling_price' => 'numeric',
            'status' => 'required|in:available,sold,retur,missing',
        ]);

        $detail->update($request->only([
            'imei',
            'storage',
            'cost_price',
            'selling_price',
            'status'
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
                            $newDetail->created_at = now();
                            $newDetail->updated_at = now();
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
        $inventoryUsers = \App\Models\User::role(['inventory', 'sales'])
            ->with(['roles', 'createdBy' => function($q) {
                $q->select('id', 'name', 'full_name');
            }])
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhere('id', $user->id);
            })
            ->where('is_active', true)
            ->select('id', 'name', 'full_name', 'username', 'code_id', 'created_by')
            ->get();

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
            $unrestrictedRoles = ['super_admin', 'admin_produk', 'audit', 'analist', 'owner'];
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

        $unrestricted = $user->hasRole(['super_admin', 'admin_produk', 'audit', 'analist', 'owner']);

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
}
