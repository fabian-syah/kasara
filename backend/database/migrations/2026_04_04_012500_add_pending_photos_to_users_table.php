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
        Schema::table('users', function (Blueprint $col) {
            $col->string('pending_photo')->nullable()->after('photo');
            $col->string('pending_photo_inventory')->nullable()->after('photo_inventory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $col) {
            $col->dropColumn(['pending_photo', 'pending_photo_inventory']);
        });
    }
};
