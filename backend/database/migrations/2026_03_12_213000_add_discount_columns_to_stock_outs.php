<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add columns to stock_outs
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->decimal('total_discount', 15, 2)->default(0)->after('selling_price');
            $table->decimal('global_discount_value', 15, 2)->default(0)->after('total_discount');
            $table->string('global_discount_type')->default('fixed')->after('global_discount_value'); // 'fixed' or 'percentage'
        });

        // Add columns to stock_out_items (pivot)
        Schema::table('stock_out_items', function (Blueprint $table) {
            $table->decimal('selling_price', 15, 2)->default(0); // Price before global discount
            $table->decimal('item_discount', 15, 2)->default(0); // Per-item discount
            $table->decimal('distributed_discount', 15, 2)->default(0); // Allocated global discount
        });

        // Add columns to stock_out_non_hp_items
        Schema::table('stock_out_non_hp_items', function (Blueprint $table) {
            $table->decimal('selling_price', 15, 2)->default(0)->change(); // Ensure decimal and consistent
            $table->decimal('item_discount', 15, 2)->default(0)->after('quantity');
            $table->decimal('distributed_discount', 15, 2)->default(0)->after('item_discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn(['total_discount', 'global_discount_value', 'global_discount_type']);
        });

        Schema::table('stock_out_items', function (Blueprint $table) {
            $table->dropColumn(['selling_price', 'item_discount', 'distributed_discount']);
        });

        Schema::table('stock_out_non_hp_items', function (Blueprint $table) {
            $table->dropColumn(['item_discount', 'distributed_discount']);
        });
    }
};
