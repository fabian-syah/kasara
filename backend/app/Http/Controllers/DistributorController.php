<?php

namespace App\Http\Controllers;

use App\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DistributorController extends Controller
{
    public function index(Request $request)
    {
        $query = Distributor::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%")
                ->orWhere('code', 'ilike', "%{$search}%")
                ->orWhere('contact_person', 'ilike', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'code' => 'nullable|string|unique:distributors,code',
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
        ]);

        $distributor = Distributor::create($validated);

        return response()->json([
            'success' => true,
            'data' => $distributor
        ], 201);
    }

    public function show(Distributor $distributor)
    {
        return response()->json(['success' => true, 'data' => $distributor]);
    }

    public function update(Request $request, Distributor $distributor)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'code' => ['nullable', 'string', Rule::unique('distributors')->ignore($distributor->id)],
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $distributor->update($validated);

        return response()->json(['success' => true, 'data' => $distributor]);
    }

    public function destroy(Distributor $distributor)
    {
        $distributor->delete();
        return response()->json(['success' => true]);
    }

    public function monitoring(Request $request)
    {
        $user = $request->user();
        $userRole = strtolower($user->roles->first()->name ?? '');

        // 1. Fetch IMEI Items (Available)
        $hpQuery = \App\Models\ProductDetail::with(['product', 'user.branch', 'user.onlineShop'])
            ->where('status', 'available')
            ->whereNotNull('distributor_id');

        // 1b. Fetch IMEI Sales (HP)
        $hpSalesQuery = \App\Models\ProductDetail::with([
            'product',
            'stockOuts' => function ($q) {
                $q->with(['user.branch', 'user.onlineShop', 'inventoryUser.branch', 'inventoryUser.onlineShop'])
                    ->whereIn('category', ['penjualan_offline', 'orderan_online', 'shopee'])
                    ->latest();
            }
        ])
            ->where('status', 'sold')
            ->whereNotNull('distributor_id')
            ->whereHas('stockOuts', function ($q) {
                $q->whereIn('category', ['penjualan_offline', 'orderan_online', 'shopee']);
            });

        $accessibleDistributorIds = $user->getAccessibleDistributorIds();

        if (($userRole === 'distribution' || $userRole === 'distributor' || $userRole === 'leader')) {
            if (empty($accessibleDistributorIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda belum dikaitkan dengan distributor manapun.'
                ], 403);
            }
            $hpQuery->whereIn('distributor_id', $accessibleDistributorIds);
            $hpSalesQuery->whereIn('distributor_id', $accessibleDistributorIds);
        } else if ($userRole === 'super_admin' && $request->has('distributor_id') && $request->distributor_id) {
            $hpQuery->where('distributor_id', $request->distributor_id);
            $hpSalesQuery->where('distributor_id', $request->distributor_id);
        }

        $hpItems = $hpQuery->get();
        $hpSalesItems = $hpSalesQuery->get();

        // 2. Fetch Non-IMEI Items (Available)
        $nonHpRaw = \App\Models\Inventory::with(['product', 'user.branch', 'user.onlineShop'])
            ->where('quantity', '>', 0)
            ->whereHas('product', function ($q) {
                $q->where('type', 'non-hp')->orWhere('has_imei', false);
            })
            ->get();

        $nonHpGrouped = collect();
        foreach ($nonHpRaw as $item) {
            $key = "{$item->product_id}-{$item->placement_type}-{$item->placement_id}-{$item->user_id}";
            if (!$nonHpGrouped->has($key)) {
                $nonHpGrouped->put($key, clone $item);
            } else {
                $existing = $nonHpGrouped->get($key);
                $existing->quantity += $item->quantity;
                $existing->id = max($existing->id, $item->id);
            }
        }
        $nonHpAll = $nonHpGrouped->values();

        $nonHpItems = [];
        foreach ($nonHpAll as $item) {
            $log = \App\Models\InventoryLog::with('distributor')
                ->where('product_id', $item->product_id)
                ->where('user_id', $item->user_id)
                ->where('type', 'in')
                ->latest()
                ->first();

            $itemDist = $log ? $log->distributor_id : ($item->user->distributor_id ?? null);

            if ($userRole === 'distribution' || $userRole === 'distributor' || $userRole === 'leader') {
                if (!in_array($itemDist, $accessibleDistributorIds))
                    continue;
            } else if ($userRole === 'super_admin' && $request->has('distributor_id') && $request->distributor_id) {
                if ($itemDist != $request->distributor_id)
                    continue;
            } else {
                if (!$itemDist)
                    continue;
            }

            $nonHpItems[] = $item;
        }

        // 2b. Fetch Non-IMEI Sales
        // To find sales from distributors for non-HP items, we look at StockOutNonHpItem 
        // and link back to InventoryLog to check the source distributor.
        $nonHpSalesRaw = \App\Models\StockOutNonHpItem::with(['product', 'stockOut.user.branch', 'stockOut.user.onlineShop', 'stockOut.inventoryUser.branch', 'stockOut.inventoryUser.onlineShop'])
            ->whereHas('stockOut', function ($q) {
                $q->whereIn('category', ['penjualan_offline', 'orderan_online', 'shopee']);
            })
            ->get();

        $nonHpSalesItems = [];
        $nonHpSalesGrouped = [];

        foreach ($nonHpSalesRaw as $soldItem) {
            if (!$soldItem->product)
                continue;

            // Heuristic for non-HP: Find the latest IN record for this product to guess the distributor
            $log = \App\Models\InventoryLog::with('distributor')
                ->where('product_id', $soldItem->product_id)
                ->where('type', 'in')
                ->latest()
                ->first();

            $itemDist = $log ? $log->distributor_id : null;

            if ($userRole === 'distribution' || $userRole === 'distributor' || $userRole === 'leader') {
                if (!in_array($itemDist, $accessibleDistributorIds))
                    continue;
            } else if ($userRole === 'super_admin' && $request->has('distributor_id') && $request->distributor_id) {
                if ($itemDist != $request->distributor_id)
                    continue;
            } else {
                if (!$itemDist)
                    continue;
            }

            // Outlet Details
            $outletName = 'APEX POS';
            if ($soldItem->stockOut) {
                $sourceUser = $soldItem->stockOut->inventoryUser ?? $soldItem->stockOut->user;
                if ($sourceUser) {
                    if ($sourceUser->branch) {
                        $outletName = 'Cabang: ' . $sourceUser->branch->name;
                    } elseif ($sourceUser->onlineShop) {
                        $outletName = 'Online: ' . $sourceUser->onlineShop->name;
                    }
                }
            }

            // Group by product and outlet logic
            $brandName = $soldItem->product->brand ?? 'Unknown';
            $typeName = $soldItem->product->name ?? 'Unknown';
            $productKey = trim("{$brandName} {$typeName} | {$outletName}");

            if (!isset($nonHpSalesGrouped[$productKey])) {
                $nonHpSalesGrouped[$productKey] = [
                    'brand' => $brandName,
                    'type_name' => $typeName,
                    'outlet' => $outletName,
                    'qty' => 0,
                    'total_sales' => 0,
                    'items' => []
                ];
            }

            $price = $soldItem->selling_price > 0 ? $soldItem->selling_price : ($soldItem->product->price ?? 0);
            $qty = $soldItem->quantity;

            $nonHpSalesGrouped[$productKey]['qty'] += $qty;
            $nonHpSalesGrouped[$productKey]['total_sales'] += ($price * $qty);
            $nonHpSalesGrouped[$productKey]['items'][] = [
                'date' => $soldItem->stockOut->created_at ?? null,
                'receipt_id' => $soldItem->stockOut->receipt_id ?? null,
                'qty' => $qty,
                'price' => $price,
            ];
        }

        $nonHpSalesItems = array_values($nonHpSalesGrouped);

        // Get names for grouping
        $branches = \App\Models\Branch::pluck('name', 'id');
        $warehouses = \App\Models\Warehouse::pluck('name', 'id');
        $onlineShops = \App\Models\OnlineShop::pluck('name', 'id');
        $distributorNames = Distributor::pluck('name', 'id');

        $grouped = [];

        // Process HP Items
        foreach ($hpItems as $item) {
            $locationName = 'Unknown Location';
            if ($item->placement_type === 'branch') {
                $locationName = 'Cabang: ' . ($branches[$item->placement_id] ?? 'Unknown');
            } elseif ($item->placement_type === 'warehouse') {
                $locationName = 'Gudang: ' . ($warehouses[$item->placement_id] ?? 'Unknown');
            } elseif ($item->placement_type === 'online_shop') {
                $locationName = 'Online: ' . ($onlineShops[$item->placement_id] ?? 'Unknown');
            } elseif ($item->placement_type === 'distributor') {
                $locationName = 'Distributor: ' . ($distributorNames[$item->placement_id] ?? 'Unknown');
            } elseif (!$item->placement_type && $item->user) {
                // Fallback: resolve from user's branch or online shop
                if ($item->user->branch) {
                    $locationName = 'Cabang: ' . $item->user->branch->name;
                } elseif ($item->user->onlineShop) {
                    $locationName = 'Online: ' . $item->user->onlineShop->name;
                }
            }

            if (!isset($grouped[$locationName])) {
                $grouped[$locationName] = [
                    'location' => $locationName,
                    'products' => []
                ];
            }

            $brandName = $item->product->brand ?? 'Unknown';
            $typeName = $item->product->name ?? 'Unknown';

            // Format capacity
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

            // Add specific item details
            $grouped[$locationName]['products'][$productKey]['items'][] = [
                'id' => $item->id,
                'imei' => $item->imei,
                'color' => $item->color,
                'notes' => $item->notes,
                'condition' => $item->condition,
            ];
        }

        // Process Non-HP Items
        foreach ($nonHpItems as $item) {
            $locationName = 'Unknown Location';
            if ($item->placement_type === 'branch') {
                $locationName = 'Cabang: ' . ($branches[$item->placement_id] ?? 'Unknown');
            } elseif ($item->placement_type === 'warehouse') {
                $locationName = 'Gudang: ' . ($warehouses[$item->placement_id] ?? 'Unknown');
            } elseif ($item->placement_type === 'online_shop') {
                $locationName = 'Online: ' . ($onlineShops[$item->placement_id] ?? 'Unknown');
            } elseif ($item->placement_type === 'distributor') {
                $locationName = 'Distributor: ' . ($distributorNames[$item->placement_id] ?? 'Unknown');
            } elseif (!$item->placement_type && $item->user) {
                // Fallback: resolve from user's branch or online shop
                if ($item->user->branch) {
                    $locationName = 'Cabang: ' . $item->user->branch->name;
                } elseif ($item->user->onlineShop) {
                    $locationName = 'Online: ' . $item->user->onlineShop->name;
                }
            }

            if (!isset($grouped[$locationName])) {
                $grouped[$locationName] = [
                    'location' => $locationName,
                    'products' => []
                ];
            }

            $brandName = $item->product->brand ?? 'Unknown';
            $typeName = $item->product->name ?? 'Unknown';

            $cond = 'New'; // Assuming Non-HP are mostly new
            $productKey = trim("{$brandName} {$typeName} - {$cond}");

            if (!isset($grouped[$locationName]['products'][$productKey])) {
                $grouped[$locationName]['products'][$productKey] = [
                    'name' => $productKey,
                    'brand' => $brandName,
                    'type_name' => $typeName,
                    'capacity' => null, // Non-HP doesn't usually have capacity
                    'condition_label' => $cond,
                    'qty' => 0,
                    'type' => $item->product->type ?? 'non-hp',
                    'has_imei' => false,
                    'items' => [] // Don't list individual items for non-HP since they are grouped by quantity
                ];
            }

            $grouped[$locationName]['products'][$productKey]['qty'] += $item->quantity;
        }

        // 3. Process Sales Data
        $salesHpFormatted = [];
        $totalOmzet = 0;

        foreach ($hpSalesItems as $soldItem) {
            // Find selling price from latest stock out
            $latestOut = $soldItem->stockOuts->first();
            $sellingPrice = 0;
            $outletName = 'APEX POS';

            if ($latestOut) {
                // If it's a multi-item stock out with overall price, we might have to fallback or find proportion.
                // Assuming selling_price on stockOut or detail level
                $sellingPrice = $latestOut->selling_price > 0 ? $latestOut->selling_price : ($soldItem->selling_price > 0 ? $soldItem->selling_price : $soldItem->product->price);

                $sourceUser = $latestOut->inventoryUser ?? $latestOut->user;
                if ($sourceUser) {
                    if ($sourceUser->branch) {
                        $outletName = 'Cabang: ' . $sourceUser->branch->name;
                    } elseif ($sourceUser->onlineShop) {
                        $outletName = 'Online: ' . $sourceUser->onlineShop->name;
                    }
                }
            }

            $brandName = $soldItem->product->brand ?? 'Unknown';
            $typeName = $soldItem->product->name ?? 'Unknown';

            $spec = [];
            if ($soldItem->ram)
                $spec[] = $soldItem->ram;
            if ($soldItem->storage)
                $spec[] = $soldItem->storage;
            $specStr = !empty($spec) ? ' ' . implode('/', $spec) : '';
            $cond = ($soldItem->condition === 'new') ? 'New' : (($soldItem->condition === 'ex_ibox') ? 'Ex iBox' : 'Second');

            $totalOmzet += $sellingPrice;

            $salesHpFormatted[] = [
                'id' => $soldItem->id,
                'outlet' => $outletName,
                'brand' => $brandName,
                'type_name' => trim("{$typeName}{$specStr}"),
                'imei' => $soldItem->imei,
                'capacity' => implode('/', $spec),
                'condition_label' => $cond,
                'date' => $latestOut ? $latestOut->created_at : $soldItem->updated_at,
                'receipt_id' => $latestOut ? $latestOut->receipt_id : null,
                'harga_jual' => $sellingPrice
            ];
        }

        // Add non-HP sales to omzet
        foreach ($nonHpSalesItems as $nhpSale) {
            $totalOmzet += $nhpSale['total_sales'];
        }

        $result = array_values($grouped);

        // Sort locations
        usort($result, function ($a, $b) {
            return strcmp($a['location'], $b['location']);
        });

        // Convert and sort products
        foreach ($result as &$loc) {
            $prodArr = array_values($loc['products']);
            usort($prodArr, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            $loc['products'] = $prodArr;
        }

        // Sort sales data
        usort($salesHpFormatted, function ($a, $b) {
            $dateA = $a['date'] ?? null;
            $dateB = $b['date'] ?? null;
            if ($dateA == $dateB)
                return 0;
            return ($dateA > $dateB) ? -1 : 1;
        });

        // Sort non-HP sales by the latest item date
        usort($nonHpSalesItems, function ($a, $b) {
            $latestA = empty($a['items']) ? null : max(array_column($a['items'], 'date'));
            $latestB = empty($b['items']) ? null : max(array_column($b['items'], 'date'));
            if ($latestA == $latestB)
                return 0;
            return ($latestA > $latestB) ? -1 : 1;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'stock' => $result,
                'sales_hp' => $salesHpFormatted,
                'sales_non_hp' => $nonHpSalesItems,
                'total_omzet' => $totalOmzet
            ]
        ]);
    }
}
