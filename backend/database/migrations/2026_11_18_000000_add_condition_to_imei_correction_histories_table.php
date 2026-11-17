<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('imei_correction_histories')) {
            Schema::table('imei_correction_histories', function (Blueprint $table) {
                if (!Schema::hasColumn('imei_correction_histories', 'old_condition')) {
                    $table->string('old_condition', 50)->nullable()->after('old_ram');
                }
                if (!Schema::hasColumn('imei_correction_histories', 'new_condition')) {
                    $table->string('new_condition', 50)->nullable()->after('new_ram');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('imei_correction_histories')) {
            Schema::table('imei_correction_histories', function (Blueprint $table) {
                if (Schema::hasColumn('imei_correction_histories', 'old_condition')) {
                    $table->dropColumn('old_condition');
                }
                if (Schema::hasColumn('imei_correction_histories', 'new_condition')) {
                    $table->dropColumn('new_condition');
                }
            });
        }
    }
};
