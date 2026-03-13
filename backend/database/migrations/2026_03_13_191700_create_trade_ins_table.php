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
        Schema::create('trade_ins', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_id')->unique();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->enum('source', ['pstore', 'luar_pstore']);

            // Item Specs
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->string('imei');
            $table->string('ram')->nullable();
            $table->string('storage')->nullable();
            $table->string('condition'); // new, second, ex_ibox

            // Financials
            $table->decimal('buy_price', 15, 2);
            $table->foreignId('payment_method_id')->constrained();

            // Optional
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            // Media
            $table->string('photo_unit')->nullable();
            $table->string('photo_customer')->nullable();

            // Meta
            $table->foreignId('user_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained();

            $table->timestamps();
            $table->softDeletes();
        });

        // Add trade_in_id to product_details
        Schema::table('product_details', function (Blueprint $table) {
            $table->foreignId('trade_in_id')->nullable()->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            $table->dropForeign(['trade_in_id']);
            $table->dropColumn('trade_in_id');
        });
        Schema::dropIfExists('trade_ins');
    }
};
