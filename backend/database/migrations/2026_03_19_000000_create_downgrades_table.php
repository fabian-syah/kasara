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
        Schema::create('downgrades', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_id')->unique();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->enum('incoming_source', ['ex_pstore', 'luar_pstore']);

            // Incoming Unit (Barang Masuk)
            $table->foreignId('incoming_product_type_id')->constrained('product_types');
            $table->string('incoming_imei')->nullable();
            $table->string('incoming_storage')->nullable();
            $table->enum('incoming_condition', ['new', 'second', 'ex_ibox']);
            $table->decimal('incoming_cost_price', 15, 2);

            // Outgoing Unit (Barang Keluar)
            $table->foreignId('outgoing_product_detail_id')->constrained('product_details');
            $table->decimal('outgoing_price', 15, 2);

            // Financials
            $table->decimal('price_difference', 15, 2);
            $table->foreignId('payment_method_id')->constrained('payment_methods');

            $table->text('reason');
            $table->text('notes')->nullable();
            $table->string('photo_unit');
            $table->string('photo_customer')->nullable();

            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('branch_id')->nullable()->constrained('branches');

            $table->timestamps();
            $table->softDeletes();
        });

        // Add reference to product_details
        Schema::table('product_details', function (Blueprint $table) {
            $table->foreignId('downgrade_id')->nullable()->constrained('downgrades')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('downgrade_id');
        });
        Schema::dropIfExists('downgrades');
    }
};
