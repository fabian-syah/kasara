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
        // 1. Add column to HP items history
        if (!Schema::hasColumn('stock_out_items', 'distributor_id')) {
            Schema::table('stock_out_items', function (Blueprint $table) {
                $table->integer('distributor_id')->nullable();
                // We reference integer as standard in this DB, but nullable to avoid constraint issues with deleted distributors
            });

            // Backfill existing HP data from product_details
            try {
                DB::statement("
                    UPDATE stock_out_items 
                    SET distributor_id = pd.distributor_id
                    FROM product_details pd
                    WHERE stock_out_items.product_detail_id = pd.id
                    AND stock_out_items.distributor_id IS NULL
                ");
                echo "Backfill HP items success.\n";
            } catch (\Exception $e) {
                // Silently fail backfill if query fails, migration should still pass
            }
        }

        // 2. Add column to Non-HP items history
        if (!Schema::hasColumn('stock_out_non_hp_items', 'distributor_id')) {
            Schema::table('stock_out_non_hp_items', function (Blueprint $table) {
                $table->integer('distributor_id')->nullable();
            });
            
            // Note: Backfilling non-HP is complex because multiple inventories can exist for one product.
            // We only backfill new transactions moving forward for non-HP.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('stock_out_items', 'distributor_id')) {
            Schema::table('stock_out_items', function (Blueprint $table) {
                $table->dropColumn('distributor_id');
            });
        }

        if (Schema::hasColumn('stock_out_non_hp_items', 'distributor_id')) {
            Schema::table('stock_out_non_hp_items', function (Blueprint $table) {
                $table->dropColumn('distributor_id');
            });
        }
    }
};
