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
        if (Schema::hasTable('stock_outs')) {
            Schema::table('stock_outs', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_outs', 'deletion_category')) {
                    $table->string('deletion_category', 100)->nullable()->after('deletion_reason');
                }
                if (!Schema::hasColumn('stock_outs', 'correct_data')) {
                    $table->text('correct_data')->nullable()->after('deletion_category');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stock_outs')) {
            Schema::table('stock_outs', function (Blueprint $table) {
                $columnsToDrop = array_values(array_filter(
                    ['deletion_category', 'correct_data'],
                    fn ($col) => Schema::hasColumn('stock_outs', $col)
                ));
                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
