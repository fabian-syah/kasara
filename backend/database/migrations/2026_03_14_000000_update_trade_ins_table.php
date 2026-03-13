<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trade_ins', function (Blueprint $table) {
            if (!Schema::hasColumn('trade_ins', 'quantity')) {
                $table->integer('quantity')->default(1)->after('condition');
            }
            $table->string('imei')->nullable()->change();
            if (Schema::hasColumn('trade_ins', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->renameColumn('product_id', 'product_type_id');
            }
        });

        Schema::table('trade_ins', function (Blueprint $table) {
            $table->foreign('product_type_id')->references('id')->on('product_types')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('trade_ins', function (Blueprint $table) {
            $table->dropForeign(['product_type_id']);
            $table->renameColumn('product_type_id', 'product_id');
            $table->string('imei')->nullable(false)->change();
            $table->dropColumn('quantity');
        });

        Schema::table('trade_ins', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }
};
