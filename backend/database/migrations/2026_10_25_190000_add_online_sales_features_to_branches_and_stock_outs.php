<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('branches', function (Blueprint $table) {
            if (!Schema::hasColumn('branches', 'enable_online_sales')) {
                $table->boolean('enable_online_sales')->default(false)->after('is_active');
            }
        });

        Schema::table('stock_outs', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_outs', 'photo_unit')) {
                $table->string('photo_unit')->nullable()->after('proof_image');
            }
            if (!Schema::hasColumn('stock_outs', 'photo_resi')) {
                $table->string('photo_resi')->nullable()->after('proof_image');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'enable_online_sales')) {
                $table->dropColumn('enable_online_sales');
            }
        });

        Schema::table('stock_outs', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('stock_outs', 'photo_unit')) {
                $cols[] = 'photo_unit';
            }
            if (Schema::hasColumn('stock_outs', 'photo_resi')) {
                $cols[] = 'photo_resi';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
