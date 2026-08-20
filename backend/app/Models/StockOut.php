<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Events\StockOutEvent;

class StockOut extends Model
{
    use SoftDeletes;
    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->reporting_date || !$model->branch_id) {
                $location = null;
                
                // The location should match where the inventory is deducted from, which is the main account (Auth::user()).
                $mainUser = User::find($model->user_id);
                $subUser = $model->inventory_user_id ? User::find($model->inventory_user_id) : null;

                // Priority: Main Account's location -> Sub Account's location
                $user = null;
                if ($mainUser && ($mainUser->branch_id || $mainUser->online_shop_id || $mainUser->warehouse_id)) {
                    $user = $mainUser;
                } elseif ($subUser && ($subUser->branch_id || $subUser->online_shop_id || $subUser->warehouse_id)) {
                    $user = $subUser;
                } else {
                    $user = $mainUser;
                }

                if ($user) {
                    if ($user->branch_id) {
                        $location = $user->branch;
                        $model->branch_id = $model->branch_id ?? $user->branch_id;
                    } elseif ($user->online_shop_id) {
                        $location = $user->onlineShop;
                        $model->online_shop_id = $model->online_shop_id ?? $user->online_shop_id;
                    } elseif ($user->warehouse_id) {
                        $location = $user->warehouse;
                        $model->warehouse_id = $model->warehouse_id ?? $user->warehouse_id;
                    }
                }
                
                if (!$model->reporting_date) {
                    $model->reporting_date = static::calculateReportingDate($model->category, $location);
                }
            }
        });

        // AUTOMATIC REALTIME BROADCAST: Trigger websocket event on ANY state change
        static::created(function ($model) {
            try {
                broadcast(new StockOutEvent($model))->toOthers();
            } catch (\Throwable $e) {
                Log::warning("Reverb broadcast on created failed: " . $e->getMessage());
            }
        });

        static::updated(function ($model) {
            try {
                broadcast(new StockOutEvent($model))->toOthers();
            } catch (\Throwable $e) {
                Log::warning("Reverb broadcast on updated failed: " . $e->getMessage());
            }
        });

        static::deleted(function ($model) {
            try {
                broadcast(new StockOutEvent($model))->toOthers();
            } catch (\Throwable $e) {
                Log::warning("Reverb broadcast on deleted failed: " . $e->getMessage());
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
        'payment_proof_image',
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
        // Event
        'event_receiver',
        'event_phone',
        'event_notes',
        // Brand Ambassador
        'ba_name',
        'ba_phone',
        'ba_social_media',
        'ba_notes',
        // Event Sponsorship
        'event_name',
        'event_doc',
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
        'notes',
        'branch_id',
        'online_shop_id',
        'warehouse_id',
        'missing_category',
        'person_in_charge',
        'loss_chronology',
        'expedition_name',
        'expedition_tracking_no',
        'expedition_date',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'dp_amount',
        'is_dp_settled',
        'parent_dp_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'shopee_items_data' => 'array',
        'non_hp_items' => 'array',
        'split_payments' => 'array',
    ];

    protected $appends = ['payment_method_name', 'split_payments_data', 'audit_score'];

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

    public function getAuditScoreAttribute()
    {
        if ($this->relationLoaded('auditAnswers')) {
            $answers = $this->auditAnswers;
            if ($answers->isEmpty()) {
                return null;
            }
            $total = $answers->count();
            $yes = $answers->where('answer', true)->count();
            return $total > 0 ? round(($yes / $total) * 100) : 0;
        }
        return null;
    }

    // Relationships
    public function items()
    {
        return $this->belongsToMany(ProductDetail::class, 'stock_out_items')
            ->withPivot(['selling_price', 'item_discount', 'distributed_discount', 'status', 'notes'])
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Logic for custom reporting/business date reset.
     * All categories reset at 05:00 (5 AM).
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

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function onlineShop()
    {
        return $this->belongsTo(OnlineShop::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function destinationBranch()
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Note: sourceBranch would be the user's branch at the time of creation
    // We'll get it through the user relationship

    // Generate short receipt ID: O03FEB-K9Z
    public static function generateReceiptId(): string
    {
        do {
            $id = 'O' . strtoupper(date('dM')) . '-' . strtoupper(Str::random(3));
        } while (self::withTrashed()->where('receipt_id', $id)->exists());

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
                ->orWhereRaw('LOWER(shopee_tracking_no) LIKE ?', ["%{$search}%"])
                ->orWhereHas('items', function ($iq) use ($search) {
                    $iq->whereRaw('LOWER(imei) LIKE ?', ["%{$search}%"])
                       ->orWhereHas('product', function ($pq) use ($search) {
                           $pq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                              ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$search}%"]);
                       });
                })
                ->orWhereHas('nonHpDetails.product', function ($pq) use ($search) {
                    $pq->where(function($qq) use ($search) {
                        $qq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                           ->orWhereRaw('LOWER(brand) LIKE ?', ["%{$search}%"]);
                        
                        if (Schema::hasColumn('products', 'non_imei_category')) {
                            $qq->orWhereRaw('LOWER(non_imei_category) LIKE ?', ["%{$search}%"]);
                        }
                    });
                });
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

    public function nonHpDetails()
    {
        return $this->hasMany(StockOutNonHpItem::class);
    }

    public function nonHpItems()
    {
        return $this->nonHpDetails();
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
