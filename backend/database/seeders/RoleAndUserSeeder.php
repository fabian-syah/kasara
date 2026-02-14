<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class RoleAndUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Reset cache permission (penting agar role baru terbaca)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Daftar Role sesuai permissions.js (snake_case)
        $roles = [
            'super_admin',
            'analist',
            'admin_produk',
            'audit',
            'security',
            'leader',
            'distribution',
            'sales',
            'inventory',
            'gudang',
            'inventory_kasir',
            'toko_online',
            'leader_shopee'
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // 3. Buat Akun Super Admin (Fabian)
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Fabian Syah',
                'email' => 'admin@apexpos.com',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'branch_id' => null, // Super Admin Global
            ]
        );

        // 4. Tempelkan Role Super Admin ke Fabian
        $admin->assignRole('super_admin');

        $this->command->info('Role & User Fabian berhasil dibuat!');
    }
}