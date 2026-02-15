<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\Distributor;
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
        $type = $request->type ?? 'hp'; // Default to HP (ProductDetail)

        if ($type === 'non-hp') {
            // ============================================
            // NON-HP (Quantity Based)
            // ============================================
            // ============================================
            // NON-HP (Quantity Based)
            // ============================================
            $query = Inventory::with(['product', 'user'])
                ->where('quantity', '>', 0); // Hide items with 0 stock

            // Filter by Branch/Placement
            // Filter by Branch/Placement
            $unrestrictedRoles = ['super_admin', 'admin_produk', 'audit', 'analist', 'owner'];
            if (!in_array($user->role, $unrestrictedRoles)) {
                $query->where(function ($q) use ($user) {
                    $hasConstraint = false;
                    if ($user->branch_id) {
                        $q->orWhere(function ($sub) use ($user) {
                            $sub->where('placement_type', 'branch')
                                ->where('placement_id', $user->branch_id);
                        });
                        $hasConstraint = true;
                    }
                    if ($user->warehouse_id) {
                        $q->orWhere(function ($sub) use ($user) {
                            $sub->where('placement_type', 'warehouse')
                                ->where('placement_id', $user->warehouse_id);
                        });
                        $hasConstraint = true;
                    }
                    if ($user->online_shop_id) {
                        $q->orWhere(function ($sub) use ($user) {
                            $sub->where('placement_type', 'online_shop')
                                ->where('placement_id', $user->online_shop_id);
                        });
                        $hasConstraint = true;
                    }

                    if (!$hasConstraint) {
                        // User has restrictions but no location assigned? Block access.
                        $q->whereRaw('0 = 1');
                    }
                });
            }
            if ($request->has('branch_id')) {
                $query->where('placement_type', 'branch')
                    ->where('placement_id', $request->branch_id);
            }

            // Search
            if ($request->search) {
                $search = $request->search;
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            // Filter by Placement Type
            if ($request->has('placement_type')) {
                $query->where('placement_type', $request->placement_type);
            }

            $items = $query->latest()->paginate(20);

            // Transform
            $items->getCollection()->transform(function ($item) {
                // Accessor for placement_name should be added to Inventory model or done here
                // Inventory model typically doesn't have placement relation defined yet in typical setup, let's allow basic mapping
                if ($item->placement_type == 'branch') {
                    $item->placement_name = \App\Models\Branch::find($item->placement_id)?->name;
                } elseif ($item->placement_type == 'warehouse') {
                    $item->placement_name = \App\Models\Warehouse::find($item->placement_id)?->name;
                } elseif ($item->placement_type == 'online_shop') {
                    $item->placement_name = \App\Models\OnlineShop::find($item->placement_id)?->name;
                }

                // Add Last Supplier Info
                $lastLog = \App\Models\InventoryLog::where('product_id', $item->product_id)
                    ->where('user_id', $item->user_id)
                    ->where('type', 'in')
                    ->latest()
                    ->first();

                $item->latest_supplier = $lastLog ? ($lastLog->supplier_name ?? ($lastLog->distributor ? $lastLog->distributor->name : null)) : null;
                $item->notes = $lastLog ? $lastLog->notes : null;

                return $item;
            });

            return response()->json($items);

        } else {
            // ============================================
            // HP (IMEI Based) - Existing Logic
            // ============================================
            $query = ProductDetail::with(['product', 'distributor', 'user']);

            // BRANCH FILTER:
            $unrestrictedRoles = ['super_admin', 'admin_produk', 'audit', 'analist', 'owner'];

            // If user is NOT in unrestricted roles AND has a placement, lock them to their placement
            // If user is NOT in unrestricted roles AND has a placement, lock them to their placement
            if (!in_array($user->role, $unrestrictedRoles)) {
                $query->where(function ($q) use ($user) {
                    $hasConstraint = false;
                    if ($user->branch_id) {
                        $q->orWhere(function ($sub) use ($user) {
                            $sub->where('placement_type', 'branch')
                                ->where('placement_id', $user->branch_id);
                        });
                        $hasConstraint = true;
                    }
                    if ($user->warehouse_id) {
                        $q->orWhere(function ($sub) use ($user) {
                            $sub->where('placement_type', 'warehouse')
                                ->where('placement_id', $user->warehouse_id);
                        });
                        $hasConstraint = true;
                    }
                    if ($user->online_shop_id) {
                        $q->orWhere(function ($sub) use ($user) {
                            $sub->where('placement_type', 'online_shop')
                                ->where('placement_id', $user->online_shop_id);
                        });
                        $hasConstraint = true;
                    }

                    if (!$hasConstraint) {
                        $q->whereRaw('0 = 1');
                    }
                });
            }

            // Optional: filter by specific branch (for super_admin or admin_produk viewing specific branch)
            if ($request->has('branch_id')) {
                $query->where('placement_type', 'branch')
                    ->where('placement_id', $request->branch_id);
            }

            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('imei', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%");
                        });
                });
            }

            // Filter by Status (Default available)
            if ($request->has('status')) {
                $query->where('status', $request->status);
            } else {
                $query->where('status', 'available');
            }

            // Filter by placement type (branch/warehouse/online_shop)
            if ($request->has('placement_type')) {
                $query->where('placement_type', $request->placement_type);
            }

            $items = $query->latest()->paginate(20);

            // Transform results to include placement name
            $items->getCollection()->transform(function ($item) {
                $item->placement_name = $item->placement ? $item->placement->name : null;

                // For returned items, include proof_image and return details
                if ($item->status === 'returned') {
                    $returnStockOut = $item->latestReturnStockOut();
                    if ($returnStockOut) {
                        $item->proof_image = $returnStockOut->proof_image
                            ? asset('storage/' . $returnStockOut->proof_image)
                            : null;
                        $item->customer_name = $returnStockOut->customer_name;
                        $item->retur_issue = $returnStockOut->retur_issue;
                        $item->retur_officer = $returnStockOut->retur_officer;
                        $item->return_date = $returnStockOut->created_at;
                    }
                }

                return $item;
            });

            return response()->json($items);

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
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhere('description', 'like', "%{$search}%");
            }
        } else {
            // HP (Product Details created) - This is logically Stock In too
            $query = ProductDetail::with(['product', 'distributor', 'user']);

            // SEARCH
            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('imei', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        });
                });
            }
        }

        // PLACEMENT FILTER (Same logic as index)
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'audit', 'analist', 'owner'];
        // PLACEMENT FILTER (Same logic as index)
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'audit', 'analist', 'owner'];
        if (!in_array($user->role, $unrestrictedRoles)) {
            $query->where(function ($q) use ($user, $type) {
                $hasConstraint = false;

                if ($user->branch_id) {
                    $q->orWhere(function ($sub) use ($user, $type) {
                        if ($type === 'non-hp') {
                            $sub->where('branch_id', $user->branch_id);
                        } else {
                            $sub->where('placement_type', 'branch')->where('placement_id', $user->branch_id);
                        }
                    });
                    $hasConstraint = true;
                }

                if ($user->warehouse_id) {
                    $q->orWhere(function ($sub) use ($user, $type) {
                        if ($type === 'non-hp') {
                            $sub->where('warehouse_id', $user->warehouse_id);
                        } else {
                            $sub->where('placement_type', 'warehouse')->where('placement_id', $user->warehouse_id);
                        }
                    });
                    $hasConstraint = true;
                }

                if ($user->online_shop_id) {
                    $q->orWhere(function ($sub) use ($user, $type) {
                        if ($type === 'non-hp') {
                            $sub->where('online_shop_id', $user->online_shop_id);
                        } else {
                            $sub->where('placement_type', 'online_shop')->where('placement_id', $user->online_shop_id);
                        }
                    });
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
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->orWhere('description', 'like', "%{$search}%");
        }

        // PLACEMENT FILTER
        // PLACEMENT FILTER
        $unrestrictedRoles = ['super_admin', 'admin_produk', 'audit', 'analist', 'owner'];
        if (!in_array($user->role, $unrestrictedRoles)) {
            $query->where(function ($q) use ($user) {
                $hasConstraint = false;

                if ($user->branch_id) {
                    $q->orWhere('branch_id', $user->branch_id);
                    $hasConstraint = true;
                }
                if ($user->warehouse_id) {
                    $q->orWhere('warehouse_id', $user->warehouse_id);
                    $hasConstraint = true;
                }
                if ($user->online_shop_id) {
                    $q->orWhere('online_shop_id', $user->online_shop_id);
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
                $search = $request->search;
                $query->whereHas('product', fn($q) => $q->where('name', 'like', "%{$search}%"))->orWhere('description', 'like', "%{$search}%");
            }
        } else {
            $query = ProductDetail::with(['product', 'distributor', 'user']);
            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('imei', 'like', "%{$search}%")->orWhereHas('product', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
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
                        $item->condition === 'new' ? 'Baru' : 'Bekas',
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
            $search = $request->search;
            $query->whereHas('product', fn($q) => $q->where('name', 'like', "%{$search}%"))->orWhere('description', 'like', "%{$search}%");
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
            'product_id' => 'required|exists:products,id',
            'distributor_id' => 'nullable|exists:distributors,id',
            'type' => 'required|in:hp,non-hp', // Matches product type

            // Placement (Ideally auto-detected from user, but allowed if explicit)
            'placement_type' => 'required|in:branch,warehouse,online_shop',
            'placement_id' => 'required|integer',

            // For Non-HP
            'quantity' => 'required_if:type,non-hp|integer|min:1',

            // For HP
            'imeis' => 'required_if:type,hp|array',
            'imeis.*.imei' => ['required_if:type,hp', 'string', 'distinct', 'max:20', 'regex:/^[0-9]+$/'], // Only numbers allowed
            // 'imeis.*.color' => 'nullable|string',
            'imeis.*.ram' => 'nullable|string',
            'imeis.*.storage' => 'nullable|string',
            'storage' => 'nullable|string', // Allow root storage
            'imeis.*.condition' => 'required_if:type,hp|in:new,second',
            'imeis.*.cost_price' => 'nullable|numeric|min:0', // Optional now
            'imeis.*.selling_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
        ]);

        $user = Auth::user();

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
            $product = Product::findOrFail($request->product_id);

            // 1. Handle Non-HP (Quantity Based)
            if ($request->type === 'non-hp') {
                $inventory = Inventory::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'placement_type' => $request->placement_type,
                        'placement_id' => $request->placement_id,
                        'user_id' => $ownerUserId // Separate inventory by user (account)
                    ],
                    ['quantity' => 0]
                );

                $inventory->increment('quantity', $request->quantity);

                // Log
                $log = InventoryLog::create([
                    'product_id' => $product->id,
                    'branch_id' => 1, // Fallback or need to make nullable if placement isn't branch
                    // TODO: Update InventoryLog to support polymorphic placement too? Or just use description for now.
                    // For now, let's assume we map placement_id -> branch_id if type is branch, or null.
                    'user_id' => $ownerUserId, // Use the Owner User ID (Inventory Account)
                    'distributor_id' => $distributorId,
                    'supplier_name' => $supplierName,
                    'type' => 'in',
                    'quantity' => $request->quantity,
                    'balance_after' => $inventory->quantity,
                    'description' => "Stock In from " . ($supplierName ?: "Distributor"),
                    'reference_id' => 'STOCK-IN-' . time(),
                    'notes' => $request->notes,
                ]);

                // Dispatch History Event
                try {
                    event(new \App\Events\InventoryLogEvent($log->load(['product', 'user', 'distributor'])));
                } catch (\Exception $e) {
                    \Log::error("Failed to broadcast InventoryLogEvent: " . $e->getMessage());
                }
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
                            'cost_price' => $item['cost_price'] ?? 0,
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
                        'cost_price' => $item['cost_price'] ?? 0,
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
                        'branch_id' => 1,
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

    // Update item status (e.g., accept return: returned -> available)
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:available,sold,returned,deleted,out'
        ]);

        $item = ProductDetail::findOrFail($id);
        $item->update(['status' => $request->status]);

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
        if (!$user->branch_id && !$user->warehouse_id && !$user->online_shop_id && !$user->hasRole('super_admin')) {
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
                'branch_id' => $user->branch_id,
                'warehouse_id' => $user->warehouse_id,
                'online_shop_id' => $user->online_shop_id,
                'distributor_id' => $user->distributor_id,
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
            'phone' => 'nullable|string|max:20',
            'photo_inventory' => 'nullable|image|max:2048', // 2MB Max
        ]);

        $account->phone = $request->phone;

        if ($request->hasFile('photo_inventory')) {
            // Delete old photo if exists
            if ($account->photo_inventory && \Illuminate\Support\Facades\Storage::exists('public/' . $account->photo_inventory)) {
                \Illuminate\Support\Facades\Storage::delete('public/' . $account->photo_inventory);
            }
            $path = $request->file('photo_inventory')->store('account-photos', 'public');
            $account->photo_inventory = $path;
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
                'message' => 'Fixer executed v2 (Safe Mode)',
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
        $user = Auth::user();
        $inventoryUsers = \App\Models\User::role('inventory')
            ->where('created_by', $user->id)
            ->where('is_active', true)
            ->select('id', 'name', 'full_name', 'username', 'code_id')
            ->get();

        return response()->json($inventoryUsers);
    }
}
