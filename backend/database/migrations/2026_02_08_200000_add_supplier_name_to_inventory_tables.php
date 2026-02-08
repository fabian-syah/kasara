<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            $table->string('supplier_name')->nullable()->after('distributor_id');
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->string('supplier_name')->nullable()->after('distributor_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            $table->dropColumn('supplier_name');
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropColumn('supplier_name');
        });
    }
};
