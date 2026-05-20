<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'placement_type',
        'placement_id',
        'quantity',
        'cost_price',
        'selling_price',
        'distributor_id',
        'user_id',
        'notes'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Standard polymorphic relationship for placement
    public function placement()
    {
        return $this->morphTo(__FUNCTION__, 'placement_type', 'placement_id');
    }

    public function latestLog()
    {
        return $this->hasOne(InventoryLog::class, 'product_id', 'product_id')
            ->where('type', 'in')
            ->latestOfMany();
    }
}
