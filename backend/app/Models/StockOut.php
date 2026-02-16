<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StockOut extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_id',
        'category',
        'sub_category',
        'selling_price',
        // Pindah Cabang
        'destination_branch_id',
        'destination_type',
        'destination_id',
        'status',
        'receiver_name',
        'transfer_notes',
        // Kesalahan Input
        'deletion_reason',
        // Retur
        'retur_officer',
        'retur_seal',
        'retur_issue',
        'customer_name',
        'customer_phone',
        'return_destination_id',
        'proof_image',
        // Shopee (legacy single-item fields - kept for backward compatibility)
        'shopee_receiver',
        'shopee_phone',
        'shopee_address',
        'shopee_notes',
        'shopee_tracking_no',
        'shopee_province',
        'shopee_city',
        'shopee_district',
        'shopee_village',
        'shopee_postal_code',
        // Giveaway
        'giveaway_receiver',
        'giveaway_phone',
        'giveaway_address',
        'giveaway_province',
        'giveaway_city',
        'giveaway_district',
        'giveaway_village',
        'giveaway_postal_code',
        'giveaway_notes',
        // Shopee per-item data
        'shopee_items_data',
        // Meta
        'user_id',
        'inventory_user_id',
        'notes',
        'non_hp_items',
        // Confirmation
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'shopee_items_data' => 'array',
        'non_hp_items' => 'array',
    ];

    // Relationships
    public function items()
    {
        return $this->belongsToMany(ProductDetail::class, 'stock_out_items')
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inventoryUser()
    {
        return $this->belongsTo(User::class, 'inventory_user_id');
    }

    public function destinationBranch()
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // Note: sourceBranch would be the user's branch at the time of creation
    // We'll get it through the user relationship

    // Generate short receipt ID: O03FEB-K9Z
    public static function generateReceiptId(): string
    {
        do {
            $id = 'O' . strtoupper(date('dM')) . '-' . strtoupper(Str::random(3));
        } while (self::where('receipt_id', $id)->exists());

        return $id;
    }

    // Scopes
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSearch($query, $search)
    {
        $search = strtolower($search);
        return $query->where(function ($q) use ($search) {
            $q->whereRaw('LOWER(receipt_id) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(receiver_name) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(customer_name) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(shopee_receiver) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(shopee_tracking_no) LIKE ?', ["%{$search}%"]);
        });
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'received');
    }

    // Relationships
    public function destination()
    {
        return $this->morphTo();
    }

    public function nonHpItems()
    {
        return $this->hasMany(StockOutNonHpItem::class);
    }
}
