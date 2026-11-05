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
        if (Schema::hasTable('stock_opnames') && !Schema::hasColumn('stock_opnames', 'executor_role')) {
            Schema::table('stock_opnames', function (Blueprint $table) {
                $table->string('executor_role', 30)->default('store')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stock_opnames') && Schema::hasColumn('stock_opnames', 'executor_role')) {
            Schema::table('stock_opnames', function (Blueprint $table) {
                $table->dropColumn('executor_role');
            });
        }
    }
};
