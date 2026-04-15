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
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->string('missing_category')->nullable()->after('category');
            $table->string('person_in_charge')->nullable()->after('missing_category');
            $table->text('loss_chronology')->nullable()->after('person_in_charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn(['missing_category', 'person_in_charge', 'loss_chronology']);
        });
    }
};
