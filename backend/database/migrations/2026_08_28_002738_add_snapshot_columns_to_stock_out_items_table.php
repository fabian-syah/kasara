<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_out_items', function (Blueprint $table) {
            $table->unsignedBigInteger('snapshot_product_id')->nullable()->after('product_detail_id');
            $table->string('snapshot_product_name')->nullable()->after('snapshot_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_out_items', function (Blueprint $table) {
            $table->dropColumn(['snapshot_product_id', 'snapshot_product_name']);
        });
    }
};
