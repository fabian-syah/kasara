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
        Schema::create('product_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('imei')->unique(); // Unique IMEI per unit

            // Specs
            $table->string('color')->nullable();
            $table->string('ram')->nullable();
            $table->string('storage')->nullable(); // ROM
            $table->enum('condition', ['new', 'second'])->default('new');

            // Status & Location
            $table->enum('status', ['available', 'sold', 'transfer', 'service', 'booked'])->default('available');
            $table->string('placement_type'); // 'branch', 'warehouse', 'online_shop'
            $table->unsignedBigInteger('placement_id');

            // Financials (Specific to this unit)
            $table->decimal('cost_price', 15, 2)->nullable(); // Harga Modal
            $table->decimal('selling_price', 15, 2)->default(0); // Harga Jual

            // Source
            $table->foreignId('distributor_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for faster lookup
            $table->index(['placement_type', 'placement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_details');
    }
};
