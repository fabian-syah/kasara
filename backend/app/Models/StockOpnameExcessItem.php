<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameExcessItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_opname_id',
        'brand',
        'product_name',
        'storage',
        'imei',
        'quantity',
        'notes',
        'photos',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'photos' => 'array',
    ];

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }
}
