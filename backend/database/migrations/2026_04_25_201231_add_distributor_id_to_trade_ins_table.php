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
        Schema::table('trade_ins', function (Blueprint $col) {
            $col->foreignId('distributor_id')->nullable()->after('source')->constrained('distributors')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trade_ins', function (Blueprint $col) {
            $col->dropConstrainedForeignId('distributor_id');
        });
    }
};
