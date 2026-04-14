<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductType extends Model
{
    use HasFactory;

    protected $fillable = ['brand_id', 'name', 'slug', 'ram', 'storage', 'category', 'non_imei_category', 'cost_price', 'price'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($type) {
            $type->slug = Str::slug($type->name . '-' . Str::random(5));
        });
        static::updating(function ($type) {
            // Slug update is optional or could require uniqueness checks
            $type->slug = Str::slug($type->name . '-' . Str::random(5));
        });
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
