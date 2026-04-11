<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('inventory_user_id')->constrained()->onDelete('set null');
            $table->foreignId('online_shop_id')->nullable()->after('branch_id')->constrained()->onDelete('set null');
            $table->foreignId('warehouse_id')->nullable()->after('online_shop_id')->constrained()->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['online_shop_id']);
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['branch_id', 'online_shop_id', 'warehouse_id']);
        });
    }
};
