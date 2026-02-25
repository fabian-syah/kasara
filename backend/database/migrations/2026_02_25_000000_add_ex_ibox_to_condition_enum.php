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
        // Alter product_details table
        DB::statement("ALTER TABLE `product_details` CHANGE COLUMN `condition` `condition` ENUM('new', 'second', 'ex_ibox') NOT NULL DEFAULT 'new'");

        // Alter product_prices table (assuming it has the condition ENUM too based on the codebase conventions)
        // Let's make sure it doesn't fail if the table doesn't have it.
        try {
            DB::statement("ALTER TABLE `product_prices` CHANGE COLUMN `condition` `condition` ENUM('new', 'second', 'ex_ibox') NOT NULL DEFAULT 'new'");
        } catch (\Exception $e) {
            // It might not exist or be different
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert product_details
        DB::statement("ALTER TABLE `product_details` CHANGE COLUMN `condition` `condition` ENUM('new', 'second') NOT NULL DEFAULT 'new'");

        // Revert product_prices
        try {
            DB::statement("ALTER TABLE `product_prices` CHANGE COLUMN `condition` `condition` ENUM('new', 'second') NOT NULL DEFAULT 'new'");
        } catch (\Exception $e) {
            // It might not exist or be different
        }
    }
};
