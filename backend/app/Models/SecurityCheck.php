<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_id',
        'security_name',
        'notes',
    ];

    public function answers()
    {
        return $this->hasMany(SecurityCheckAnswer::class);
    }

    public function excessItems()
    {
        return $this->hasMany(SecurityCheckExcessItem::class);
    }

    public function stockOut()
    {
        return $this->belongsTo(StockOut::class, 'receipt_id', 'receipt_id');
    }
}
