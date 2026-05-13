<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptSetting extends Model
{
    protected $fillable = [
        'branch_id',
        'online_shop_id',
        'store_address',
        'whatsapp_number',
        'instagram',
        'tiktok',
        'warranty_terms'
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function onlineShop()
    {
        return $this->belongsTo(OnlineShop::class);
    }
}
