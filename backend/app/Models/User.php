<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

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
        'phone',
    ];

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

    // Helper to get all accessible branch IDs (including primary and extra)
    public function getAccessibleBranchIds()
    {
        $ids = [];
        if ($this->branch_id)
            $ids[] = $this->branch_id;

        $extras = $this->placements()->where('model_type', 'branch')->pluck('model_id')->toArray();
        return array_unique(array_merge($ids, $extras));
    }

    // Helper to get all accessible online shop IDs
    public function getAccessibleOnlineShopIds()
    {
        $ids = [];
        if ($this->online_shop_id)
            $ids[] = $this->online_shop_id;

        $extras = $this->placements()->where('model_type', 'online_shop')->pluck('model_id')->toArray();
        return array_unique(array_merge($ids, $extras));
    }

    // Helper to get all accessible warehouse IDs
    public function getAccessibleWarehouseIds()
    {
        $ids = [];
        if ($this->warehouse_id)
            $ids[] = $this->warehouse_id;

        $extras = $this->placements()->where('model_type', 'warehouse')->pluck('model_id')->toArray();
        return array_unique(array_merge($ids, $extras));
    }

    // Helper to get all accessible distributor IDs
    public function getAccessibleDistributorIds()
    {
        $ids = [];
        if ($this->distributor_id)
            $ids[] = $this->distributor_id;

        $extras = $this->placements()->where('model_type', 'distributor')->pluck('model_id')->toArray();
        return array_unique(array_merge($ids, $extras));
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }
}
