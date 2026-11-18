<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('stock_opnames')) {
            // PostgreSQL native ALTER COLUMN TYPE TEXT (instant, lock-free, supports unlimited length)
            DB::statement('ALTER TABLE stock_opnames ALTER COLUMN brand TYPE TEXT');
            DB::statement('ALTER TABLE stock_opnames ALTER COLUMN product_type TYPE TEXT');
        }

        if (Schema::hasTable('stock_opname_items')) {
            DB::statement('ALTER TABLE stock_opname_items ALTER COLUMN brand TYPE TEXT');
            DB::statement('ALTER TABLE stock_opname_items ALTER COLUMN product_name TYPE TEXT');
        }

        if (Schema::hasTable('stock_opname_excess_items')) {
            DB::statement('ALTER TABLE stock_opname_excess_items ALTER COLUMN brand TYPE TEXT');
            DB::statement('ALTER TABLE stock_opname_excess_items ALTER COLUMN product_name TYPE TEXT');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stock_opnames')) {
            DB::statement('ALTER TABLE stock_opnames ALTER COLUMN brand TYPE VARCHAR(100)');
            DB::statement('ALTER TABLE stock_opnames ALTER COLUMN product_type TYPE VARCHAR(150)');
        }

        if (Schema::hasTable('stock_opname_items')) {
            DB::statement('ALTER TABLE stock_opname_items ALTER COLUMN brand TYPE VARCHAR(100)');
            DB::statement('ALTER TABLE stock_opname_items ALTER COLUMN product_name TYPE VARCHAR(200)');
        }

        if (Schema::hasTable('stock_opname_excess_items')) {
            DB::statement('ALTER TABLE stock_opname_excess_items ALTER COLUMN brand TYPE VARCHAR(100)');
            DB::statement('ALTER TABLE stock_opname_excess_items ALTER COLUMN product_name TYPE VARCHAR(200)');
        }
    }
};
