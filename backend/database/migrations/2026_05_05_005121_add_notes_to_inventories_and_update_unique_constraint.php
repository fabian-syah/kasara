<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (!Schema::hasColumn('inventories', 'notes')) {
                $table->text('notes')->nullable();
            }
        });

        // Drop old constraint and create new one including notes
        // We use SQL because name might vary or already exist
        DB::statement('ALTER TABLE inventories DROP CONSTRAINT IF EXISTS inv_distributor_unique');
        DB::statement('ALTER TABLE inventories DROP CONSTRAINT IF EXISTS inv_distributor_notes_unique');

        Schema::table('inventories', function (Blueprint $table) {
            $table->unique(['product_id', 'placement_type', 'placement_id', 'distributor_id', 'cost_price', 'user_id', 'notes'], 'inv_distributor_notes_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique('inv_distributor_notes_unique');
            $table->dropColumn('notes');
            // Restore old constraint if needed, but usually we just leave it
            $table->unique(['product_id', 'placement_type', 'placement_id', 'distributor_id', 'cost_price', 'user_id'], 'inv_distributor_unique');
        });
    }
};
