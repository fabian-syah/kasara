<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds columns to support the new Balancing feature (super admin only).
     * - balancing_type: 'payment_method' or 'missed_sale'
     * - balancing_notes: free-text description of the balancing reason
     * - balancing_cs_user_id: the CS (inventory) user who handled the original transaction
     */
    public function up(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->string('balancing_type')->nullable()->after('category');
            $table->text('balancing_notes')->nullable()->after('notes');
            $table->unsignedBigInteger('balancing_cs_user_id')->nullable()->after('inventory_user_id');
            $table->foreign('balancing_cs_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropForeign(['balancing_cs_user_id']);
            $table->dropColumn(['balancing_type', 'balancing_notes', 'balancing_cs_user_id']);
        });
    }
};
