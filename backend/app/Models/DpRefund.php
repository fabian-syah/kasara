<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DpRefund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'receipt_id',
        'stock_out_id',
        'customer_name',
        'customer_phone',
        'refund_amount',
        'payment_method_id',
        'split_payments',
        'reason',
        'notes',
        'user_id',
        'inventory_user_id',
        'branch_id',
        'photo',
    ];

    protected $casts = [
        'split_payments' => 'array',
    ];

    public function stockOut()
    {
        return $this->belongsTo(StockOut::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inventoryUser()
    {
        return $this->belongsTo(User::class, 'inventory_user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Generate unique receipt ID for DP Refund.
     * Format: RDP{dd}{Mon}{yy}-{###}
     */
    public static function generateReceiptId()
    {
        $prefix = 'RDP' . date('dMy'); // RDP01Sep26

        // Query all existing receipt IDs with today's prefix from DpRefund (including trashed)
        $existingModelIds = self::withTrashed()
            ->where('receipt_id', 'like', $prefix . '-%')
            ->pluck('receipt_id');

        // Query from StockOut as well
        $existingStockOutIds = \App\Models\StockOut::withTrashed()
            ->where('receipt_id', 'like', $prefix . '-%')
            ->pluck('receipt_id');

        $allExisting = $existingModelIds->concat($existingStockOutIds);

        $maxNumber = 0;
        foreach ($allExisting as $rid) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)/', (string) $rid, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $nextNumber = $maxNumber + 1;

        // Loop until candidate is guaranteed unique in both DpRefund and StockOut
        do {
            $candidate = $prefix . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            $existsInModel = self::withTrashed()
                ->where(function ($q) use ($candidate) {
                    $q->where('receipt_id', $candidate)
                      ->orWhere('receipt_id', 'like', $candidate . '-%');
                })
                ->exists();

            $existsInStockOut = \App\Models\StockOut::withTrashed()
                ->where(function ($q) use ($candidate) {
                    $q->where('receipt_id', $candidate)
                      ->orWhere('receipt_id', 'like', $candidate . '-%');
                })
                ->exists();

            if ($existsInModel || $existsInStockOut) {
                $nextNumber++;
            } else {
                break;
            }
        } while (true);

        return $candidate;
    }
}
