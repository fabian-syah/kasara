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
        $tables = ['refunds', 'unit_exchanges', 'tukar_tambahs', 'downgrades'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $col) use ($table) {
                if (!Schema::hasColumn($table, 'distributor_id')) {
                    $col->foreignId('distributor_id')->nullable()->after('customer_phone')->constrained('distributors')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['refunds', 'unit_exchanges', 'tukar_tambahs', 'downgrades'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $col) {
                $col->dropConstrainedForeignId('distributor_id');
            });
        }
    }
};
