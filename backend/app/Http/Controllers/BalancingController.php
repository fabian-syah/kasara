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
        return response()->json(['data' => 'test_success']);
    }

    /**
     * Get CS (inventory) users belonging to a specific branch.
     * These are the users with role 'inventory' whose creator is assigned to this branch,
     * or toko_offline users directly assigned to the branch.
     */
    public function getBranchUsers(Request $request)
    {
        $request->validate(['branch_id' => 'required|exists:branches,id']);

        $branchId = $request->branch_id;

        // Get users directly assigned to this branch (toko_offline, inventory accounts)
        $users = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['inventory', 'toko_offline']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'code_id']);

        return response()->json(['data' => $users]);
    }

    /**
     * Get distinct customer names from previous transactions at a branch.
     * Useful for autocomplete in the balancing form.
     */
    public function getCustomers(Request $request)
    {
        $request->validate(['branch_id' => 'required|exists:branches,id']);

        $branchId = $request->branch_id;

        $customers = StockOut::where('branch_id', $branchId)
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->select('customer_name')
            ->distinct()
            ->orderBy('customer_name')
            ->limit(500)
            ->pluck('customer_name');

        return response()->json(['data' => $customers]);
    }

    /**
     * Get payment methods available for a branch.
     */
    public function getPaymentMethods(Request $request)
    {
        $request->validate(['branch_id' => 'required|exists:branches,id']);

        $branch = Branch::find($request->branch_id);

        // Get branch-specific payment methods, fall back to all active ones
        $methods = $branch->paymentMethods()->where('is_active', true)->get();

        if ($methods->isEmpty()) {
            $methods = PaymentMethod::where('is_active', true)->orderBy('name')->get();
        }

        return response()->json(['data' => $methods]);
    }

    /**
     * Store a new balancing record (Balancing Metode Pembayaran).
     * Creates a StockOut with category='balancing' and the user-specified reporting_date.
     */
    public function storePaymentMethod(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'reporting_date' => 'required|date',
            'customer_name' => 'nullable|string|max:255',
            'balancing_description' => 'nullable|string|max:500',
            'balancing_cs_user_id' => 'nullable|exists:users,id',
            'notes' => 'required|string',
            'proof_image' => 'required|image|max:20480',
            'split_payments' => 'required|string', // JSON string
            'selling_price' => 'required|numeric', // Can be negative
            'password' => 'required|string',
        ]);

        // Verify super admin password
        /** @var User $user */
        $user = Auth::user();

        if (!Hash::check($request->password, $user->getAuthPassword())) {
            return response()->json([
                'message' => 'Password salah.',
                'errors' => ['password' => ['Password yang Anda masukkan salah.']]
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Handle file upload
            $proofImagePath = null;
            if ($request->hasFile('proof_image')) {
                $proofImagePath = $request->file('proof_image')->store('stock-outs/balancing', 'public');
            }

            // Parse split_payments
            $splitPayments = json_decode($request->split_payments, true);
            if (!is_array($splitPayments) || empty($splitPayments)) {
                throw new \Exception('Metode pembayaran harus diisi minimal satu.');
            }

            // Calculate total from split payments
            $totalAmount = 0;
            $primaryMethodId = null;
            foreach ($splitPayments as $sp) {
                $totalAmount += floatval($sp['amount'] ?? 0);
                if (!$primaryMethodId && isset($sp['payment_method_id'])) {
                    $primaryMethodId = $sp['payment_method_id'];
                }
            }

            // Use explicit selling_price from request (can be negative)
            $sellingPrice = floatval($request->selling_price);

            // Create the balancing StockOut record
            $stockOut = StockOut::create([
                'receipt_id' => StockOut::generateReceiptId(),
                'category' => 'balancing',
                'balancing_type' => 'payment_method',
                'sub_category' => 'balancing_metode_pembayaran',
                'status' => 'received',
                'user_id' => Auth::id(),
                'branch_id' => $request->branch_id,
                'reporting_date' => $request->reporting_date, // User-selected date, NOT today
                'customer_name' => $request->customer_name,
                'selling_price' => $sellingPrice,
                'payment_method_id' => $primaryMethodId,
                'split_payments' => $splitPayments,
                'paid_amount' => $totalAmount,
                'notes' => $request->notes,
                'balancing_notes' => $request->balancing_description,
                'balancing_cs_user_id' => $request->balancing_cs_user_id,
                'inventory_user_id' => $request->balancing_cs_user_id,
                'proof_image' => $proofImagePath,
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info("Balancing Payment Method created", [
                'stock_out_id' => $stockOut->id,
                'receipt_id' => $stockOut->receipt_id,
                'branch_id' => $request->branch_id,
                'reporting_date' => $request->reporting_date,
                'selling_price' => $sellingPrice,
                'created_by' => Auth::id(),
            ]);

            // Load relationships for response
            $stockOut->load(['branch', 'user', 'paymentMethod']);

            return response()->json([
                'success' => true,
                'message' => 'Balancing metode pembayaran berhasil disimpan.',
                'data' => $stockOut,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Balancing store failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Gagal menyimpan balancing: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel/void a balancing record.
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string',
            'cancel_reason' => 'required|string|max:500',
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (!Hash::check($request->password, $user->getAuthPassword())) {
            return response()->json([
                'message' => 'Password salah.',
                'errors' => ['password' => ['Password yang Anda masukkan salah.']]
            ], 422);
        }

        $stockOut = StockOut::where('id', $id)
            ->where('category', 'balancing')
            ->firstOrFail();

        if ($stockOut->cancelled_at) {
            return response()->json([
                'message' => 'Balancing ini sudah dibatalkan sebelumnya.',
            ], 422);
        }

        $stockOut->update([
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'cancel_reason' => $request->cancel_reason,
            'selling_price' => 0, // Zero out the amount
        ]);

        Log::info("Balancing cancelled", [
            'stock_out_id' => $stockOut->id,
            'receipt_id' => $stockOut->receipt_id,
            'cancelled_by' => Auth::id(),
            'reason' => $request->cancel_reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Balancing berhasil dibatalkan.',
        ]);
    }
}
