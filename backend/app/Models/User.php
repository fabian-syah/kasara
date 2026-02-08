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
