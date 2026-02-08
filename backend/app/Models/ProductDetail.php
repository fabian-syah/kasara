<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'user_id',
        'imei',
        'color',
        'ram',
        'storage',
        'condition',
        'status',
        'placement_type',
        'placement_id',
        'cost_price',
        'selling_price',
        'distributor_id',
        'supplier_name'
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
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

    // Relationship for stock outs (to get return proof_image)
    public function stockOuts()
    {
        return $this->belongsToMany(StockOut::class, 'stock_out_items')
            ->withTimestamps();
    }

    // Get the latest return stock out for this item
    public function latestReturnStockOut()
    {
        return $this->stockOuts()
            ->where('category', 'retur')
            ->latest()
            ->first();
    }
}
