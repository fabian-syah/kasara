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
        Schema::table('stock_outs', function (Blueprint $blueprint) {
            $blueprint->boolean('is_bundle')->default(false)->after('category');
            $blueprint->text('bundle_description')->nullable()->after('is_bundle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['is_bundle', 'bundle_description']);
        });
    }
};
