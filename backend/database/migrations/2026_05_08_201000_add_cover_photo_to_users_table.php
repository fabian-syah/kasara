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
        Schema::table('users', function (Blueprint $blueprint) {
            if (!Schema::hasColumn('users', 'cover_photo')) {
                $blueprint->string('cover_photo')->nullable()->after('photo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $blueprint) {
            if (Schema::hasColumn('users', 'cover_photo')) {
                $blueprint->dropColumn('cover_photo');
            }
        });
    }
};
