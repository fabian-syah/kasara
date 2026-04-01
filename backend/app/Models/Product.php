<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'type', // hp, non-hp
        'has_imei', // boolean
        'price',
        'min_stock',
        'description',
        'category',
        'brand',
        'brand_id',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'has_imei' => 'boolean',
        'price' => 'decimal:2',
    ];

    // Relations
    public function details()
    {
        return $this->hasMany(ProductDetail::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function brandRelation()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
}
