<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (!Schema::hasColumn('inventories', 'selling_price')) {
                $table->decimal('selling_price', 15, 2)->default(0)->after('cost_price');
            }
        });

        // Backfill existing inventory records with the product's current global price
        // so existing data doesn't show 0
        \Illuminate\Support\Facades\DB::statement("
            UPDATE inventories
            SET selling_price = COALESCE(
                (SELECT price FROM products WHERE products.id = inventories.product_id),
                0
            )
            WHERE selling_price = 0
        ");
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('selling_price');
        });
    }
};
