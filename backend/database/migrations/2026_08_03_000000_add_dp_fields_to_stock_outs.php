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
            if (!Schema::hasColumn('stock_outs', 'dp_amount')) {
                $table->decimal('dp_amount', 15, 2)->nullable()->default(0)->after('selling_price');
            }
            if (!Schema::hasColumn('stock_outs', 'is_dp_settled')) {
                $table->boolean('is_dp_settled')->default(false)->after('dp_amount');
            }
            if (!Schema::hasColumn('stock_outs', 'parent_dp_id')) {
                $table->foreignId('parent_dp_id')->nullable()->constrained('stock_outs')->nullOnDelete()->after('is_dp_settled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropForeign(['parent_dp_id']);
            $table->dropColumn(['dp_amount', 'is_dp_settled', 'parent_dp_id']);
        });
    }
};
