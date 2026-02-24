<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditProfit extends Model
{
    protected $fillable = [
        'stock_out_id',
        'harga_modal',
        'items_modal',
        'auditor_id',
    ];

    protected $casts = [
        'harga_modal' => 'decimal:2',
        'items_modal' => 'array',
    ];

    public function stockOut()
    {
        return $this->belongsTo(StockOut::class);
    }

    public function auditor()
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}
