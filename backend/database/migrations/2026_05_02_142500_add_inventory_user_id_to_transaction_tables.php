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
        $tables = ['unit_exchanges', 'tukar_tambahs', 'downgrades', 'refunds', 'trade_ins'];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    if (!Schema::hasColumn($table->getTable(), 'inventory_user_id')) {
                        $table->foreignId('inventory_user_id')->nullable()->constrained('users')->nullOnDelete();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['unit_exchanges', 'tukar_tambahs', 'downgrades', 'refunds', 'trade_ins'];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    if (Schema::hasColumn($table->getTable(), 'inventory_user_id')) {
                        $table->dropColumn('inventory_user_id');
                    }
                });
            }
        }
    }
};
