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
        Schema::table('product_prices', function (Blueprint $table) {
            $table->string('ram')->nullable()->after('condition');
            $table->string('storage')->nullable()->after('ram');

            // Drop old unique constraint
            $table->dropUnique(['product_type_id', 'condition']);

            // Add new unique constraint including capacity
            // Note: We use a shorter name to avoid max identifier length issues
            $table->unique(['product_type_id', 'condition', 'ram', 'storage'], 'prices_type_cond_cap_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropUnique('prices_type_cond_cap_unique');
            $table->dropColumn(['ram', 'storage']);
            $table->unique(['product_type_id', 'condition']);
        });
    }
};
