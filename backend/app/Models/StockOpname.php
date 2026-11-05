<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use HasFactory;

    protected $fillable = [
        'opname_number',
        'date',
        'branch_id',
        'user_id',
        'cs_user_id',
        'executor_role',
        'category_type',
        'method',
        'brand',
        'product_type',
        'total_system',
        'total_physical',
        'total_missing',
        'total_excess',
        'photos',
        'notes',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'photos' => 'array',
        'total_system' => 'integer',
        'total_physical' => 'integer',
        'total_missing' => 'integer',
        'total_excess' => 'integer',
    ];

    protected $appends = ['executor_role_label'];

    public function getExecutorRoleLabelAttribute()
    {
        return match($this->executor_role) {
            'audit' => 'SO Audit',
            'leader' => 'SO Leader',
            'admin' => 'SO Admin',
            default => 'SO CS Toko',
        };
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function csUser()
    {
        return $this->belongsTo(User::class, 'cs_user_id');
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }

    public function excessItems()
    {
        return $this->hasMany(StockOpnameExcessItem::class, 'stock_opname_id');
    }
}
