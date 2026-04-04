<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            // Tambahkan kolom distributor_id jika belum ada
            if (!Schema::hasColumn('inventories', 'distributor_id')) {
                $table->foreignId('distributor_id')->nullable()->constrained()->nullOnDelete();
            }
            
            // Tambahkan harga modal untuk pemisahan stok yang lebih akurat
            if (!Schema::hasColumn('inventories', 'cost_price')) {
                $table->decimal('cost_price', 15, 2)->default(0);
            }

            // Hapus index unik yang lama (yang menyebabkan stok menimpa)
            try {
                $table->dropUnique('inventory_unique_location');
            } catch (\Exception $e) {
                // Ignore if doesn't exist
            }

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
