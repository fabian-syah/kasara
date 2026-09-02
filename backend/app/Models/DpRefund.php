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
        $latest = self::withTrashed()->where('receipt_id', 'like', $prefix . '%')->orderBy('id', 'desc')->first();

        if (!$latest) {
            $number = 1;
        } else {
            $lastId = $latest->receipt_id;
            $number = (int) substr($lastId, -3) + 1;
        }

        return $prefix . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
}
