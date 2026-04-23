<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->json('allowed_brands')->nullable()->comment('List of brand IDs available for this distributor');
        });
    }

    public function down()
    {
        Schema::table('distributors', function (Blueprint $table) {
            $table->dropColumn('allowed_brands');
        });
    }
};
