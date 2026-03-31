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
        if (!Schema::hasColumn('stock_outs', 'reporting_date')) {
            Schema::table('stock_outs', function (Blueprint $table) {
                $table->date('reporting_date')->nullable()->after('receipt_id')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $row) {
            $row->dropColumn('reporting_date');
        });
    }
};
