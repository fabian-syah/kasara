<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->date('reporting_date')->nullable()->after('notes')->index();
        });

        // Backfill existing data: set reporting_date based on created_at with 5AM shift (PostgreSQL)
        DB::statement("
            UPDATE inventory_logs 
            SET reporting_date = CASE 
                WHEN EXTRACT(HOUR FROM created_at) < 5 THEN (created_at::date - INTERVAL '1 day')::date
                ELSE created_at::date
            END
            WHERE reporting_date IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropColumn('reporting_date');
        });
    }
};
