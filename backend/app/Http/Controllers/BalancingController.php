<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\StockOut;
use App\Models\User;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class BalancingController extends Controller
{
    /**
     * Get list of active branches for balancing selection.
     */
    public function getBranches()
    {
        $branches = Branch::where('is_active', true)
            ->where('type', 'physical')
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'timezone']);

        return response()->json(['data' => $branches]);
    }

    /**
     * Get users for a specific branch (to be used as Customer Service selection)
     */
    public function getBranchUsers(Request $request)
    {
        $branchId = $request->query('branch_id');

        if (!$branchId || $branchId === 'all') {
            $csUserIds = StockOut::where('category', StockOut::CATEGORY_BALANCING)
                ->whereNotNull('balancing_cs_user_id')
                ->pluck('balancing_cs_user_id')
                ->merge(
                    StockOut::where('category', StockOut::CATEGORY_BALANCING)
                        ->whereNotNull('inventory_user_id')
                        ->pluck('inventory_user_id')
                )
                ->unique()
                ->filter();

            $users = User::whereIn('id', $csUserIds)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json(['data' => $users]);
        }

        $userIds = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->pluck('id')
            ->merge(
                StockOut::where('branch_id', $branchId)
                    ->where('category', StockOut::CATEGORY_BALANCING)
                    ->whereNotNull('balancing_cs_user_id')
                    ->pluck('balancing_cs_user_id')
            )
            ->merge(
                StockOut::where('branch_id', $branchId)
                    ->where('category', StockOut::CATEGORY_BALANCING)
                    ->whereNotNull('inventory_user_id')
                    ->pluck('inventory_user_id')
            )
            ->unique()
            ->filter();

        $users = User::whereIn('id', $userIds)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $users]);
    }

    /**
     * Search customers based on name or phone
     */
    public function getCustomers(Request $request)
    {
        $search = $request->query('search');

        if (!$search || strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        // Search from previous StockOuts as customer history
        $customers = StockOut::where('customer_name', 'ilike', "%{$search}%")
            ->orWhere('customer_phone', 'ilike', "%{$search}%")
            ->select('customer_name', 'customer_phone')
            ->distinct()
            ->limit(10)
            ->get();

        return response()->json(['data' => $customers]);
    }

    /**
     * Get payment methods available for balancing
     */
    public function getPaymentMethods()
    {
        // Balancing might allow negative inputs to reduce omset
        $methods = PaymentMethod::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'provider']);

        return response()->json(['data' => $methods]);
    }

    /**
     * Store a new Balancing Payment Method record.
     */
    public function storePaymentMethod(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'date' => 'required|date',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_service_id' => 'required|exists:users,id',
            'notes' => 'required|string',
            'photo' => 'nullable|image|max:20480', // Max 20MB
            'payment_proof_images' => 'nullable|array',
            'payment_proof_images.*' => 'image|max:20480', // Max 20MB per image
            'payment_methods' => 'required|array|min:1',
            'payment_methods.*.method_id' => 'required|exists:payment_methods,id',
            'payment_methods.*.amount' => 'required|numeric', // allow negative
            'password' => 'required|string',
        ]);

        // Verify Super Admin Password
        $user = Auth::user();
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah.'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('balancing', 'public');
            }

            $paymentProofPaths = [];
            if ($request->hasFile('payment_proof_images')) {
                foreach ($request->file('payment_proof_images') as $file) {
                    $paymentProofPaths[] = $file->store('balancing/payment-proofs', 'public');
                }
            }
            
            $totalAmount = 0;
            foreach ($request->payment_methods as $pm) {
                $totalAmount += $pm['amount'];
            }

            // In Balancing Metode Pembayaran, we don't reduce inventory,
            // we just create a StockOut record categorized as 'balancing' to record the omset modification.
            // A StockOut without items will just serve as an invoice/transaction record.
            
            $stockOut = new StockOut();
            // Generate invoice number
            $branchCode = Branch::find($request->branch_id)->code ?? 'XX';
            $datePrefix = date('ymd');
            $count = StockOut::whereDate('created_at', today())->count() + 1;
            $stockOut->invoice_number = "INV-BAL-{$branchCode}-{$datePrefix}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
            
            $stockOut->branch_id = $request->branch_id;
            $stockOut->creator_id = $user->id; // Super admin
            $stockOut->pic_id = $request->customer_service_id; // CS selected
            
            $stockOut->customer_name = $request->customer_name;
            $stockOut->customer_phone = $request->customer_phone;
            
            $stockOut->category = StockOut::CATEGORY_BALANCING;
            $stockOut->sub_category = 'balancing_metode_pembayaran';
            
            $stockOut->status = 'completed';
            $stockOut->total_amount = $totalAmount;
            
            // Critical for omset attribution: set the reporting date to the requested date!
            $stockOut->reporting_date = $request->date; 
            
            $stockOut->notes = $request->notes;
            $stockOut->proof_image = $photoPath;
            $stockOut->payment_proof_images = $paymentProofPaths;
            
            $stockOut->save();

            // Attach Payment Methods
            foreach ($request->payment_methods as $pm) {
                $stockOut->paymentMethods()->attach($pm['method_id'], [
                    'amount' => $pm['amount'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Balancing metode pembayaran berhasil disimpan.',
                'data' => $stockOut->load('paymentMethods')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Balancing Payment Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel / Void a balancing transaction.
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string',
            'reason' => 'required|string',
        ]);

        $user = Auth::user();
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah.'
            ], 403);
        }

        $stockOut = StockOut::where('category', StockOut::CATEGORY_BALANCING)->findOrFail($id);

        if ($stockOut->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi sudah dibatalkan sebelumnya.'
            ], 422);
        }

        $stockOut->status = 'cancelled';
        $stockOut->notes = $stockOut->notes . "\n[DIBATALKAN: {$request->reason}]";
        $stockOut->save();

        return response()->json([
            'success' => true,
            'message' => 'Balancing berhasil dibatalkan.',
        ]);
    }

    /**
     * Get balancing history with filters and totals.
     * Accessible by: super_admin, audit, leader
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasAnyRole(['super_admin', 'audit', 'leader'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya dapat diakses oleh Super Admin, Audit, dan Leader.'
            ], 403);
        }

        $query = StockOut::where('category', StockOut::CATEGORY_BALANCING);

        // Branch scoping for audit & leader
        if (!$user->hasRole('super_admin')) {
            $accessibleBranchIds = $user->getAccessibleBranchIds();
            if (!empty($accessibleBranchIds)) {
                $query->whereIn('branch_id', $accessibleBranchIds);
            } else {
                $query->whereRaw('1=0');
            }
        }

        // Filter by branch
        if ($request->filled('branch_id') && $request->branch_id !== 'all') {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by CS
        if ($request->filled('cs_id') && $request->cs_id !== 'all') {
            $csId = $request->cs_id;
            $query->where(function ($q) use ($csId) {
                $q->where('balancing_cs_user_id', $csId)
                    ->orWhere('inventory_user_id', $csId);
            });
        }

        // Filter by Date: Default per month
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $sDate = $request->start_date;
            $eDate = $request->end_date;
            $query->where(function ($q) use ($sDate, $eDate) {
                $q->whereBetween('reporting_date', [$sDate, $eDate])
                    ->orWhere(function ($sq) use ($sDate, $eDate) {
                        $sq->whereNull('reporting_date')
                            ->whereBetween(DB::raw('DATE(created_at)'), [$sDate, $eDate]);
                    });
            });
        } elseif ($request->filled('date')) {
            $d = $request->date;
            $query->where(function ($q) use ($d) {
                $q->whereDate('reporting_date', $d)
                    ->orWhere(function ($sq) use ($d) {
                        $sq->whereNull('reporting_date')
                            ->whereDate('created_at', $d);
                    });
            });
        } else {
            // Default per month (YYYY-MM)
            $month = $request->month ?: date('Y-m');
            $parts = explode('-', $month);
            $y = (int) $parts[0];
            $m = (int) ($parts[1] ?? date('m'));
            $query->where(function ($q) use ($month, $m, $y) {
                $q->where('reporting_date', 'like', "{$month}%")
                    ->orWhere(function ($sq) use ($m, $y) {
                        $sq->whereNull('reporting_date')
                            ->whereMonth('created_at', $m)
                            ->whereYear('created_at', $y);
                    });
            });
        }

        // Filter search keyword
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(receipt_id) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(customer_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(customer_phone) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(notes) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(balancing_notes) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(sales_account) LIKE ?', ["%{$search}%"]);
            });
        }

        // Fetch records with relations
        $records = $query->with([
            'branch:id,name,code',
            'balancingCsUser:id,name,username',
            'inventoryUser:id,name,username',
            'user:id,name,username',
            'items.product:id,name,brand',
            'nonHpDetails.product:id,name,brand',
            'paymentMethod:id,name',
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        // Load all payment methods to resolve split names quickly
        $paymentMethodsMap = PaymentMethod::pluck('name', 'id');

        $totalOmsetMinus = 0;
        $totalOmsetPlus = 0;
        $totalSelisihPembayaran = 0;

        $items = $records->map(function ($trx) use ($paymentMethodsMap, &$totalOmsetMinus, &$totalOmsetPlus, &$totalSelisihPembayaran) {
            $isMissedSale = ($trx->sub_category === 'balancing_penjualan_terlewat' || $trx->balancing_type === 'missed_sale');
            $isPaymentBalancing = ($trx->sub_category === 'balancing_metode_pembayaran' || $trx->balancing_type === 'payment_method');

            $sellingPrice = (float) ($trx->selling_price ?? 0);
            
            // Process split payments
            $rawSplits = $trx->split_payments;
            if (is_string($rawSplits)) {
                $rawSplits = json_decode($rawSplits, true);
            }
            if (!is_array($rawSplits)) {
                $rawSplits = [];
            }

            $splitsData = [];
            $posSplitSum = 0;
            $negSplitSum = 0;
            foreach ($rawSplits as $sp) {
                $mId = $sp['payment_method_id'] ?? ($sp['method_id'] ?? null);
                $amt = (float) ($sp['amount'] ?? 0);
                $mName = $sp['method_name'] ?? ($paymentMethodsMap[$mId] ?? 'Lainnya');

                $splitsData[] = [
                    'payment_method_id' => $mId,
                    'method_name' => $mName,
                    'amount' => $amt,
                ];

                if ($amt > 0) {
                    $posSplitSum += $amt;
                } elseif ($amt < 0) {
                    $negSplitSum += abs($amt);
                }
            }

            // Calculation logic
            $nominalOmsetMinus = 0;
            $nominalOmsetPlus = 0;
            $salahMetodePembayaran = 0;

            if ($isMissedSale) {
                $nominalOmsetPlus = max(0, $sellingPrice);
                $nominalOmsetMinus = 0;
                $salahMetodePembayaran = 0;
            } elseif ($isPaymentBalancing) {
                if ($posSplitSum > 0 && $negSplitSum > 0) {
                    $salahMetodePembayaran = min($posSplitSum, $negSplitSum);
                    $nominalOmsetPlus = max(0, $posSplitSum - $negSplitSum);
                    $nominalOmsetMinus = max(0, $negSplitSum - $posSplitSum);
                } elseif ($posSplitSum > 0 && $negSplitSum == 0) {
                    $salahMetodePembayaran = 0;
                    $nominalOmsetPlus = $posSplitSum;
                    $nominalOmsetMinus = 0;
                } elseif ($negSplitSum > 0 && $posSplitSum == 0) {
                    $salahMetodePembayaran = 0;
                    $nominalOmsetPlus = 0;
                    $nominalOmsetMinus = $negSplitSum;
                } else {
                    if ($sellingPrice > 0) {
                        $nominalOmsetPlus = $sellingPrice;
                    } elseif ($sellingPrice < 0) {
                        $nominalOmsetMinus = abs($sellingPrice);
                    }
                }
            } else {
                if ($posSplitSum > 0 && $negSplitSum > 0) {
                    $salahMetodePembayaran = min($posSplitSum, $negSplitSum);
                    $nominalOmsetPlus = max(0, $posSplitSum - $negSplitSum);
                    $nominalOmsetMinus = max(0, $negSplitSum - $posSplitSum);
                } else {
                    if ($sellingPrice > 0) {
                        $nominalOmsetPlus = $sellingPrice;
                    } elseif ($sellingPrice < 0) {
                        $nominalOmsetMinus = abs($sellingPrice);
                    }
                }
            }

            // Aggregate totals (exclude cancelled)
            if ($trx->status !== 'cancelled') {
                $totalOmsetMinus += $nominalOmsetMinus;
                $totalOmsetPlus += $nominalOmsetPlus;
                $totalSelisihPembayaran += $salahMetodePembayaran;
            }

            // Category Label
            $categoryLabel = 'Balancing';
            if ($isPaymentBalancing) {
                $categoryLabel = 'Balancing Metode Pembayaran';
            } elseif ($isMissedSale) {
                $categoryLabel = 'Balancing Penjualan Terlewat';
            }

            // CS Name
            $namaCs = $trx->balancingCsUser?->name
                ?? $trx->inventoryUser?->name
                ?? $trx->sales_account
                ?? $trx->user?->name
                ?? '-';

            // Gather all photos
            $photos = [];
            if ($trx->proof_image) {
                $photos[] = [
                    'type' => 'proof',
                    'label' => 'Bukti Transaksi',
                    'url' => asset('storage/' . $trx->proof_image)
                ];
            }
            if ($trx->payment_proof_image) {
                $photos[] = [
                    'type' => 'payment_proof',
                    'label' => 'Bukti Pembayaran',
                    'url' => asset('storage/' . $trx->payment_proof_image)
                ];
            }
            if (!empty($trx->payment_proof_images) && is_array($trx->payment_proof_images)) {
                foreach ($trx->payment_proof_images as $idx => $img) {
                    $photos[] = [
                        'type' => 'payment_proof',
                        'label' => 'Bukti Pembayaran #' . ($idx + 1),
                        'url' => asset('storage/' . $img)
                    ];
                }
            }

            // Product items for missed sale
            $detailItems = [];
            if ($isMissedSale) {
                foreach ($trx->items as $hp) {
                    $detailItems[] = [
                        'type' => 'hp',
                        'name' => $hp->product?->name ?? 'HP',
                        'brand' => $hp->product?->brand ?? '-',
                        'imei' => $hp->imei ?? '-',
                        'price' => (float) ($hp->pivot->selling_price ?? $hp->selling_price ?? 0),
                        'quantity' => 1,
                    ];
                }
                foreach ($trx->nonHpDetails as $nhp) {
                    $detailItems[] = [
                        'type' => 'non_hp',
                        'name' => $nhp->product?->name ?? 'Non-HP',
                        'brand' => $nhp->product?->brand ?? '-',
                        'imei' => '-',
                        'price' => (float) ($nhp->selling_price ?? 0),
                        'quantity' => (int) ($nhp->quantity ?? 1),
                    ];
                }
            }

            return [
                'id' => $trx->id,
                'receipt_id' => $trx->receipt_id ?? "BAL-{$trx->id}",
                'tanggal' => $trx->created_at ? $trx->created_at->format('Y-m-d H:i:s') : '-',
                'tanggal_omset' => $trx->reporting_date ?? ($trx->created_at ? $trx->created_at->format('Y-m-d') : '-'),
                'kategori' => $categoryLabel,
                'sub_category' => $trx->sub_category ?? $trx->balancing_type,
                'cabang' => $trx->branch?->name ?? '-',
                'branch_id' => $trx->branch_id,
                'nama_cs' => $namaCs,
                'cs_user_id' => $trx->balancing_cs_user_id ?? $trx->inventory_user_id,
                'keterangan' => $trx->notes ?? $trx->balancing_notes ?? '-',
                'customer_name' => $trx->customer_name,
                'customer_phone' => $trx->customer_phone ?? $trx->customer_wa,
                'nominal_omset_minus' => $nominalOmsetMinus,
                'nominal_omset_plus' => $nominalOmsetPlus,
                'salah_metode_pembayaran' => $salahMetodePembayaran,
                'selling_price' => $sellingPrice,
                'split_payments' => $splitsData,
                'payment_method_name' => $trx->paymentMethod?->name ?? null,
                'photos' => $photos,
                'detail_items' => $detailItems,
                'status' => $trx->status ?? 'completed',
                'created_by' => $trx->user?->name ?? 'System',
            ];
        });

        // Get list of branches and CS for dropdowns
        $branchesQuery = Branch::where('is_active', true)->where('type', 'physical')->orderBy('name');
        if (!$user->hasRole('super_admin')) {
            $accessibleBranchIds = $user->getAccessibleBranchIds();
            if (!empty($accessibleBranchIds)) {
                $branchesQuery->whereIn('id', $accessibleBranchIds);
            } else {
                $branchesQuery->whereRaw('1=0');
            }
        }
        $branchesList = $branchesQuery->get(['id', 'name']);

        // CS dropdown list based on branch filter
        if ($request->filled('branch_id') && $request->branch_id !== 'all') {
            $filteredBranchId = $request->branch_id;
            $csUserIds = User::where('branch_id', $filteredBranchId)
                ->where('is_active', true)
                ->pluck('id')
                ->merge(
                    StockOut::where('branch_id', $filteredBranchId)
                        ->where('category', StockOut::CATEGORY_BALANCING)
                        ->whereNotNull('balancing_cs_user_id')
                        ->pluck('balancing_cs_user_id')
                )
                ->merge(
                    StockOut::where('branch_id', $filteredBranchId)
                        ->where('category', StockOut::CATEGORY_BALANCING)
                        ->whereNotNull('inventory_user_id')
                        ->pluck('inventory_user_id')
                )
                ->unique()
                ->filter();

            $csList = User::whereIn('id', $csUserIds)->select('id', 'name', 'username')->orderBy('name')->get();
        } else {
            $csQuery = StockOut::where('category', StockOut::CATEGORY_BALANCING);
            if (!$user->hasRole('super_admin')) {
                $accessibleBranchIds = $user->getAccessibleBranchIds();
                if (!empty($accessibleBranchIds)) {
                    $csQuery->whereIn('branch_id', $accessibleBranchIds);
                }
            }

            $csUserIds = (clone $csQuery)->whereNotNull('balancing_cs_user_id')
                ->pluck('balancing_cs_user_id')
                ->merge(
                    (clone $csQuery)->whereNotNull('inventory_user_id')
                        ->pluck('inventory_user_id')
                )
                ->unique()
                ->filter();

            $csList = User::whereIn('id', $csUserIds)->select('id', 'name', 'username')->orderBy('name')->get();
        }

        return response()->json([
            'success' => true,
            'data' => $items,
            'summary' => [
                'total_omset_minus' => $totalOmsetMinus,
                'total_omset_plus' => $totalOmsetPlus,
                'total_selisih_pembayaran' => $totalSelisihPembayaran,
                'total_records' => $items->count(),
            ],
            'filters' => [
                'branches' => $branchesList,
                'cs_users' => $csList,
            ]
        ]);
    }
}
