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
        'inventory_user_id',
        'notes',
        'checked_items',
    ];

    protected $casts = [
        'checked_items' => 'array',
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

    public function inventoryUser()
    {
        return $this->belongsTo(User::class, 'inventory_user_id');
    }
}
