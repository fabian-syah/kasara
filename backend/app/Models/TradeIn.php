<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TradeIn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'receipt_id',
        'customer_name',
        'customer_phone',
        'source',
        'distributor_id',
        'product_type_id',
        'imei',
        'ram',
        'storage',
        'condition',
        'buy_price',
        'quantity',
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

    public function inventoryItem()
    {
        return $this->hasOne(ProductDetail::class);
    }

    public static function generateReceiptId()
    {
        $prefix = 'TI' . date('dMy'); // TI13Mar26
        $latest = self::where('receipt_id', 'like', $prefix . '%')->latest()->first();

        if (!$latest) {
            $number = 1;
        } else {
            $lastId = $latest->receipt_id;
            $number = (int) substr($lastId, -3) + 1;
        }

        return $prefix . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
}
