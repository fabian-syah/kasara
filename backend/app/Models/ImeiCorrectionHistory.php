<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImeiCorrectionHistory extends Model
{
    use HasFactory;

    protected $table = 'imei_correction_histories';

    protected $fillable = [
        'product_detail_id',
        'imei',
        'old_product_id',
        'old_product_name',
        'old_brand',
        'old_brand_id',
        'old_storage',
        'old_color',
        'old_ram',
        'new_product_id',
        'new_product_name',
        'new_brand',
        'new_brand_id',
        'new_storage',
        'new_color',
        'new_ram',
        'cs_name_mistake',
        'notes',
        'created_by',
    ];

    public function productDetail()
    {
        return $this->belongsTo(ProductDetail::class);
    }

    public function oldProduct()
    {
        return $this->belongsTo(Product::class, 'old_product_id');
    }

    public function newProduct()
    {
        return $this->belongsTo(Product::class, 'new_product_id');
    }

    public function oldBrand()
    {
        return $this->belongsTo(Brand::class, 'old_brand_id');
    }

    public function newBrand()
    {
        return $this->belongsTo(Brand::class, 'new_brand_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
