<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StockOut extends Model
{
    use SoftDeletes;
    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->reporting_date) {
                $location = null;
                // Try to get location from inventory_user_id or user_id
                $user = User::find($model->inventory_user_id ?? $model->user_id);
                if ($user) {
                    if ($user->branch_id) {
                        $location = $user->branch;
                    } elseif ($user->online_shop_id) {
                        $location = $user->onlineShop;
                    }
                }
                
                $model->reporting_date = static::calculateReportingDate($model->category, $location);
            }
        });
    }

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
        'reporting_date',
        'shopee_order_id',
        'customer_wa',
        'transaction_pin',
        // Confirmation
        'confirmed_at',
        'confirmed_by',
        'sales_account',
        'payment_method_id',
        'paid_amount',
        'split_payments',
        'is_bundle',
        'bundle_description',
        'total_discount',
        'global_discount_value',
        'global_discount_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'shopee_items_data' => 'array',
        'non_hp_items' => 'array',
        'split_payments' => 'array',
    ];

    protected $appends = ['payment_method_name', 'split_payments_data'];

    public function getPaymentMethodNameAttribute()
    {
        return $this->paymentMethod->name ?? null;
    }

    public function getSplitPaymentsDataAttribute()
    {
        $splits = $this->split_payments;
        if (!$splits)
            return [];

        if (is_string($splits)) {
            $splits = json_decode($splits, true);
        }

        if (!is_array($splits))
            return [];
        return [];

        // Check if names are already there
        if (count($splits) > 0 && isset($splits[0]['method_name']))
            return $splits;

        $methodIds = array_column($splits, 'payment_method_id');
        $methodNames = \App\Models\PaymentMethod::whereIn('id', $methodIds)->pluck('name', 'id');

        $result = [];
        foreach ($splits as $sp) {
            $result[] = [
                'method_name' => $methodNames[$sp['payment_method_id']] ?? 'Unknown',
                'amount' => $sp['amount'] ?? 0
            ];
        }
        return $result;
    }

    // Relationships
    public function items()
    {
        return $this->belongsToMany(ProductDetail::class, 'stock_out_items')
            ->withPivot(['selling_price', 'item_discount', 'distributed_discount'])
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Logic for custom reporting/business date reset.
     * All sales: 17:00 (5 PM) reset.
     * Everything else: 05:00 (5 AM) reset.
     */
    public static function calculateReportingDate($category, $branchOrTimezone, $timestamp = null)
    {
        $dt = $timestamp ? \Carbon\Carbon::parse($timestamp) : now();

        // 1. Resolve Timezone
        $tz = 'Asia/Jakarta'; // Default WIB
        if ($branchOrTimezone instanceof Branch || $branchOrTimezone instanceof OnlineShop) {
            $branchTz = strtoupper($branchOrTimezone->timezone ?? '');
            $tz = match ($branchTz) {
                'WITA', 'ASIA/MAKASSAR' => 'Asia/Makassar',
                'WIT', 'ASIA/JAYAPURA' => 'Asia/Jayapura',
                default => 'Asia/Jakarta',
            };
        } elseif (is_string($branchOrTimezone)) {
            $tz = $branchOrTimezone;
        }

        // Convert UTC/System time to local branch time
        $dt->setTimezone($tz);

        // 2. Apply Cutoff Logic (5:00 AM)
        $hour = (int) $dt->format('H');

        if ($hour >= 5) {
            // Already past 5 AM, so reporting_date is TODAY
            return $dt->format('Y-m-d');
        } else {
            // It's before 5 AM (e.g., 4:59 AM), so reporting_date is YESTERDAY
            return $dt->subDay()->format('Y-m-d');
        }
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

    public function auditAnswers()
    {
        return $this->hasMany(AuditAnswer::class);
    }

    public function auditProfit()
    {
        return $this->hasOne(AuditProfit::class, 'stock_out_id', 'id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
