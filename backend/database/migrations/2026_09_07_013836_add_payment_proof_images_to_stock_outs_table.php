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
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->json('payment_proof_images')->nullable()->after('payment_proof_image');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn('payment_proof_images');
        });
    }
};
