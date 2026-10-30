<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_methods', 'is_online_store')) {
                $table->boolean('is_online_store')->default(false)->after('is_active');
            }
        });

        // Set is_online_store to true for existing Shopee or online store methods
        try {
            DB::table('payment_methods')
                ->where(function ($q) {
                    $q->where('name', 'ilike', '%shopee%')
                      ->orWhere('name', 'ilike', '%online%')
                      ->orWhere('account_name', 'ilike', '%shopee%');
                })
                ->update(['is_online_store' => true]);
        } catch (\Throwable $e) {
            // Ignore if driver doesn't support ilike or error
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (Schema::hasColumn('payment_methods', 'is_online_store')) {
                $table->dropColumn('is_online_store');
            }
        });
    }
};
