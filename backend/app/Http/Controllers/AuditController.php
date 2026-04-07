<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use App\Models\StockOutNonHpItem;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\User;
use App\Models\AuditAnswer;
use App\Models\AuditProfit;
use App\Models\Question;
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
        $onlineShopIds = $user->getAccessibleOnlineShopIds();

        if (empty($branchIds) && empty($onlineShopIds)) {
            return response()->json([
                'daily_sales' => [],
                'brand_sales' => [],
                'cs_sales' => []
            ]);
        }

        // Date Filter
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        // Filter by specific location
        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;

        $scopeToAccess = function ($query) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
            $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                $q->where(function ($sub) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId) {
                        if (empty($branchIds) || in_array($requestedBranchId, $branchIds)) {
                            $sub->where('branch_id', $requestedBranchId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedOnlineShopId) {
                        if (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) {
                            $sub->where('online_shop_id', $requestedOnlineShopId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } else {
                        if (!empty($branchIds)) {
                            $sub->orWhereIn('branch_id', $branchIds);
                        }
                        if (!empty($onlineShopIds)) {
                            $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        }
                    }
                });
            });
        };

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'refund', 'angkat_barang', 'pindah_cabang', 'retur', 'cancel_penjualan'];

        // 1. Daily Sales
        $paymentMethods = \App\Models\PaymentMethod::all()->keyBy('id');

        // Load nonHpDetails relationship for Product details, but we will use JSON column for price
        $dailySalesQuery = StockOut::with(['items.product', 'nonHpDetails.product', 'user', 'inventoryUser', 'auditAnswers', 'paymentMethod'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        $scopeToAccess($dailySalesQuery);

        $paginatedSales = $dailySalesQuery->latest()->paginate(50);
        
        $dailySales = collect($paginatedSales->items())->map(function ($trx) use ($paymentMethods) {
            $details = [];
            $calculatedTotal = 0;

            $hpItems = $trx->items;
            $nonHpItems = $trx->nonHpDetails;

            $bundleHpId = null;
            $bundleNonHpId = null;

            // If it's a bundle, we group the first HP and first Non-HP into one line for the receipt
            if ($trx->is_bundle && ($hpItems->isNotEmpty() || $nonHpItems->isNotEmpty())) {
                $mainHp = $hpItems->first();
                $mainNonHp = $nonHpItems->first();

                $bundlePrice = 0;
                $bundleItemDiscount = 0;
                $bundleDistributedDiscount = 0;
                $bundleName = $trx->bundle_description ?: ($mainHp ? $mainHp->product->name . ' + BUNDLING' : 'PAKET BUNDLING');
                $bundleImei = $mainHp ? $mainHp->imei : '-';

                if ($mainHp) {
                    $pivot = $mainHp->pivot;
                    $sellingPrice = $pivot->selling_price ?? $mainHp->selling_price;
                    $itemDisc = ($pivot->item_discount ?? 0);
                    $bundlePrice += ($sellingPrice - $itemDisc);
                    $bundleHpId = $mainHp->id;
                }

                if ($mainNonHp) {
                    $itemDisc = ($mainNonHp->item_discount ?? 0);
                    $bundlePrice += ($mainNonHp->selling_price - $itemDisc);
                    $bundleNonHpId = $mainNonHp->id;
                }

                $details[] = [
                    'name' => $bundleName,
                    'qty' => 1,
                    'price' => $bundlePrice,
                    'item_discount' => 0, // Discounts are now reflected in 'price'
                    'distributed_discount' => 0,
                    'is_fixed' => true,
                    'type' => 'Bundle',
                    'imei' => $bundleImei,
                ];
                $calculatedTotal += $bundlePrice;
            }

            // 1. HP Items (process non-bundled ones)
            foreach ($hpItems as $item) {
                if ($item->id === $bundleHpId)
                    continue;

                $pivot = $item->pivot;
                $sellingPrice = $pivot->selling_price ?? $item->selling_price;
                $itemDiscount = $pivot->item_discount ?? 0;
                $netPrice = $sellingPrice - $itemDiscount;

                $details[] = [
                    'name' => $item->product->name ?? 'Unknown HP',
                    'qty' => 1,
                    'price' => $netPrice,
                    'item_discount' => 0,
                    'distributed_discount' => 0,
                    'is_fixed' => true,
                    'brand' => $item->product->brand ?? '-',
                    'type' => 'HP',
                    'imei' => $item->imei ?? '-',
                    'storage' => $item->storage ?? null,
                    'condition' => $item->condition === 'new' ? 'new' : ($item->condition === 'ex_ibox' ? 'ex_ibox' : ($item->condition ?? 'second')),
                ];
                $calculatedTotal += $netPrice;
            }

            // 2. Non-HP Items (process non-bundled ones or remaining quantity)
            foreach ($nonHpItems as $item) {
                $isMainNonHp = ($item->id === $bundleNonHpId);
                $qty = $isMainNonHp ? ($item->quantity - 1) : $item->quantity;

                if ($qty <= 0)
                    continue;

                $sellingPrice = $item->selling_price ?? 0;
                $itemDiscount = $item->item_discount ?? 0;
                $netPrice = $sellingPrice - $itemDiscount;

                $details[] = [
                    'name' => $item->product->name ?? 'Item Non-HP',
                    'qty' => $qty,
                    'price' => $netPrice,
                    'item_discount' => 0,
                    'distributed_discount' => 0,
                    'is_fixed' => true,
                    'brand' => $item->product->brand ?? '-',
                    'type' => 'Non-HP',
                    'imei' => '-',
                ];
                $calculatedTotal += ($netPrice * $qty);
            }

            // 3. Final Adjustment / Gap Handling
            // [REMOVED] We no longer show "Diskon" as a row item per user request.
            // Any gaps will be handled by the "DISKON" field in the summary.

            // Calculate Global Discount explicitly for the summary
            $calculatedGlobalDiscount = 0;
            if ($trx->global_discount_type === 'percentage') {
                $calculatedGlobalDiscount = ($calculatedTotal * ($trx->global_discount_value ?? 0)) / 100;
            } else {
                $calculatedGlobalDiscount = $trx->global_discount_value ?? 0;
            }

            // Outlet Details
            $outletName = 'APEX POS';
            $outletAddress = 'Jl. Raya Example No. 123, Indonesia';

            $sourceUser = $trx->inventoryUser ?? $trx->user;

            if ($sourceUser) {
                if ($sourceUser->branch_id) {
                    $branch = \App\Models\Branch::find($sourceUser->branch_id);
                    if ($branch) {
                        $outletName = $branch->name;
                        $outletAddress = $branch->address ?? 'Alamat Cabang Belum Diatur';
                    }
                } elseif ($sourceUser->online_shop_id) {
                    $shop = \App\Models\OnlineShop::find($sourceUser->online_shop_id);
                    if ($shop) {
                        $outletName = $shop->name;
                        $addrParts = [];
                        if ($shop->platform)
                            $addrParts[] = ucfirst($shop->platform);
                        $outletAddress = !empty($addrParts) ? implode(' - ', $addrParts) : 'Toko Online';
                    }
                } elseif ($sourceUser->warehouse_id) {
                    $warehouse = \App\Models\Warehouse::find($sourceUser->warehouse_id);
                    if ($warehouse) {
                        $outletName = $warehouse->name;
                        $outletAddress = $warehouse->address ?? 'Alamat Gudang Belum Diatur';
                    }
                }
            }

            // Audit score calculation (must match getChecklist logic for edited questions)
            $currentQuestions = Question::where('category', $trx->category)->get();
            $answers = $trx->auditAnswers;
            $yesCount = $answers->where('answer', true)->count();
            $totalQuestions = $currentQuestions->count();

            // Count edited questions (answered but content changed) as additional unanswered
            foreach ($currentQuestions as $cq) {
                $existingAns = $answers->firstWhere('question_id', $cq->id);
                if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $cq->content) {
                    $totalQuestions++; // edited question = +1 unanswered
                }
            }
            // Count orphaned answers (deleted questions) still in the total
            foreach ($answers as $ans) {
                if ($ans->question_id === null || !$currentQuestions->contains('id', $ans->question_id)) {
                    $totalQuestions++;
                }
            }

            $auditScore = null;
            if ($answers->count() > 0 && $totalQuestions > 0) {
                $auditScore = round(($yesCount / $totalQuestions) * 100);
            }

            // 4. Payment Breakdown
            $processedSplitPayments = [];
            $cash = 0;
            $transfer = 0;
            $debit = 0;
            if ($trx->split_payments) {
                $splits = is_string($trx->split_payments) ? json_decode($trx->split_payments, true) : $trx->split_payments;
                if (is_array($splits)) {
                    foreach ($splits as $sp) {
                        $methodId = $sp['payment_method_id'] ?? ($sp['method_id'] ?? null);
                        $amount = floatval($sp['amount'] ?? 0);

                        $methodName = 'Unknown';
                        if ($methodId && isset($paymentMethods[$methodId])) {
                            $method = $paymentMethods[$methodId];
                            $methodName = $method->name;
                            $cat = strtolower($method->category ?? '');
                            $name = strtolower($method->name ?? '');

                            if (str_contains($cat, 'cash') || str_contains($cat, 'tunai') || str_contains($name, 'cash') || str_contains($name, 'tunai')) {
                                $cash += $amount;
                            } elseif (str_contains($cat, 'debit') || str_contains($name, 'debit') || str_contains($name, 'edc')) {
                                $debit += $amount;
                            } else {
                                $transfer += $amount;
                            }
                        } else {
                            $transfer += $amount;
                        }

                        $processedSplitPayments[] = [
                            'method_name' => $methodName,
                            'amount' => $amount
                        ];
                    }
                }
            } else {
                // Fallback for older transactions without split_payments
                if ($trx->category === 'penjualan_offline') {
                    $cash = $trx->selling_price;
                } else {
                    $transfer = $trx->selling_price;
                }
            }

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'order_no' => $trx->receipt_id,
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? $trx->shopee_receiver ?? $trx->giveaway_receiver ?? '-',
                'customer_phone' => $trx->customer_phone ?? $trx->shopee_phone ?? $trx->giveaway_phone ?? '-',
                'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                'brand_names' => collect()->concat($trx->items->map(fn($i) => $i->product->brand ?? '-'))->concat($trx->nonHpDetails->map(fn($i) => $i->product->brand ?? '-'))->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-',
                'product_names' => collect()->concat($trx->items->map(fn($i) => $i->product->name ?? '-'))->concat($trx->nonHpDetails->map(fn($i) => $i->product->name ?? '-'))->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: ($trx->is_bundle ? $trx->bundle_description : '-'),
                'imeis' => $trx->items->map(fn($i) => $i->imei)->filter()->implode(', ') ?: '-',
                'storages' => $trx->items->map(fn($i) => $i->ram && $i->storage ? $i->ram . '/' . $i->storage : $i->storage)->filter()->unique()->implode(', ') ?: null,
                'conditions' => $trx->items->map(fn($i) => match ($i->condition) { 'new' => 'Baru', 'ex_ibox' => 'Ex iBox', default => 'Second'})->filter()->unique()->implode(', ') ?: null,
                'qty' => $trx->items->count() + ($trx->non_hp_items ? collect($trx->non_hp_items)->sum('quantity') : ($trx->nonHpDetails ? $trx->nonHpDetails->sum('quantity') : 0)),
                'items' => $details,
                'status' => $trx->status === 'received' ? 'Lunas' : 'Pending',
                'payment_method' => $trx->paymentMethod->name ?? ($trx->category === 'penjualan_offline' ? 'Offline' : 'Online'),
                'payment_method_name' => $trx->paymentMethod->name ?? null,
                'split_payments_data' => $processedSplitPayments,
                'cash' => $cash,
                'transfer' => $transfer,
                'debit' => $debit,
                'grand_total' => $trx->selling_price, // Final Paid Amount
                'total_discount' => $calculatedGlobalDiscount,
                'global_discount_value' => $calculatedGlobalDiscount,
                'global_discount_type' => 'fixed',
                'original_price' => $trx->selling_price + $calculatedGlobalDiscount,
                'outlet_name' => $outletName,
                'outlet_address' => $outletAddress,
                'customer_wa' => $trx->customer_wa,
                'notes' => $trx->notes,
                'sales_account' => $trx->sales_account,
                'inventory_user_name' => $trx->inventoryUser->name ?? null,
                'transaction_pin' => (string)$trx->transaction_pin === '9090' ? '9090' : null,
                'audit_score' => $auditScore,
                'audit_answered' => $trx->auditAnswers->count(),
                'audit_total' => $totalQuestions,
                'audit_yes' => $yesCount,
                'proof_image' => $trx->proof_image ? asset('storage/' . $trx->proof_image) : null,
            ];
        });

        // 2. Report per Brand (with scope)
        $brandStats = [];

        // HP
        $hpQuery = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
            ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId) {
                    $q->where('users.branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $q->where('users.online_shop_id', $requestedOnlineShopId);
                } else {
                    if (!empty($branchIds))
                        $q->orWhereIn('users.branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                }
            })
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
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategories)
            ->whereBetween('stock_outs.reporting_date', [$startDate, $endDate])
            ->where(function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId) {
                    $q->where('users.branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $q->where('users.online_shop_id', $requestedOnlineShopId);
                } else {
                    if (!empty($branchIds))
                        $q->orWhereIn('users.branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $q->orWhereIn('users.online_shop_id', $onlineShopIds);
                }
            })
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

        // 3. Report per CS
        $csQuery = StockOut::whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);
        $scopeToAccess($csQuery);

        $csSales = $csQuery->with(['inventoryUser', 'user'])
            ->select('inventory_user_id', 'user_id', DB::raw('count(*) as count'), DB::raw('sum(selling_price) as total'))
            ->groupBy('inventory_user_id', 'user_id')
            ->get()
            ->map(function ($item) {
                return [
                    'cs_name' => $item->inventoryUser->name ?? ($item->user->name ?? 'Unknown'),
                    'total_sales' => $item->count,
                    'total_trade_in' => 0,
                    'total_refund' => 0,
                    'grand_total' => $item->total
                ];
            });

        return response()->json([
            'daily_sales' => [
                'data' => $dailySales,
                'current_page' => $paginatedSales->currentPage(),
                'last_page' => $paginatedSales->lastPage(),
                'total' => $paginatedSales->total(),
                'per_page' => $paginatedSales->perPage(),
            ],
            'brand_sales' => $formattedBrandSales,
            'cs_sales' => $csSales
        ]);
    }

    public function inventory(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();

        if (empty($branchIds) && empty($onlineShopIds)) {
            return response()->json([
                'stock' => 0,
                'stock_hp' => 0,
                'stock_non_hp' => 0,
                'in' => 0,
                'in_hp' => 0,
                'in_non_hp' => 0,
                'out' => 0,
                'out_hp' => 0,
                'out_non_hp' => 0,
            ]);
        }

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;

        // Filter assignments based on request
        if ($requestedBranchId) {
            $branchIds = (empty($branchIds) || in_array($requestedBranchId, $branchIds)) ? [$requestedBranchId] : [];
            $onlineShopIds = [];
        } elseif ($requestedOnlineShopId) {
            $onlineShopIds = (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) ? [$requestedOnlineShopId] : [];
            $branchIds = [];
        }

        if (empty($branchIds) && empty($onlineShopIds)) {
            return response()->json([
                'stock' => 0,
                'stock_hp' => 0,
                'stock_non_hp' => 0,
                'in' => 0,
                'in_hp' => 0,
                'in_non_hp' => 0,
                'out' => 0,
                'out_hp' => 0,
                'out_non_hp' => 0,
            ]);
        }

        // 1. Stock (Available Items)
        // HP
        $hpStock = ProductDetail::where('status', 'available')
            ->where(function ($q) use ($branchIds, $onlineShopIds) {
                if (!empty($branchIds)) {
                    $q->orWhere(function ($sub) use ($branchIds) {
                        $sub->where('placement_type', 'branch')->whereIn('placement_id', $branchIds);
                    });
                }
                if (!empty($onlineShopIds)) {
                    $q->orWhere(function ($sub) use ($onlineShopIds) {
                        $sub->where('placement_type', 'online_shop')->whereIn('placement_id', $onlineShopIds);
                    });
                }
            })
            ->count();

        // Non-HP
        $nonHpStockQuery = \App\Models\Inventory::query();
        $nonHpStockQuery->where(function ($q) use ($branchIds, $onlineShopIds) {
            if (!empty($branchIds)) {
                $q->orWhere(function ($sub) use ($branchIds) {
                    $sub->where('placement_type', 'branch')->whereIn('placement_id', $branchIds);
                });
            }
            if (!empty($onlineShopIds)) {
                $q->orWhere(function ($sub) use ($onlineShopIds) {
                    $sub->where('placement_type', 'online_shop')->whereIn('placement_id', $onlineShopIds);
                });
            }
        });
        $nonHpStock = (int) $nonHpStockQuery->sum('quantity');

        $totalStock = $hpStock + $nonHpStock;

        // 2. Stock In (Incoming Transfers that are Received)
        // Helper to scope StockOut (Transfers) by Destination
        $scopeIn = function ($q) use ($branchIds, $onlineShopIds) {
            $q->where('stock_outs.status', 'received')
                ->whereMonth('stock_outs.reporting_date', now()->month)
                ->whereYear('stock_outs.reporting_date', now()->year)
                ->where(function ($sub) use ($branchIds, $onlineShopIds) {
                    if (!empty($branchIds)) {
                        $sub->orWhere(function ($deep) use ($branchIds) {
                            $deep->where('stock_outs.destination_type', 'branch')->whereIn('stock_outs.destination_id', $branchIds);
                        });
                    }
                    if (!empty($onlineShopIds)) {
                        $sub->orWhere(function ($deep) use ($onlineShopIds) {
                            $deep->where('stock_outs.destination_type', 'online_shop')->whereIn('stock_outs.destination_id', $onlineShopIds);
                        });
                    }
                });
        };

        // HP In
        $inHp = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->where(function ($q) use ($scopeIn) {
                $scopeIn($q);
            })
            ->count();

        // Non-HP In
        $inNonHp = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->where(function ($q) use ($scopeIn) {
                $scopeIn($q);
            })
            ->sum('stock_out_non_hp_items.quantity');

        $totalIn = $inHp + $inNonHp;


        // 3. Stock Out (Sales + Transfers Out)
        // Helper to scope StockOut by Source
        $scopeOut = function ($q) use ($branchIds, $onlineShopIds) {
            $q->whereMonth('stock_outs.reporting_date', now()->month)
                ->whereYear('stock_outs.reporting_date', now()->year)
                ->where(function ($sub) use ($branchIds, $onlineShopIds) {
                    if (!empty($branchIds)) {
                        $sub->whereIn('users.branch_id', $branchIds);
                    }
                    if (!empty($onlineShopIds)) {
                        $sub->orWhereIn('users.online_shop_id', $onlineShopIds);
                    }
                });
        };

        // HP Out
        $outHp = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereMonth('stock_outs.reporting_date', now()->month)
            ->whereYear('stock_outs.reporting_date', now()->year)
            ->where(function ($q) use ($branchIds, $onlineShopIds) {
                if (!empty($branchIds))
                    $q->orWhereIn('users.branch_id', $branchIds);
                if (!empty($onlineShopIds))
                    $q->orWhereIn('users.online_shop_id', $onlineShopIds);
            })
            ->count();

        // Non-HP Out
        $outNonHp = DB::table('stock_out_non_hp_items')
            ->join('stock_outs', 'stock_out_non_hp_items.stock_out_id', '=', 'stock_outs.id')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereMonth('stock_outs.reporting_date', now()->month)
            ->whereYear('stock_outs.reporting_date', now()->year)
            ->where(function ($q) use ($branchIds, $onlineShopIds) {
                if (!empty($branchIds))
                    $q->orWhereIn('users.branch_id', $branchIds);
                if (!empty($onlineShopIds))
                    $q->orWhereIn('users.online_shop_id', $onlineShopIds);
            })
            ->sum('stock_out_non_hp_items.quantity');

        $totalOut = $outHp + $outNonHp;

        return response()->json([
            'stock' => $totalStock,
            'stock_hp' => $hpStock,
            'stock_non_hp' => $nonHpStock,
            'in' => $totalIn,
            'in_hp' => $inHp,
            'in_non_hp' => (int) $inNonHp,
            'out' => $totalOut,
            'out_hp' => $outHp,
            'out_non_hp' => (int) $outNonHp
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
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();

        if (empty($branchIds) && empty($onlineShopIds)) {
            return response()->json([
                'profit_trend' => [],
                'sales_breakdown' => [],
                'summary' => [
                    'total_profit' => 0,
                    'total_revenue' => 0,
                    'total_items' => 0
                ]
            ]);
        }

        $year = $request->year ?? date('Y');
        $month = $request->month; // Optional

        // Base Query Categories
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        // 1. Profit Trend (Daily)
        $dailyStats = [];

        // Query StockOut (Transactions)
        $query = StockOut::with(['items', 'nonHpItems', 'user', 'inventoryUser'])
            ->whereIn('category', $salesCategories)
            ->whereYear('reporting_date', $year);

        if ($request->date) {
            $query->where('reporting_date', $request->date);
        } elseif ($month) {
            $query->whereMonth('reporting_date', $month);
        }

        // Scope to user access & location filter
        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;

        $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
            $q->where(function ($sub) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                if ($requestedBranchId) {
                    if (empty($branchIds) || in_array($requestedBranchId, $branchIds)) {
                        $sub->where('branch_id', $requestedBranchId);
                    } else {
                        $sub->whereRaw('1=0');
                    }
                } elseif ($requestedOnlineShopId) {
                    if (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) {
                        $sub->where('online_shop_id', $requestedOnlineShopId);
                    } else {
                        $sub->whereRaw('1=0');
                    }
                } else {
                    if (!empty($branchIds))
                        $sub->orWhereIn('branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $sub->orWhereIn('online_shop_id', $onlineShopIds);
                }
            });
        });

        $transactions = $query->oldest()->get();

        $totalRevenue = 0;
        $totalCost = 0;
        $totalItems = 0;

        // Breakdown Stats
        $breakdown = [];

        foreach ($transactions as $trx) {
            $date = $trx->reporting_date;

            // Calculate Cost
            $trxCost = 0;
            $trxItems = 0;

            // HP Items Cost
            foreach ($trx->items as $item) {
                $trxCost += $item->cost_price;
                $trxItems++;
            }

            // Non-HP Items Cost (Limitation: Assuming 0 or need product reference)
            foreach ($trx->nonHpItems as $nhp) {
                $trxItems += $nhp->quantity;
            }

            $profit = $trx->selling_price - $trxCost;

            // Update Total Stats
            $totalRevenue += $trx->selling_price;
            $totalCost += $trxCost;
            $totalItems += $trxItems;

            // Daily Stats for Trend Chart
            if (!isset($dailyStats[$date])) {
                $dailyStats[$date] = [
                    'date' => $date,
                    'profit' => 0,
                    'revenue' => 0,
                    'items' => 0
                ];
            }
            $dailyStats[$date]['profit'] += $profit;
            $dailyStats[$date]['revenue'] += $trx->selling_price;
            $dailyStats[$date]['items'] += $trxItems;

            // Breakdown by CS (Akun Inventory)
            $sourceName = 'Unknown CS';

            if ($trx->inventoryUser) {
                $sourceName = $trx->inventoryUser->full_name;
            } elseif ($trx->user) {
                $sourceName = $trx->user->full_name;
            }

            if (!isset($breakdown[$sourceName])) {
                $breakdown[$sourceName] = [
                    'name' => $sourceName,
                    'profit' => 0,
                    'revenue' => 0,
                    'items' => 0
                ];
            }
            $breakdown[$sourceName]['profit'] += $profit;
            $breakdown[$sourceName]['revenue'] += $trx->selling_price;
            $breakdown[$sourceName]['items'] += $trxItems;
        }

        // Daily Comparison Logic (if date provided)
        $comparison = null;
        if ($request->date) {
            $targetDate = $request->date;
            $prevDate = date('Y-m-d', strtotime($targetDate . ' -1 day'));

            $targetStats = $dailyStats[$targetDate] ?? ['profit' => 0, 'revenue' => 0, 'items' => 0];
            $prevStats = $dailyStats[$prevDate] ?? ['profit' => 0, 'revenue' => 0, 'items' => 0];

            $comparison = [
                'date' => $targetDate,
                'profit' => $targetStats['profit'],
                'revenue' => $targetStats['revenue'],
                'items' => $targetStats['items'],
                'prev_date' => $prevDate,
                'prev_profit' => $prevStats['profit'],
                'prev_revenue' => $prevStats['revenue'],
                'profit_diff' => $targetStats['profit'] - $prevStats['profit'],
                'revenue_diff' => $targetStats['revenue'] - $prevStats['revenue'],
                'percentage' => $prevStats['profit'] != 0 ? round((($targetStats['profit'] - $prevStats['profit']) / abs($prevStats['profit'])) * 100, 1) : 0
            ];
        }

        return response()->json([
            'summary' => [
                'total_profit' => $totalRevenue - $totalCost,
                'total_revenue' => $totalRevenue,
                'total_items' => $totalItems
            ],
            'profit_trend' => array_values($dailyStats),
            'sales_breakdown' => array_values($breakdown),
            'comparison' => $comparison
        ]);
    }

    /**
     * Get audit checklist questions + existing answers for a stock_out.
     * Merges answered questions (with snapshotted content) + current unanswered questions.
     */
    public function getChecklist($stockOutId)
    {
        $stockOut = StockOut::findOrFail($stockOutId);
        $category = $stockOut->category;

        // Contextual Mapping: If we are auditing a received transfer, use the "In" category questions
        if ($category === 'pindah_cabang' && $stockOut->status === 'received') {
            $category = 'pindah_cabang_masuk';
        } elseif ($category === 'Barang Masuk Inventory') {
            $category = 'barang_masuk';
        }

        // Get current questions for this category
        $currentQuestions = Question::where('category', $category)->orderBy('id')->get();
        $currentQuestionIds = $currentQuestions->pluck('id')->toArray();

        // Get existing answers for this transaction
        $existingAnswers = AuditAnswer::where('stock_out_id', $stockOutId)->get();

        $checklist = collect();
        $answeredQuestionIds = [];

        // 1. Add previously answered questions (use snapshotted content if available)
        foreach ($existingAnswers as $ans) {
            $answeredQuestionIds[] = $ans->question_id;
            $checklist->push([
                'question_id' => $ans->question_id,
                'content' => $ans->question_content ?? optional(Question::find($ans->question_id))->content ?? 'Pertanyaan dihapus',
                'answer' => (bool) $ans->answer,
                'notes' => $ans->notes,
                'answered_at' => $ans->updated_at?->toDateTimeString(),
                'is_deleted' => $ans->question_id === null || !in_array($ans->question_id, $currentQuestionIds),
            ]);
        }

        // 2. Add current questions that haven't been answered yet,
        //    OR that were answered but the content has since been edited (new version = new question)
        foreach ($currentQuestions as $q) {
            if (!in_array($q->id, $answeredQuestionIds)) {
                // Never answered — add as new
                $checklist->push([
                    'question_id' => $q->id,
                    'content' => $q->content,
                    'answer' => null,
                    'notes' => null,
                    'answered_at' => null,
                    'is_deleted' => false,
                ]);
            } else {
                // Was answered — check if the question text was edited since the answer
                $existingAns = $existingAnswers->firstWhere('question_id', $q->id);
                if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $q->content) {
                    // Question was edited: mark old answer as 'is_deleted' (old version) and add new version
                    $checklist->push([
                        'question_id' => $q->id,
                        'content' => $q->content,
                        'answer' => null,
                        'notes' => null,
                        'answered_at' => null,
                        'is_deleted' => false,
                    ]);
                    // Mark the old answered version as edited
                    $checklist->transform(function ($item) use ($q, $existingAns) {
                        if ($item['question_id'] === $q->id && $item['content'] === $existingAns->question_content && $item['answer'] !== null) {
                            $item['is_deleted'] = true;
                        }
                        return $item;
                    });
                }
            }
        }

        $answeredCount = $existingAnswers->count();
        $yesCount = $existingAnswers->where('answer', true)->count();
        $totalQuestions = $checklist->count();
        $latestAnswer = $existingAnswers->max('updated_at');

        return response()->json([
            'stock_out_id' => (int) $stockOutId,
            'category' => $category,
            'questions' => $checklist->values(),
            'total' => $totalQuestions,
            'answered' => $answeredCount,
            'yes_count' => $yesCount,
            'score' => $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : 0,
            'audited_at' => $latestAnswer ? \Carbon\Carbon::parse($latestAnswer)->toDateTimeString() : null,
        ]);
    }

    /**
     * Save/update audit checklist answers with notes and question content snapshot.
     */
    public function saveChecklist(Request $request, $stockOutId)
    {
        $stockOut = StockOut::findOrFail($stockOutId);
        $user = $request->user();

        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.answer' => 'required|boolean',
            'answers.*.notes' => 'nullable|string|max:1000',
            'answers.*.content' => 'nullable|string',
        ]);

        foreach ($request->answers as $item) {
            $question = Question::find($item['question_id']);
            $content = $item['content'] ?? ($question ? $question->content : null);

            AuditAnswer::updateOrCreate(
                [
                    'stock_out_id' => $stockOutId,
                    'question_id' => $item['question_id'],
                ],
                [
                    'answer' => $item['answer'],
                    'auditor_id' => $user->id,
                    'question_content' => $content,
                    'notes' => $item['notes'] ?? null,
                ]
            );
        }

        // Return updated score
        $allAnswers = AuditAnswer::where('stock_out_id', $stockOutId)->get();
        $totalQuestions = $allAnswers->count();
        $yesCount = $allAnswers->where('answer', true)->count();
        $score = $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : 0;

        return response()->json([
            'message' => 'Checklist berhasil disimpan',
            'score' => $score,
            'answered' => $totalQuestions,
            'yes_count' => $yesCount,
            'total' => $totalQuestions,
            'audited_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get sales data with profit information for audit.
     */
    public function profit(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();

        if (empty($branchIds) && empty($onlineShopIds)) {
            return response()->json([
                'daily_sales' => [],
                'brand_sales' => [],
                'cs_sales' => []
            ]);
        }

        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;

        $scopeToAccess = function ($query) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
            $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                $q->where(function ($sub) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId) {
                        if (empty($branchIds) || in_array($requestedBranchId, $branchIds)) {
                            $sub->where('branch_id', $requestedBranchId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedOnlineShopId) {
                        if (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) {
                            $sub->where('online_shop_id', $requestedOnlineShopId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } else {
                        if (!empty($branchIds)) {
                            $sub->orWhereIn('branch_id', $branchIds);
                        }
                        if (!empty($onlineShopIds)) {
                            $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        }
                    }
                });
            });
        };

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        $dailySalesQuery = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'auditProfit'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        $scopeToAccess($dailySalesQuery);

        $paginatedProfit = $dailySalesQuery->latest()->paginate(50);
        
        $dailySales = collect($paginatedProfit->items())->map(function ($trx) {
            $details = [];
            $calculatedTotal = 0;

            // HP Items
            foreach ($trx->items as $item) {
                $price = ($item->selling_price > 0) ? $item->selling_price : ($item->product->price ?? 0);
                $details[] = [
                    'id' => 'hp_' . $item->id,
                    'name' => $item->product->name ?? 'Unknown HP',
                    'qty' => 1,
                    'price' => $price,
                    'is_fixed' => true,
                    'brand' => $item->product->brand ?? '-',
                    'type' => 'HP',
                    'imei' => $item->imei ?? '-',
                    'storage' => $item->ram && $item->storage ? $item->ram . ' / ' . $item->storage : ($item->storage ?? null),
                    'condition' => $item->condition === 'new' ? 'new' : ($item->condition === 'ex_ibox' ? 'ex_ibox' : ($item->condition ?? 'second')),
                    'raw_cost_price' => (float) ($item->cost_price ?? 0),
                ];
                $calculatedTotal += $price;
            }

            // Non-HP Items
            $jsonItems = $trx->non_hp_items;
            $hasJsonData = is_array($jsonItems) && count($jsonItems) > 0;

            if ($hasJsonData) {
                $productMap = $trx->nonHpItems->pluck('product', 'product_id');
                foreach ($jsonItems as $idx => $itemData) {
                    $pid = $itemData['product_id'] ?? null;
                    $product = $productMap[$pid] ?? null;
                    $name = $product ? $product->name : ($itemData['product_name'] ?? 'Item Non-HP');
                    $price = $itemData['selling_price'] ?? 0;
                    $qty = $itemData['quantity'] ?? 1;
                    $details[] = [
                        'id' => 'nonhp_json_' . $idx,
                        'name' => $name,
                        'qty' => $qty,
                        'price' => $price,
                        'is_fixed' => true,
                        'brand' => $product ? ($product->brand ?? '-') : '-',
                        'type' => 'Non-HP',
                        'raw_cost_price' => (float) ($product ? ($product->cost_price ?? 0) : 0)
                    ];
                    $calculatedTotal += ($price * $qty);
                }
            } else {
                foreach ($trx->nonHpItems as $nhp) {
                    $basePrice = $nhp->product->price ?? 0;
                    $details[] = [
                        'id' => 'nonhp_' . $nhp->id,
                        'name' => $nhp->product->name ?? 'Unknown Item',
                        'qty' => $nhp->quantity,
                        'price' => $basePrice,
                        'is_fixed' => true,
                        'brand' => $nhp->product->brand ?? '-',
                        'type' => 'Non-HP',
                        'raw_cost_price' => (float) ($nhp->product->cost_price ?? 0)
                    ];
                    $calculatedTotal += ($basePrice * $nhp->quantity);
                }
            }

            // Gap handling
            $remainingBalance = $trx->selling_price - $calculatedTotal;
            if (abs($remainingBalance) > 1) {
                $details[] = [
                    'id' => 'gap_1',
                    'name' => $remainingBalance > 0 ? 'Biaya Admin / Tambahan' : 'Diskon',
                    'qty' => 1,
                    'price' => $remainingBalance,
                    'brand' => '-',
                    'type' => 'Lainnya',
                    'raw_cost_price' => 0
                ];
            }

            // Outlet Details
            $outletName = 'APEX POS';
            $sourceUser = $trx->inventoryUser ?? $trx->user;
            if ($sourceUser) {
                if ($sourceUser->branch_id) {
                    $branch = \App\Models\Branch::find($sourceUser->branch_id);
                    if ($branch)
                        $outletName = $branch->name;
                } elseif ($sourceUser->online_shop_id) {
                    $shop = \App\Models\OnlineShop::find($sourceUser->online_shop_id);
                    if ($shop)
                        $outletName = $shop->name;
                }
            }

            // Profit calculation per item
            $savedProfit = $trx->auditProfit;
            $itemsModalData = $savedProfit ? ($savedProfit->items_modal ?? []) : [];

            $totalHargaModal = 0;
            $totalHargaJual = 0;

            foreach ($details as &$detail) {
                $itemJualTotal = $detail['price'] * $detail['qty']; // Aggregated sell price for this item row
                // Use actual cost price from item if available, otherwise fallback to 95%
                $defaultItemModal = (isset($detail['raw_cost_price']) && $detail['raw_cost_price'] > 0)
                    ? $detail['raw_cost_price']
                    : ($itemJualTotal > 0 ? round($itemJualTotal * 0.95) : 0);

                // If auditor saved a specific modal for this item row, use it
                $savedItemModal = null;
                if (is_array($itemsModalData) && isset($itemsModalData[$detail['id']])) {
                    $savedItemModal = (float) $itemsModalData[$detail['id']];
                }

                $effectiveItemModal = $savedItemModal ?? $defaultItemModal;
                $itemProfit = $itemJualTotal - $effectiveItemModal;

                $detail['harga_jual'] = $itemJualTotal;
                $detail['default_harga_modal'] = $defaultItemModal;
                $detail['harga_modal'] = $savedItemModal;
                $detail['profit'] = $itemProfit;
                $detail['has_saved_modal'] = $savedItemModal !== null;

                $totalHargaModal += $effectiveItemModal;
                $totalHargaJual += $itemJualTotal;
            }
            unset($detail);

            // Total Profit calculation (sum of items)
            $hargaJual = (float) ($trx->selling_price ?? 0);
            $hargaModal = $savedProfit ? (float) $savedProfit->harga_modal : null;
            $defaultHargaModal = $hargaJual > 0 ? round($hargaJual * 0.95) : 0;

            // Effective sum of all item modals
            $effectiveHargaModal = $totalHargaModal;
            $profit = $hargaJual - $effectiveHargaModal;

            // Audit score using 'profit' category (must match getProfitChecklist logic)
            $currentProfitQuestions = Question::where('category', 'profit')->get();
            $profitQuestionIds = $currentProfitQuestions->pluck('id')->toArray();
            $profitAnswers = $trx->auditAnswers->filter(function ($a) use ($profitQuestionIds) {
                return in_array($a->question_id, $profitQuestionIds) || $a->question_id === null;
            });
            $yesCount = $profitAnswers->where('answer', true)->count();
            $totalQuestions = $currentProfitQuestions->count();

            // Count edited questions as additional unanswered
            foreach ($currentProfitQuestions as $cq) {
                $existingAns = $profitAnswers->firstWhere('question_id', $cq->id);
                if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $cq->content) {
                    $totalQuestions++;
                }
            }
            // Count orphaned answers (deleted questions)
            foreach ($profitAnswers as $ans) {
                if ($ans->question_id === null || !$currentProfitQuestions->contains('id', $ans->question_id)) {
                    $totalQuestions++;
                }
            }

            $auditScore = null;
            if ($profitAnswers->count() > 0 && $totalQuestions > 0) {
                $auditScore = round(($yesCount / $totalQuestions) * 100);
            }

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'order_no' => $trx->receipt_id,
                'customer_name' => $trx->customer_name ?? $trx->receiver_name ?? $trx->shopee_receiver ?? $trx->giveaway_receiver ?? '-',
                'customer_phone' => $trx->customer_phone ?? $trx->shopee_phone ?? $trx->giveaway_phone ?? '-',
                'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                'brand_names' => collect()->concat($trx->items->map(fn($i) => $i->product->brand ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->brand ?? '-'))->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-',
                'product_names' => collect()->concat($trx->items->map(fn($i) => $i->product->name ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->name ?? '-'))->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: '-',
                'imeis' => $trx->items->map(fn($i) => $i->imei)->filter()->implode(', ') ?: '-',
                'qty' => $trx->items->count() + ($trx->non_hp_items ? collect($trx->non_hp_items)->sum('quantity') : $trx->nonHpItems->sum('quantity')),
                'items' => $details,
                'status' => $trx->status === 'received' ? 'Lunas' : 'Pending',
                'harga_jual' => $hargaJual,
                'harga_modal' => $hargaModal,
                'default_harga_modal' => $defaultHargaModal,
                'profit' => $profit,
                'has_saved_modal' => $savedProfit !== null,
                'outlet_name' => $outletName,
                'audit_score' => $auditScore,
                'audit_answered' => $trx->auditAnswers->whereIn(
                    'question_id',
                    Question::where('category', 'profit')->pluck('id')
                )->count(),
                'audit_total' => $totalQuestions,
                'audit_yes' => $yesCount,
            ];
        });

        return response()->json([
            'daily_sales' => [
                'data' => $dailySales,
                'current_page' => $paginatedProfit->currentPage(),
                'last_page' => $paginatedProfit->lastPage(),
                'total' => $paginatedProfit->total(),
                'per_page' => $paginatedProfit->perPage(),
            ],
        ]);
    }

    /**
     * Save/update auditor's harga modal for a stock_out.
     */
    public function saveProfitData(Request $request, $stockOutId)
    {
        $stockOut = StockOut::findOrFail($stockOutId);
        $user = $request->user();

        $request->validate([
            'items_modal' => 'required|array',
        ]);

        $itemsModal = $request->items_modal;
        $totalModal = array_sum(array_map('floatval', $itemsModal));

        $auditProfit = AuditProfit::updateOrCreate(
            ['stock_out_id' => $stockOutId],
            [
                'harga_modal' => $totalModal,
                'items_modal' => $itemsModal,
                'auditor_id' => $user->id,
            ]
        );

        $profit = $stockOut->selling_price - $totalModal;

        return response()->json([
            'message' => 'Harga modal berhasil disimpan',
            'harga_modal' => $totalModal,
            'items_modal' => $itemsModal,
            'profit' => $profit,
        ]);
    }

    /**
     * Get audit checklist questions for profit category.
     * Merges answered (snapshotted) + current unanswered questions.
     */
    public function getProfitChecklist($stockOutId)
    {
        $stockOut = StockOut::findOrFail($stockOutId);

        $currentQuestions = Question::where('category', 'profit')->orderBy('id')->get();
        $currentQuestionIds = $currentQuestions->pluck('id')->toArray();

        // Load all answers for this transaction
        $existingAnswers = AuditAnswer::where('stock_out_id', $stockOutId)->get();

        $checklist = collect();
        $answeredQuestionIds = [];

        foreach ($existingAnswers as $ans) {
            $answeredQuestionIds[] = $ans->question_id;
            $checklist->push([
                'question_id' => $ans->question_id,
                'content' => $ans->question_content ?? optional(Question::find($ans->question_id))->content ?? 'Pertanyaan dihapus',
                'answer' => (bool) $ans->answer,
                'notes' => $ans->notes,
                'answered_at' => $ans->updated_at?->toDateTimeString(),
                'is_deleted' => $ans->question_id === null || !in_array($ans->question_id, $currentQuestionIds),
            ]);
        }

        // 2. Add current questions that haven't been answered yet,
        //    OR that were answered but the content has since been edited
        foreach ($currentQuestions as $q) {
            if (!in_array($q->id, $answeredQuestionIds)) {
                $checklist->push([
                    'question_id' => $q->id,
                    'content' => $q->content,
                    'answer' => null,
                    'notes' => null,
                    'answered_at' => null,
                    'is_deleted' => false,
                ]);
            } else {
                $existingAns = $existingAnswers->firstWhere('question_id', $q->id);
                if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $q->content) {
                    $checklist->push([
                        'question_id' => $q->id,
                        'content' => $q->content,
                        'answer' => null,
                        'notes' => null,
                        'answered_at' => null,
                        'is_deleted' => false,
                    ]);
                    $checklist->transform(function ($item) use ($q, $existingAns) {
                        if ($item['question_id'] === $q->id && $item['content'] === $existingAns->question_content && $item['answer'] !== null) {
                            $item['is_deleted'] = true;
                        }
                        return $item;
                    });
                }
            }
        }

        $answeredCount = $existingAnswers->count();
        $yesCount = $existingAnswers->where('answer', true)->count();
        $totalQuestions = $checklist->count();
        $latestAnswer = $existingAnswers->max('updated_at');

        return response()->json([
            'stock_out_id' => (int) $stockOutId,
            'category' => 'profit',
            'questions' => $checklist->values(),
            'total' => $totalQuestions,
            'answered' => $answeredCount,
            'yes_count' => $yesCount,
            'score' => $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : 0,
            'audited_at' => $latestAnswer ? \Carbon\Carbon::parse($latestAnswer)->toDateTimeString() : null,
        ]);
    }

    /**
     * Save profit checklist answers with notes and question content snapshot.
     */
    public function saveProfitChecklist(Request $request, $stockOutId)
    {
        $stockOut = StockOut::findOrFail($stockOutId);
        $user = $request->user();

        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.answer' => 'required|boolean',
            'answers.*.notes' => 'nullable|string|max:1000',
            'answers.*.content' => 'nullable|string',
        ]);

        foreach ($request->answers as $item) {
            $question = Question::find($item['question_id']);
            $content = $item['content'] ?? ($question ? $question->content : null);

            AuditAnswer::updateOrCreate(
                [
                    'stock_out_id' => $stockOutId,
                    'question_id' => $item['question_id'],
                ],
                [
                    'answer' => $item['answer'],
                    'auditor_id' => $user->id,
                    'question_content' => $content,
                    'notes' => $item['notes'] ?? null,
                ]
            );
        }

        $allAnswers = AuditAnswer::where('stock_out_id', $stockOutId)->get();
        $totalQuestions = $allAnswers->count();
        $yesCount = $allAnswers->where('answer', true)->count();
        $score = $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : 0;

        return response()->json([
            'message' => 'Checklist profit berhasil disimpan',
            'score' => $score,
            'answered' => $totalQuestions,
            'yes_count' => $yesCount,
            'total' => $totalQuestions,
            'audited_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Export Audit Sales data as CSV.
     */
    public function exportSales(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();

        if (empty($branchIds) && empty($onlineShopIds)) {
            return response()->json(['error' => 'No access'], 403);
        }

        // Date Filter
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        // Filter by specific location
        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;

        $scopeToAccess = function ($query) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
            $query->whereHas('user', function ($q) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                $q->where(function ($sub) use ($branchIds, $onlineShopIds, $requestedBranchId, $requestedOnlineShopId) {
                    if ($requestedBranchId) {
                        if (empty($branchIds) || in_array($requestedBranchId, $branchIds)) {
                            $sub->where('branch_id', $requestedBranchId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } elseif ($requestedOnlineShopId) {
                        if (empty($onlineShopIds) || in_array($requestedOnlineShopId, $onlineShopIds)) {
                            $sub->where('online_shop_id', $requestedOnlineShopId);
                        } else {
                            $sub->whereRaw('1=0');
                        }
                    } else {
                        if (!empty($branchIds)) {
                            $sub->orWhereIn('branch_id', $branchIds);
                        }
                        if (!empty($onlineShopIds)) {
                            $sub->orWhereIn('online_shop_id', $onlineShopIds);
                        }
                    }
                });
            });
        };

        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline'];

        $dailySalesQuery = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        $scopeToAccess($dailySalesQuery);

        $items = $dailySalesQuery->latest()->get();

        $callback = function () use ($items) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            fputcsv($file, [
                'Waktu',
                'No Pesanan',
                'Nama Pelanggan',
                'Kategori',
                'Tipe',
                'Brand',
                'Nama Type',
                'Jumlah Barang',
                'Status',
                'Audit Score (%)'
            ]);

            foreach ($items as $trx) {
                // Calculate Audit Score (Logic from sales() method)
                $categoryQuestions = Question::where('category', $trx->category)->get();
                $currentQuestionIds = $categoryQuestions->pluck('id')->toArray();
                $existingAnswers = $trx->auditAnswers;

                $totalQuestions = $categoryQuestions->count();
                $yesCount = $existingAnswers->where('answer', true)->count();

                // Snapshot handling logic similar to getChecklist
                foreach ($categoryQuestions as $cq) {
                    $existingAns = $existingAnswers->firstWhere('question_id', $cq->id);
                    if ($existingAns && $existingAns->question_content && $existingAns->question_content !== $cq->content) {
                        $totalQuestions++;
                    }
                }
                foreach ($existingAnswers as $ans) {
                    if ($ans->question_id === null || !in_array($ans->question_id, $currentQuestionIds)) {
                        $totalQuestions++;
                    }
                }

                $auditScore = $totalQuestions > 0 ? round(($yesCount / $totalQuestions) * 100) : null;

                fputcsv($file, [
                    $trx->created_at->format('Y-m-d H:i'),
                    $trx->receipt_id,
                    $trx->customer_name ?? $trx->receiver_name ?? $trx->shopee_receiver ?? $trx->giveaway_receiver ?? '-',
                    $trx->category,
                    $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                    $trx->items->map(fn($i) => $i->product->brand ?? '-')->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: ($trx->nonHpItems->map(fn($i) => $i->product->brand ?? '-')->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-'),
                    $trx->items->map(fn($i) => $i->product->name ?? '-')->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: ($trx->nonHpItems->map(fn($i) => $i->product->name ?? '-')->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: '-'),
                    $trx->items->count() + ($trx->non_hp_items ? collect($trx->non_hp_items)->sum('quantity') : $trx->nonHpItems->sum('quantity')),
                    $trx->status === 'received' ? 'Lunas' : 'Pending',
                    $auditScore !== null ? $auditScore . '%' : '-'
                ]);
            }

            fclose($file);
        };

        $filename = 'audit-sales-export-' . now()->format('Y-m-d') . '.csv';

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**

     * Get stock-in data for audit.
     */
    public function stockIn(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();
        $warehouseIds = $user->getAccessibleWarehouseIds();

        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;

        $categories = ['barang_masuk', 'pindah_cabang_masuk', 'pindah_cabang'];

        $query = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'destination'])
            ->whereIn('category', $categories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        // Only received transfers are relevant for "In"
        $query->where(function ($q) {
            $q->where('category', '!=', 'pindah_cabang')
                ->orWhere('status', 'received');
        });

        // Filter by location
        $query->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId) {
            // For barang_masuk_inventory (manual), filter by inventoryUser's location
            $q->where(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId) {
                $sub->whereIn('category', ['barang_masuk', 'Barang Masuk Inventory']);
                $sub->whereHas('inventoryUser', function ($sq) use ($branchIds, $onlineShopIds, $warehouseIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId) {
                    if ($requestedBranchId) {
                        $sq->where('branch_id', $requestedBranchId);
                    } elseif ($requestedOnlineShopId) {
                        $sq->where('online_shop_id', $requestedOnlineShopId);
                    } elseif ($requestedWarehouseId) {
                        $sq->where('warehouse_id', $requestedWarehouseId);
                    } else {
                        if (!empty($branchIds))
                            $sq->orWhereIn('branch_id', $branchIds);
                        if (!empty($onlineShopIds))
                            $sq->orWhereIn('online_shop_id', $onlineShopIds);
                        if (!empty($warehouseIds))
                            $sq->orWhereIn('warehouse_id', $warehouseIds);
                    }
                });
            });

            // For pindah_cabang (transfers), filter by destination
            $q->orWhere(function ($sub) use ($branchIds, $onlineShopIds, $warehouseIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId) {
                $sub->where('category', 'pindah_cabang');
                if ($requestedBranchId) {
                    $sub->where('destination_type', 'branch')->where('destination_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $sub->where('destination_type', 'online_shop')->where('destination_id', $requestedOnlineShopId);
                } elseif ($requestedWarehouseId) {
                    $sub->where('destination_type', 'warehouse')->where('destination_id', $requestedWarehouseId);
                } else {
                    $sub->where(function ($inner) use ($branchIds, $onlineShopIds, $warehouseIds) {
                        if (!empty($branchIds)) {
                            $inner->orWhere(function ($ss) use ($branchIds) {
                                $ss->where('destination_type', 'branch')->whereIn('destination_id', $branchIds);
                            });
                        }
                        if (!empty($onlineShopIds)) {
                            $inner->orWhere(function ($ss) use ($onlineShopIds) {
                                $ss->where('destination_type', 'online_shop')->whereIn('destination_id', $onlineShopIds);
                            });
                        }
                        if (!empty($warehouseIds)) {
                            $inner->orWhere(function ($ss) use ($warehouseIds) {
                                $ss->where('destination_type', 'warehouse')->whereIn('destination_id', $warehouseIds);
                            });
                        }
                    });
                }
            });
        });

        $paginatedIn = $query->latest()->paginate(50);
        
        $records = collect($paginatedIn->items())->map(function ($trx) {
            $hpItemsCount = $trx->items->count();
            // Non-HP count from audit payload or relation
            $nonHpItemsCount = 0;
            if ($trx->items->isEmpty()) {
                if (is_array($trx->non_hp_items)) {
                    $nonHpItemsCount = collect($trx->non_hp_items)->sum('quantity');
                } elseif ($trx->nonHpItems->isNotEmpty()) {
                    $nonHpItemsCount = $trx->nonHpItems->sum('quantity');
                }
            }

            // Audit score
            $auditAnsCount = $trx->auditAnswers->count();
            $yesCount = $trx->auditAnswers->where('answer', true)->count();
            $currentQuestions = Question::where('category', $trx->category)->count();

            // Only calculate score if there is at least 1 answer
            $score = null;
            if ($auditAnsCount > 0 && $currentQuestions > 0) {
                $score = round(($yesCount / $currentQuestions) * 100);
            }

            $sourceLabel = 'Unknown';
            if ($trx->category === 'pindah_cabang') {
                $sourceUser = $trx->user;
                $sourceLabel = $sourceUser->branch?->name ?? $sourceUser->warehouse?->name ?? 'External';
            } else {
                $sourceLabel = 'Manual Entry';
            }

            $actualCategory = $trx->category;
            // Map for frontend display in the context of Stock In audit
            $displayCategory = $actualCategory;
            if ($actualCategory === 'pindah_cabang') {
                $displayCategory = 'pindah_cabang_masuk';
            } elseif ($actualCategory === 'Barang Masuk Inventory') {
                $displayCategory = 'barang_masuk';
            }

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'receipt_id' => $trx->receipt_id,
                'category' => $displayCategory,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                'brand_names' => collect()->concat($trx->items->map(fn($i) => $i->product->brand ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->brand ?? '-'))->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-',
                'product_names' => collect()->concat($trx->items->map(fn($i) => $i->product->name ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->name ?? '-'))->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: '-',
                'imeis' => $trx->items->map(fn($i) => $i->imei)->filter()->implode(', ') ?: '-',
                'storages' => $trx->items->map(fn($i) => $i->ram && $i->storage ? $i->ram . '/' . $i->storage : $i->storage)->filter()->unique()->implode(', ') ?: null,
                'conditions' => $trx->items->map(fn($i) => match ($i->condition) { 'new' => 'Baru', 'ex_ibox' => 'Ex iBox', default => 'Second'})->filter()->unique()->implode(', ') ?: null,
                'qty' => $hpItemsCount + $nonHpItemsCount,
                'source' => $sourceLabel,
                'audit_score' => $score,
                'audit_answered' => $auditAnsCount,
                'audit_total' => $currentQuestions,
            ];
        });

        return response()->json([
            'data' => $records,
            'current_page' => $paginatedIn->currentPage(),
            'last_page' => $paginatedIn->lastPage(),
            'total' => $paginatedIn->total(),
            'per_page' => $paginatedIn->perPage(),
        ]);
    }

    /**
     * Get stock-out data for audit.
     */
    public function stockOut(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->getAccessibleBranchIds();
        $onlineShopIds = $user->getAccessibleOnlineShopIds();
        $warehouseIds = $user->getAccessibleWarehouseIds();

        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();

        $requestedBranchId = $request->branch_id;
        $requestedOnlineShopId = $request->online_shop_id;
        $requestedWarehouseId = $request->warehouse_id;

        $categories = [
            'penjualan_offline',
            'orderan_online',
            'pindah_cabang',
            'retur',
            'kesalahan_input',
            'giveaway_customer',
            'hadiah',
            'brand_ambassador',
            'promo',
            'inventaris'
        ];

        $query = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'inventoryUser', 'auditAnswers', 'destination'])
            ->whereIn('category', $categories)
            ->whereBetween('reporting_date', [$startDate, $endDate]);

        // Filter by location
        $query->where(function ($q) use ($branchIds, $onlineShopIds, $warehouseIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId) {
            $q->whereHas('inventoryUser', function ($sq) use ($branchIds, $onlineShopIds, $warehouseIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId) {
                if ($requestedBranchId) {
                    $sq->where('branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $sq->where('online_shop_id', $requestedOnlineShopId);
                } elseif ($requestedWarehouseId) {
                    $sq->where('warehouse_id', $requestedWarehouseId);
                } else {
                    if (!empty($branchIds))
                        $sq->orWhereIn('branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $sq->orWhereIn('online_shop_id', $onlineShopIds);
                    if (!empty($warehouseIds))
                        $sq->orWhereIn('warehouse_id', $warehouseIds);
                }
            })->orWhereHas('user', function ($sq) use ($branchIds, $onlineShopIds, $warehouseIds, $requestedBranchId, $requestedOnlineShopId, $requestedWarehouseId) {
                if ($requestedBranchId) {
                    $sq->where('branch_id', $requestedBranchId);
                } elseif ($requestedOnlineShopId) {
                    $sq->where('online_shop_id', $requestedOnlineShopId);
                } elseif ($requestedWarehouseId) {
                    $sq->where('warehouse_id', $requestedWarehouseId);
                } else {
                    if (!empty($branchIds))
                        $sq->orWhereIn('branch_id', $branchIds);
                    if (!empty($onlineShopIds))
                        $sq->orWhereIn('online_shop_id', $onlineShopIds);
                    if (!empty($warehouseIds))
                        $sq->orWhereIn('warehouse_id', $warehouseIds);
                }
            });
        });

        $paginatedOut = $query->latest()->paginate(50);
        
        $records = collect($paginatedOut->items())->map(function ($trx) {
            $hpItemsCount = $trx->items->count();
            // Non-HP count from audit payload or relation
            $nonHpItemsCount = 0;
            if ($trx->items->isEmpty()) {
                if (is_array($trx->non_hp_items)) {
                    $nonHpItemsCount = collect($trx->non_hp_items)->sum('quantity');
                } elseif ($trx->nonHpItems->isNotEmpty()) {
                    $nonHpItemsCount = $trx->nonHpItems->sum('quantity');
                }
            }

            // Audit score
            $auditAnsCount = $trx->auditAnswers->count();
            $yesCount = $trx->auditAnswers->where('answer', true)->count();
            $currentQuestions = Question::where('category', $trx->category)->count();

            // Only calculate score if there is at least 1 answer
            $score = null;
            if ($auditAnsCount > 0 && $currentQuestions > 0) {
                $score = round(($yesCount / $currentQuestions) * 100);
            }

            $sourceLabel = 'Internal';
            if ($trx->category === 'pindah_cabang' && $trx->destination) {
                $sourceLabel = $trx->destination->name;
            } elseif (in_array($trx->category, ['penjualan_offline', 'orderan_online'])) {
                $sourceLabel = 'Customer';
            } else {
                $sourceLabel = 'Manual Entry';
            }

            return [
                'id' => $trx->id,
                'date' => $trx->created_at->toDateTimeString(),
                'receipt_id' => $trx->receipt_id,
                'category' => $trx->category,
                'type' => $trx->items->isNotEmpty() ? 'HP' : 'Non-HP',
                'brand_names' => collect()->concat($trx->items->map(fn($i) => $i->product->brand ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->brand ?? '-'))->unique()->filter(fn($b) => $b !== '-')->implode(', ') ?: '-',
                'product_names' => collect()->concat($trx->items->map(fn($i) => $i->product->name ?? '-'))->concat($trx->nonHpItems->map(fn($i) => $i->product->name ?? '-'))->unique()->filter(fn($n) => $n !== '-')->implode(', ') ?: '-',
                'imeis' => $trx->items->map(fn($i) => $i->imei)->filter()->implode(', ') ?: '-',
                'storages' => $trx->items->map(fn($i) => $i->ram && $i->storage ? $i->ram . '/' . $i->storage : $i->storage)->filter()->unique()->implode(', ') ?: null,
                'conditions' => $trx->items->map(fn($i) => match ($i->condition) { 'new' => 'Baru', 'ex_ibox' => 'Ex iBox', default => 'Second'})->filter()->unique()->implode(', ') ?: null,
                'qty' => $hpItemsCount + $nonHpItemsCount,
                'source' => $sourceLabel,
                'audit_score' => $score,
                'audit_answered' => $auditAnsCount,
                'audit_total' => $currentQuestions,
            ];
        });

        return response()->json([
            'data' => $records,
            'current_page' => $paginatedOut->currentPage(),
            'last_page' => $paginatedOut->lastPage(),
            'total' => $paginatedOut->total(),
            'per_page' => $paginatedOut->perPage(),
        ]);
    }

    /**
     * Get checklist for stock-in audit.
     */
    public function getStockInChecklist($stockOutId)
    {
        return $this->getChecklist($stockOutId);
    }

    /**
     * Save checklist for stock-in audit.
     */
    public function saveStockInChecklist(Request $request, $stockOutId)
    {
        return $this->saveChecklist($request, $stockOutId);
    }

    /**
     * Get checklist for stock-out audit.
     */
    public function getStockOutChecklist($stockOutId)
    {
        return $this->getChecklist($stockOutId);
    }

    /**
     * Save checklist for stock-out audit.
     */
    public function saveStockOutChecklist(Request $request, $stockOutId)
    {
        return $this->saveChecklist($request, $stockOutId);
    }
}
