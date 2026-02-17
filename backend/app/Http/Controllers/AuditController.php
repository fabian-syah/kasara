<?php

namespace App\Http\Controllers;

use App\Models\StockOut; // Changed from Transaction
use App\Models\StockOutNonHpItem;
use App\Models\Product;
use App\Models\ProductDetail; // Added
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    /**
     * Get aggregated sales data for audit dashboard/reports.
     * Scoped to branches accessible by the user.
     */
    public function sales(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();

        if (empty($branchIds)) {
            return response()->json([
                'daily_sales' => [],
                'brand_sales' => [],
                'cs_sales' => []
            ]);
        }

        // Date Filter
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        // Helper to scope query to branches
        // Note: StockOut has 'user_id', we need to check if that user belongs to one of the branches
        // OR check 'branch_id' if StockOut has it? StockOut has 'destination_branch_id' but that's for transfer.
        // StockOut doesn't have 'branch_id' directly? Let's check model again.
        // StockOut model (lines 13-66) does NOT have 'branch_id'.
        // It has 'user_id'. So we rely on user's branch.

        $scopeToBranches = function ($query) use ($branchIds) {
            $query->whereHas('user', function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds);
            });
        };

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        // 1. Daily Sales
        // Group by Date -> Order No (receipt_id) -> Customer -> etc
        $dailySalesQuery = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        $scopeToBranches($dailySalesQuery);

        $dailySales = $dailySalesQuery->latest()->get()->map(function ($trx) {
            // Determine items summary
            $itemSummary = '-';
            $qty = 0;

            if ($trx->items->isNotEmpty()) {
                $first = $trx->items->first();
                $itemSummary = $first->product->name ?? '-';
                if ($trx->items->count() > 1)
                    $itemSummary .= ' +' . ($trx->items->count() - 1) . ' items';
                $qty += $trx->items->count();
            }
            if ($trx->nonHpItems->isNotEmpty()) {
                if ($itemSummary === '-') {
                    $first = $trx->nonHpItems->first();
                    $itemSummary = $first->product->name ?? '-';
                    if ($trx->nonHpItems->count() > 1)
                        $itemSummary .= ' +' . ($trx->nonHpItems->count() - 1) . ' items';
                }
                $qty += $trx->nonHpItems->sum('quantity');
            }

            // Payment method is not tracked in DB, using placeholders
            return [
                'date' => $trx->created_at->toDateTimeString(),
                'order_no' => $trx->receipt_id,
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? '-',
                'customer_phone' => $trx->customer_phone ?? '-',
                'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                'qty' => $qty,
                'status' => $trx->status === 'received' ? 'Lunas' : 'Pending', // Assumption
                'cash' => 0, // Placeholder
                'transfer' => 0, // Placeholder
                'debit' => 0, // Placeholder
                'grand_total' => $trx->selling_price // Assuming selling_price is total
            ];
        });

        // 2. Report per Brand (HP + Non-HP)
        // Reuse logic from ReportController but simpler
        $brandStats = [];

        // HP
        $hpQuery = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id') // Join users for scoping
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereIn('users.branch_id', $branchIds)
            ->select('products.brand', DB::raw('count(*) as count'))
            ->groupBy('products.brand')
            ->get();

        foreach ($hpQuery as $item) {
            if (!isset($brandStats[$item->brand]))
                $brandStats[$item->brand] = 0;
            $brandStats[$item->brand] += $item->count;
        }

        // Non-HP
        $nhpQuery = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->join('products', 'stock_out_non_hp_items.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id') // Join users for scoping
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereIn('users.branch_id', $branchIds)
            ->select('products.brand', DB::raw('sum(quantity) as count'))
            ->groupBy('products.brand')
            ->get();

        foreach ($nhpQuery as $item) {
            if (!isset($brandStats[$item->brand]))
                $brandStats[$item->brand] = 0;
            $brandStats[$item->brand] += $item->count;
        }

        $formattedBrandSales = [];
        foreach ($brandStats as $brand => $qty) {
            $formattedBrandSales[] = ['brand' => $brand, 'qty' => $qty];
        }

        // 3. Report per CS (Aggregated by inventory_user_id)
        $csQuery = StockOut::whereIn('category', $salesCategories)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $scopeToBranches($csQuery);

        $csSales = $csQuery->with('inventoryUser')
            ->select('inventory_user_id', DB::raw('count(*) as count'), DB::raw('sum(selling_price) as total'))
            ->groupBy('inventory_user_id')
            ->get()
            ->map(function ($item) {
                return [
                    'cs_name' => $item->inventoryUser->name ?? 'Unknown',
                    'total_sales' => $item->count, // This is count of receipts, not items. Close enough?
                    'total_trade_in' => 0,
                    'total_refund' => 0,
                    'grand_total' => $item->total
                ];
            });

        return response()->json([
            'daily_sales' => $dailySales,
            'brand_sales' => $formattedBrandSales,
            'cs_sales' => $csSales
        ]);
    }

    /**
     * Get inventory summary (Stock, In, Out)
     */
    public function inventory(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();

        if (empty($branchIds)) {
            return response()->json([
                'stock' => 0,
                'in' => 0,
                'out' => 0
            ]);
        }

        // 1. Stock (Available Items)
        // HP
        $hpStock = ProductDetail::where('status', 'available')
            ->where('placement_type', 'branch')
            ->whereIn('placement_id', $branchIds)
            ->count();

        // Non-HP (Sum quantity in Inventory model)
        // Need to check Inventory model logic
        $nonHpStock = \App\Models\Inventory::where('placement_type', 'branch')
            ->whereIn('placement_id', $branchIds)
            ->sum('quantity');

        $totalStock = $hpStock + $nonHpStock;

        // 2. Stock In (Incoming Transfers that are Received)
        // Logic: StockOut where destination_branch_id IN branchIds AND status = 'received'
        $stockIn = StockOut::where('destination_type', 'branch')
            ->whereIn('destination_id', $branchIds) // Use destination_id for morph, assuming destination_type is correct
            ->where('status', 'received')
            ->count();

        // 3. Stock Out (Sales + Transfers Out)
        // Logic: StockOut created by users in these branches
        $stockOut = StockOut::whereHas('user', function ($q) use ($branchIds) {
            $q->whereIn('branch_id', $branchIds);
        })->count();

        return response()->json([
            'stock' => $totalStock,
            'in' => $stockIn,
            'out' => $stockOut
        ]);
    }

    /**
     * Track Item by IMEI/SKU
     */
    public function track(Request $request)
    {
        $search = $request->query('q');
        if (!$search)
            return response()->json([]);

        // Search ProductDetails (HP)
        $items = ProductDetail::with(['product'])
            ->where('imei', 'like', "%$search%")
            ->take(20)
            ->get()
            ->map(function ($item) {
                $loc = '-';
                if ($item->placement_type === 'branch') {
                    $loc = \App\Models\Branch::find($item->placement_id)?->name ?? 'Branch ' . $item->placement_id;
                } elseif ($item->placement_type === 'warehouse') {
                    $loc = \App\Models\Warehouse::find($item->placement_id)?->name ?? 'Gudang ' . $item->placement_id;
                } elseif ($item->placement_type === 'online_shop') {
                    $loc = \App\Models\OnlineShop::find($item->placement_id)?->name ?? 'Online Shop ' . $item->placement_id;
                }

                return [
                    'id' => $item->id,
                    'name' => $item->product->name ?? '-',
                    'imei' => $item->imei,
                    'status' => $item->status,
                    'location' => $loc,
                    'type' => 'HP'
                ];
            });

        return response()->json($items);
    }

    public function analysis(Request $request)
    {
        // Placeholder for Branch Analysis
        return response()->json([
            'message' => 'Analysis data not implemented yet'
        ]);
    }
}
