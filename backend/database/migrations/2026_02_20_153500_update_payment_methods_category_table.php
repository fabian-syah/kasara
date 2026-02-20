<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('category')->default('transfer')->after('account_name');
        });

        // Migrate data from is_cash to category
        DB::table('payment_methods')->where('is_cash', true)->update(['category' => 'cash']);

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_cash');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_cash')->default(false)->after('account_name');
        });

        DB::table('payment_methods')->where('category', 'cash')->update(['is_cash' => true]);

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
