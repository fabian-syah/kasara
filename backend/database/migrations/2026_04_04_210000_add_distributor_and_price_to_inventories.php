<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (!Schema::hasColumn('inventories', 'distributor_id')) {
                $table->foreignId('distributor_id')->nullable()->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('inventories', 'cost_price')) {
                $table->decimal('cost_price', 15, 2)->default(0);
            }
        });

        // Hapus index unik lama menggunakan SQL mentah agar tidak error jika tidak ditemukan
        // Kami hapus kemungkinana kunci otomatis maupun manual
        $constraints = ['inventory_unique_location', 'inventories_product_id_placement_type_placement_id_unique'];
        foreach ($constraints as $con) {
            DB::statement("ALTER TABLE inventories DROP CONSTRAINT IF EXISTS \"$con\"");
        }

        Schema::table('inventories', function (Blueprint $table) {
            // Buat index unik baru yang menyertakan distributor & harga
            // Ini akan membuat Arcis (30) dan Debs (20) menjadi dua baris berbeda
            $table->unique(['product_id', 'placement_type', 'placement_id', 'distributor_id', 'cost_price', 'user_id'], 'inv_distributor_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique('inv_distributor_unique');
            $table->unique(['product_id', 'placement_type', 'placement_id'], 'inventory_unique_location');
            $table->dropColumn(['distributor_id', 'cost_price']);
        });
    }
};
