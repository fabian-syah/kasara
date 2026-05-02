<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductDetail extends Model
{
    use HasFactory, SoftDeletes;
    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->created_at && now()->hour < 5) {
                $model->created_at = now()->subDay();
            }
        });
    }

    protected $fillable = [
        'product_id',
        'user_id',
        'imei',
        'color',
        'ram',
        'storage',
        'condition',
        'status',
        'placement_type',
        'placement_id',
        'cost_price',
        'selling_price',
        'distributor_id',
        'supplier_name',
        'notes',
        'trade_in_id',
        'refund_id',
        'unit_exchange_id',
        'tukar_tambah_id',
        'downgrade_id'
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    // Standard polymorphic relationship for placement
    public function placement()
    {
        return $this->morphTo(__FUNCTION__, 'placement_type', 'placement_id');
    }

    // Relationship for stock outs (to get return proof_image)
    public function stockOuts()
    {
        return $this->belongsToMany(StockOut::class, 'stock_out_items')
            ->withTimestamps();
    }

    // Get the latest return stock out for this item
    public function latestReturnStockOut()
    {
        return $this->stockOuts()
            ->where('category', 'retur')
            ->latest()
            ->first();
    }

    public function tradeIn()
    {
        return $this->belongsTo(TradeIn::class);
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }

    public function unitExchange()
    {
        return $this->belongsTo(UnitExchange::class);
    }

    public function tukarTambah()
    {
        return $this->belongsTo(TukarTambah::class);
    }

    public function downgrade()
    {
        return $this->belongsTo(Downgrade::class);
    }
}
