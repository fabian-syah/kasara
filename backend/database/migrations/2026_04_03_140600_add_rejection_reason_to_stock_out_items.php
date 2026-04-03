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
        // 1. Fix the product_details status constraint for PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_details DROP CONSTRAINT IF EXISTS product_details_status_check');
        }

        // 2. Add status and notes/rejection reason to stock_out_items
        Schema::table('stock_out_items', function (Blueprint $table) {
            $table->string('status', 20)->default('confirmed'); // confirmed, rejected
            $table->text('notes')->nullable(); // reason for rejection
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_out_items', function (Blueprint $table) {
            $table->dropColumn(['status', 'notes']);
        });

        // We don't restore the constraint as it was a bug in the first place
    }
};
