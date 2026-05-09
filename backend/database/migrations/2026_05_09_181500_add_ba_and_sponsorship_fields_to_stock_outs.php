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
            // Brand Ambassador Fields
            $table->string('ba_name')->nullable()->after('event_notes');
            $table->string('ba_phone')->nullable()->after('ba_name');
            $table->string('ba_social_media')->nullable()->after('ba_phone');
            $table->text('ba_notes')->nullable()->after('ba_social_media');

            // Event Sponsorship Fields (Complementing event_receiver, event_phone, event_notes)
            $table->string('event_name')->nullable()->after('ba_notes');
            $table->string('event_doc')->nullable()->after('event_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn([
                'ba_name',
                'ba_phone',
                'ba_social_media',
                'ba_notes',
                'event_name',
                'event_doc',
            ]);
        });
    }
};
