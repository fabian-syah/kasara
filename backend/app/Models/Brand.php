<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'category'];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($brand) {
            $brand->slug = self::generateUniqueSlug($brand->name);
        });
        
        static::updating(function ($brand) {
            if ($brand->isDirty('name')) {
                $brand->slug = self::generateUniqueSlug($brand->name, $brand->id);
            }
        });
    }

    public static function generateUniqueSlug($name, $ignoreId = 0)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (self::where('slug', $slug)->where('id', '!=', $ignoreId)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    public function productTypes()
    {
        return $this->hasMany(ProductType::class);
    }
}
