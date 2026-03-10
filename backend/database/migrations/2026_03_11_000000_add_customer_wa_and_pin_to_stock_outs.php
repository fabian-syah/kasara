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
            if (!Schema::hasColumn('stock_outs', 'customer_wa')) {
                $table->string('customer_wa')->nullable()->after('customer_phone');
            }
            if (!Schema::hasColumn('stock_outs', 'transaction_pin')) {
                $table->string('transaction_pin')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn(['customer_wa', 'transaction_pin']);
        });
    }
};
