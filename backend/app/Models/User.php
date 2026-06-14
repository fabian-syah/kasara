<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Class User
 * @package App\Models
 * 
 * @method bool hasRole(string|array $roles, string|null $guard = null)
 * @method bool hasAnyRole(string|array $roles)
 * @method bool hasAllRoles(string|array $roles)
 * @method bool assignRole(...$roles)
 * @method bool removeRole($role)
 * @method bool syncRoles(...$roles)
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code_id',
        'full_name',
        'username',
        'email',
        'password',
        'address',
        'birth_date',
        'photo',
        'branch_id',
        'warehouse_id',
        'online_shop_id',
        'distributor_id',
        'is_active',
        'theme_color',
        'last_seen',
        'created_by',
        'photo_inventory',
        'pending_photo',
        'pending_photo_inventory',
        'phone',
        'transaction_pin',
        'pin_enabled',
        'pin_reset_requested_at',
        'font_size',
        'cover_photo',
    ];

    protected $appends = [
        'transaction_pin_exists',
    ];

    public function getTransactionPinExistsAttribute()
    {
        return !empty($this->transaction_pin);
    }

    // Relasi ke Cabang
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function onlineShop()
    {
        return $this->belongsTo(OnlineShop::class);
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }

    // Multi-placement relationship
    public function placements()
    {
        return $this->hasMany(UserPlacement::class);
    }

    /**
     * Check if the user has any specific assignment in any location category.
     * This is used to determine if a restricted role (Audit/Analist) should
     * get global access to a category or be limited to their assignments.
     */
    public function hasAnySpecificAssignment()
    {
        // Cache this for the request? Or simple check.
        if ($this->branch_id || $this->online_shop_id || $this->warehouse_id || $this->distributor_id) {
            return true;
        }

        return $this->placements()->exists();
    }

    // Helper to get all accessible branch IDs (including primary and extra)
    public function getAccessibleBranchIds()
    {
        $ids = [];
        if ($this->branch_id)
            $ids[] = $this->branch_id;

        $extras = $this->placements()->whereIn('model_type', ['branch', 'App\Models\Branch'])->pluck('model_id')->toArray();
        $assignedIds = array_unique(array_merge($ids, $extras));

        if ($this->hasAnyRole(['super_admin', 'analist', 'analis'])) {
            /** @var array $excluded */
            $excluded = config('kasara.excluded_keywords', []);
            return \App\Models\Branch::where(function ($q) use ($excluded) {
                foreach ($excluded as $term) {
                    $q->where('name', 'not ilike', '%' . $term . '%');
                }
            })->pluck('id')->toArray();
        }

        if ($this->hasRole(['audit'])) {
            return $assignedIds;
        }

        return $assignedIds;
    }

    // Helper to get all accessible online shop IDs
    public function getAccessibleOnlineShopIds()
    {
        $ids = [];
        if ($this->online_shop_id)
            $ids[] = $this->online_shop_id;

        $extras = $this->placements()->whereIn('model_type', ['online_shop', 'App\Models\OnlineShop'])->pluck('model_id')->toArray();
        $assignedIds = array_unique(array_merge($ids, $extras));

        if ($this->hasAnyRole(['super_admin', 'analist', 'analis'])) {
            /** @var array $excluded */
            $excluded = config('kasara.excluded_keywords', []);
            return \App\Models\OnlineShop::where(function ($q) use ($excluded) {
                foreach ($excluded as $term) {
                    $q->where('name', 'not ilike', '%' . $term . '%');
                }
            })->pluck('id')->toArray();
        }

        if ($this->hasRole(['audit'])) {
            return $assignedIds;
        }

        return $assignedIds;
    }

    // Helper to get all accessible warehouse IDs
    public function getAccessibleWarehouseIds()
    {
        $ids = [];
        if ($this->warehouse_id)
            $ids[] = $this->warehouse_id;

        $extras = $this->placements()->whereIn('model_type', ['warehouse', 'App\Models\Warehouse'])->pluck('model_id')->toArray();
        $assignedIds = array_unique(array_merge($ids, $extras));

        if ($this->hasAnyRole(['super_admin', 'analist', 'analis'])) {
            /** @var array $excluded */
            $excluded = config('kasara.excluded_keywords', []);
            return \App\Models\Warehouse::where(function ($q) use ($excluded) {
                foreach ($excluded as $term) {
                    $q->where('name', 'not ilike', '%' . $term . '%');
                }
            })->pluck('id')->toArray();
        }

        if ($this->hasRole(['audit'])) {
            return $assignedIds;
        }

        return $assignedIds;
    }

    // Helper to get all accessible distributor IDs
    public function getAccessibleDistributorIds()
    {
        $ids = [];
        if ($this->distributor_id)
            $ids[] = $this->distributor_id;

        $extras = $this->placements()->whereIn('model_type', ['distributor', 'App\Models\Distributor'])->pluck('model_id')->toArray();
        $assignedIds = array_unique(array_merge($ids, $extras));

        if ($this->hasAnyRole(['super_admin', 'analist', 'analis'])) {
            /** @var array $excluded */
            $excluded = config('kasara.excluded_keywords', []);
            return \App\Models\Distributor::where(function ($q) use ($excluded) {
                foreach ($excluded as $term) {
                    $q->where('name', 'not ilike', '%' . $term . '%');
                }
            })->pluck('id')->toArray();
        }

        if ($this->hasRole(['audit'])) {
            return $assignedIds;
        }

        return $assignedIds;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'transaction_pin',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen' => 'datetime', // TAMBAHKAN INI
            'is_active' => 'boolean', // Tambahkan ini juga biar CRUD lebih stabil
            'pin_enabled' => 'boolean',
            'transaction_pin' => 'hashed',
            'pin_reset_requested_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }
}
