<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update old transaction history categories from penjualan to penjualan_store
        DB::table('stock_outs')
            ->where('category', 'penjualan')
            ->update(['category' => 'penjualan_store']);
            
        // Also update questions table just in case any questions were made for old label
        DB::table('questions')
            ->where('category', 'penjualan')
            ->update(['category' => 'penjualan_store']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('stock_outs')
            ->where('category', 'penjualan_store')
            ->update(['category' => 'penjualan']);
            
        DB::table('questions')
            ->where('category', 'penjualan_store')
            ->update(['category' => 'penjualan']);
    }
};
