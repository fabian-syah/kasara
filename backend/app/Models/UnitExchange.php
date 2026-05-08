<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitExchange extends Model
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
        'distributor_id',
        'incoming_cost_price',
        'outgoing_product_detail_id',
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

    public function incomingProductType()
    {
        return $this->belongsTo(ProductType::class, 'incoming_product_type_id');
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    public function outgoingProductDetail()
    {
        return $this->belongsTo(ProductDetail::class, 'outgoing_product_detail_id');
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
        $prefix = 'UE' . date('dMy'); // UE15Mar26
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
