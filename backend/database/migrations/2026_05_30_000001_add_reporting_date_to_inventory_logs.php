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

        // Backfill ALL data: set reporting_date based on created_at with 5AM shift
        // created_at sudah dalam WIB (Asia/Jakarta) karena app timezone = Asia/Jakarta
        DB::statement("
            UPDATE inventory_logs 
            SET reporting_date = CASE 
                WHEN EXTRACT(HOUR FROM created_at) < 5 THEN (created_at::date - INTERVAL '1 day')::date
                ELSE created_at::date
            END
        ");

        // Fix: untuk type='out', sync reporting_date dari StockOut yang terkait (via description containing receipt_id)
        // StockOut sudah punya reporting_date yang benar
        DB::statement("
            UPDATE inventory_logs il
            SET reporting_date = so.reporting_date
            FROM stock_outs so
            WHERE il.type = 'out'
              AND so.reporting_date IS NOT NULL
              AND il.description ILIKE '%' || so.receipt_id || '%'
        ");
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropColumn('reporting_date');
        });
    }
};
