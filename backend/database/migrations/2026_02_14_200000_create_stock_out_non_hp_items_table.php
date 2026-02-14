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
        Schema::create('stock_out_non_hp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_out_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity'); // Quantity sent
            $table->integer('received_quantity')->default(0); // Quantity confirmed received
            $table->integer('returned_quantity')->default(0); // Quantity rejected/returned
            // Add status for individual line item if needed, but qty is enough for now
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_out_non_hp_items');
    }
};
