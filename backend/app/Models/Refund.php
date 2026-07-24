<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'receipt_id',
        'customer_name',
        'customer_phone',
        'distributor_id',
        'product_type_id',
        'imei',
        'storage',
        'condition',
        'refund_price',
        'payment_method_id',
        'split_payments',
        'reason',
        'notes',
        'photo_unit',
        'photo_customer',
        'user_id',
        'inventory_user_id',
        'branch_id',
    ];

    protected $casts = [
        'split_payments' => 'array',
    ];

    public function productType()
    {
        return $this->belongsTo(ProductType::class);
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public static function generateReceiptId()
    {
        $prefix = 'RF' . date('dMy'); // RF15Mar26
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
