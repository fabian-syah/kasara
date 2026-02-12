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
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->change();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete()->after('branch_id');
            $table->foreignId('online_shop_id')->nullable()->constrained()->nullOnDelete()->after('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable(false)->change();
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
            $table->dropForeign(['online_shop_id']);
            $table->dropColumn('online_shop_id');
        });
    }
};
