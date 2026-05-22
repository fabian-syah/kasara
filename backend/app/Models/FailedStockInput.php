<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedStockInput extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'product_name',
        'product_id',
        'imei',
        'placement_type',
        'placement_id',
        'placement_name',
        'distributor_name',
        'condition',
        'cost_price',
        'selling_price',
        'quantity',
        'error_message',
        'error_type',
        'request_data',
    ];

    protected $casts = [
        'request_data' => 'array',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
