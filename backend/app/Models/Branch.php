<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'code',
        'name',
        'address',
        'timezone',
        'is_active',
        'type', // physical, online
        'platform',
        'url',
        'api_key',
        'api_secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeOnline($query)
    {
        return $query->where('type', 'online');
    }

    public function scopePhysical($query)
    {
        return $query->where('type', 'physical');
    }
}
