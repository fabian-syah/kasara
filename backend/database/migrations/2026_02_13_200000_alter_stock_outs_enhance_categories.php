<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Modify 'category' column to be STRING (remove enum constraint)
        // We use raw SQL for safety across potential DB versions where Doctrine/DBAL might be missing for enum changes.
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE stock_outs ALTER COLUMN category TYPE VARCHAR(50)");
        } else {
            DB::statement("ALTER TABLE stock_outs MODIFY COLUMN category VARCHAR(50) NOT NULL");
        }

        // 2. Add 'sub_category' column
        if (!Schema::hasColumn('stock_outs', 'sub_category')) {
            Schema::table('stock_outs', function (Blueprint $table) {
                $table->string('sub_category')->nullable()->after('category');
            });
        }

        // 3. Migrate 'shopee' data to 'orderan_online'
        DB::table('stock_outs')
            ->where('category', 'shopee')
            ->update(['category' => 'orderan_online']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert 'orderan_online' to 'shopee'
        DB::table('stock_outs')
            ->where('category', 'orderan_online')
            ->update(['category' => 'shopee']);

        // Drop sub-category
        if (Schema::hasColumn('stock_outs', 'sub_category')) {
            Schema::table('stock_outs', function (Blueprint $table) {
                $table->dropColumn('sub_category');
            });
        }

        // Revert category to ENUM (Warning: Data loss if other categories exist)
        // DB::statement("ALTER TABLE stock_outs MODIFY COLUMN category ENUM('pindah_cabang', 'kesalahan_input', 'retur', 'shopee') NOT NULL");
    }
};
