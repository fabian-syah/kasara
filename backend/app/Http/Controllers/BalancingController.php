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

        if (!$branchId) {
            return response()->json(['data' => []]);
        }

        $users = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->select('id', 'name')
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
}
