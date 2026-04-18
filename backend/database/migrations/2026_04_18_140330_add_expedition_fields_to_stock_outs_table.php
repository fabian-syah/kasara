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
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->string('expedition_name')->nullable()->after('shopee_tracking_no');
            $table->string('expedition_tracking_no')->nullable()->after('expedition_name');
            $table->date('expedition_date')->nullable()->after('expedition_tracking_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn(['expedition_name', 'expedition_tracking_no', 'expedition_date']);
        });
    }
};
