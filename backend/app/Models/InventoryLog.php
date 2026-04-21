<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    use HasFactory;
    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->created_at && now()->hour < 5) {
                $model->created_at = now()->subDay();
            }
        });
    }

    protected $fillable = [
        'product_id',
        'branch_id',
        'warehouse_id',
        'online_shop_id',
        'distributor_id',
        'user_id',
        'type',
        'quantity',
        'balance_after',
        'reference_id',
        'description',
        'supplier_name',
        'notes'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }
}
