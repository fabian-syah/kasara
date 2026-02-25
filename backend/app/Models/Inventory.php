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
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Dynamic relationship for placement
    public function placement()
    {
        if ($this->placement_type === 'branch')
            return $this->belongsTo(Branch::class, 'placement_id');
        if ($this->placement_type === 'warehouse')
            return $this->belongsTo(Warehouse::class, 'placement_id');
        if ($this->placement_type === 'online_shop')
            return $this->belongsTo(OnlineShop::class, 'placement_id');
        return null;
    }

    public function latestLog()
    {
        return $this->hasOne(InventoryLog::class, 'product_id', 'product_id')
            ->where('type', 'in')
            ->latestOfMany();
    }
}
