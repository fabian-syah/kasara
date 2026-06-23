<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityCheckExcessItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'security_check_id',
        'brand',
        'type',
        'storage',
        'excess_qty',
        'notes',
    ];

    public function securityCheck()
    {
        return $this->belongsTo(SecurityCheck::class);
    }
}
