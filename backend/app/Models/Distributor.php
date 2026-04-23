<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Distributor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'contact_person',
        'phone',
        'email',
        'address',
        'is_active',
        'allowed_brands',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_brands' => 'array',
    ];
}
