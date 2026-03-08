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
            if (!Schema::hasColumn('stock_outs', 'payment_method_id')) {
                $table->foreignId('payment_method_id')->nullable()->after('sales_account')->constrained('payment_methods')->nullOnDelete();
            }
            if (!Schema::hasColumn('stock_outs', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('payment_method_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('paid_amount');
        });
    }
};
