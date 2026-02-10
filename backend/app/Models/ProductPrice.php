<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    use HasFactory;

    protected $fillable = ['product_type_id', 'condition', 'cost_price', 'price'];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    public function productType()
    {
        return $this->belongsTo(ProductType::class);
    }
}
