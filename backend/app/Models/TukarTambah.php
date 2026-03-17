<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TukarTambah extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'receipt_id',
        'customer_name',
        'customer_phone',
        'incoming_source',
        'incoming_product_type_id',
        'incoming_imei',
        'incoming_storage',
        'incoming_condition',
        'incoming_cost_price',
        'outgoing_product_detail_id',
        'outgoing_price',
        'price_difference',
        'payment_method_id',
        'reason',
        'notes',
        'photo_unit',
        'photo_customer',
        'user_id',
        'branch_id',
    ];

    public function incomingProductType()
    {
        return $this->belongsTo(ProductType::class, 'incoming_product_type_id');
    }

    public function outgoingProductDetail()
    {
        return $this->belongsTo(ProductDetail::class, 'outgoing_product_detail_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public static function generateReceiptId()
    {
        $prefix = 'TT' . date('dMy'); // TT18Mar26
        $latest = self::where('receipt_id', 'like', $prefix . '%')->latest()->first();

        if (!$latest) {
            $number = 1;
        } else {
            $lastId = $latest->receipt_id;
            // Handle format prefix-number
            $parts = explode('-', $lastId);
            $number = (int) end($parts) + 1;
        }

        return $prefix . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
}
