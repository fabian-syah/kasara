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
        Schema::table('refunds', function (Blueprint $table) {
            if (!Schema::hasColumn('refunds', 'split_payments')) {
                $table->json('split_payments')->nullable()->after('payment_method_id');
            }
        });

        Schema::table('unit_exchanges', function (Blueprint $table) {
            if (!Schema::hasColumn('unit_exchanges', 'split_payments')) {
                $table->json('split_payments')->nullable()->after('outgoing_product_detail_id');
            }
        });

        Schema::table('tukar_tambahs', function (Blueprint $table) {
            if (!Schema::hasColumn('tukar_tambahs', 'split_payments')) {
                $table->json('split_payments')->nullable()->after('payment_method_id');
            }
        });

        Schema::table('downgrades', function (Blueprint $table) {
            if (!Schema::hasColumn('downgrades', 'split_payments')) {
                $table->json('split_payments')->nullable()->after('payment_method_id');
            }
        });

        Schema::table('trade_ins', function (Blueprint $table) {
            if (!Schema::hasColumn('trade_ins', 'split_payments')) {
                $table->json('split_payments')->nullable()->after('payment_method_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            if (Schema::hasColumn('refunds', 'split_payments')) {
                $table->dropColumn('split_payments');
            }
        });

        Schema::table('unit_exchanges', function (Blueprint $table) {
            if (Schema::hasColumn('unit_exchanges', 'split_payments')) {
                $table->dropColumn('split_payments');
            }
        });

        Schema::table('tukar_tambahs', function (Blueprint $table) {
            if (Schema::hasColumn('tukar_tambahs', 'split_payments')) {
                $table->dropColumn('split_payments');
            }
        });

        Schema::table('downgrades', function (Blueprint $table) {
            if (Schema::hasColumn('downgrades', 'split_payments')) {
                $table->dropColumn('split_payments');
            }
        });

        Schema::table('trade_ins', function (Blueprint $table) {
            if (Schema::hasColumn('trade_ins', 'split_payments')) {
                $table->dropColumn('split_payments');
            }
        });
    }
};
