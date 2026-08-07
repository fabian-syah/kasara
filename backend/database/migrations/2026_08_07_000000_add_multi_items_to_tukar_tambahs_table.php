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
        Schema::table('tukar_tambahs', function (Blueprint $blueprint) {
            $blueprint->json('incoming_items')->nullable()->after('incoming_cost_price');
            $blueprint->json('outgoing_items')->nullable()->after('outgoing_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tukar_tambahs', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['incoming_items', 'outgoing_items']);
        });
    }
};
