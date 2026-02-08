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
        'selling_price',
        // Pindah Cabang
        'destination_branch_id',
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
        'notes',
        // Confirmation
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'shopee_items_data' => 'array',
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
        return $query->where(function ($q) use ($search) {
            $q->where('receipt_id', 'like', "%{$search}%")
                ->orWhere('receiver_name', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('shopee_receiver', 'like', "%{$search}%")
                ->orWhere('shopee_tracking_no', 'like', "%{$search}%");
        });
    }

    public function scopePending($query)
    {
        return $query->whereNull('confirmed_at');
    }

    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('confirmed_at');
    }
}
