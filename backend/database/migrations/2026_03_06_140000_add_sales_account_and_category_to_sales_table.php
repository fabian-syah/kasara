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
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'sales_account')) {
                $table->string('sales_account')->nullable()->after('customer_name');
            }
            if (!Schema::hasColumn('sales', 'category')) {
                $table->string('category')->nullable()->after('sales_account');
            }
        });
    }

    /**
     * Reverse the migrations hmmmm.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['sales_account', 'category']);
        });
    }
};
