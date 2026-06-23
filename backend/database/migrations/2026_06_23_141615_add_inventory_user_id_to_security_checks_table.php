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
        Schema::table('security_checks', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_user_id')->nullable()->after('security_name');
            $table->foreign('inventory_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_checks', function (Blueprint $table) {
            $table->dropForeign(['inventory_user_id']);
            $table->dropColumn('inventory_user_id');
        });
    }
};
