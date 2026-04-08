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
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->string('event_receiver')->nullable()->after('giveaway_notes');
            $table->string('event_phone')->nullable()->after('event_receiver');
            $table->text('event_notes')->nullable()->after('event_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn(['event_receiver', 'event_phone', 'event_notes']);
        });
    }
};
