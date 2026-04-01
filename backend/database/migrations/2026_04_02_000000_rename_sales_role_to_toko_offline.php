<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename 'sales' role to 'toko_offline' in roles table
        DB::table('roles')->where('name', 'sales')->update(['name' => 'toko_offline']);
        
        // 2. Also rename permissions if they are prefixed with 'sales.' (optional, but good practice)
        DB::table('permissions')->where('name', 'like', 'sales.%')
          ->get()
          ->each(function($permission) {
              $newName = str_replace('sales.', 'toko_offline.', $permission->name);
              DB::table('permissions')->where('id', $permission->id)->update(['name' => $newName]);
          });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('name', 'toko_offline')->update(['name' => 'sales']);
        
        DB::table('permissions')->where('name', 'like', 'toko_offline.%')
          ->get()
          ->each(function($permission) {
              $newName = str_replace('toko_offline.', 'sales.', $permission->name);
              DB::table('permissions')->where('id', $permission->id)->update(['name' => $newName]);
          });
    }
};
