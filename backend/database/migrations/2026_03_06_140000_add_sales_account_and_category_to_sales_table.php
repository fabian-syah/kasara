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
        Schema::table('stock_outs', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_outs', 'sales_account')) {
                $table->string('sales_account')->nullable()->after('customer_name');
            }
            if (!Schema::hasColumn('stock_outs', 'category')) {
                $table->string('category')->nullable()->after('sales_account');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn(['sales_account', 'category']);
        });
    }
};
