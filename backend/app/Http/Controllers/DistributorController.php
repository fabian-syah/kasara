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

        $query = \App\Models\ProductDetail::with(['product'])
            ->where('status', 'available')
            ->whereNotNull('distributor_id');

        // Apply distributor filter based on Role
        if ($userRole === 'distribution' || $userRole === 'distributor') {
            if (!$user->distributor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda belum dikaitkan dengan distributor manapun.'
                ], 403);
            }
            $query->where('distributor_id', $user->distributor_id);
        } else if ($userRole === 'super_admin' && $request->has('distributor_id') && $request->distributor_id) {
            $query->where('distributor_id', $request->distributor_id);
        }

        // Fetch items
        $items = $query->get();

        // Get names for grouping
        $branches = \App\Models\Branch::pluck('name', 'id');
        $warehouses = \App\Models\Warehouse::pluck('name', 'id');
        $onlineShops = \App\Models\OnlineShop::pluck('name', 'id');

        $grouped = [];

        foreach ($items as $item) {
            $locationName = 'Unknown Location';
            if ($item->placement_type === 'branch') {
                $locationName = 'Cabang: ' . ($branches[$item->placement_id] ?? 'Unknown');
            } elseif ($item->placement_type === 'warehouse') {
                $locationName = 'Gudang: ' . ($warehouses[$item->placement_id] ?? 'Unknown');
            } elseif ($item->placement_type === 'online_shop') {
                $locationName = 'Online: ' . ($onlineShops[$item->placement_id] ?? 'Unknown');
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

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
