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
        Schema::create('tukar_tambahs', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('receipt_id')->unique();
            $blueprint->string('customer_name');
            $blueprint->string('customer_phone');
            $blueprint->enum('incoming_source', ['ex_pstore', 'luar_pstore']);

            // Incoming Unit (Barang Masuk)
            $blueprint->foreignId('incoming_product_type_id')->constrained('product_types');
            $blueprint->string('incoming_imei')->nullable();
            $blueprint->string('incoming_storage')->nullable();
            $blueprint->enum('incoming_condition', ['new', 'second', 'ex_ibox']);
            $blueprint->decimal('incoming_cost_price', 15, 2);

            // Outgoing Unit (Barang Keluar)
            $blueprint->foreignId('outgoing_product_detail_id')->constrained('product_details');
            $blueprint->decimal('outgoing_price', 15, 2);

            // Financials
            $blueprint->decimal('price_difference', 15, 2); // Also known as omset/turnover
            $blueprint->foreignId('payment_method_id')->constrained('payment_methods');

            $blueprint->text('reason');
            $blueprint->text('notes')->nullable();
            $blueprint->string('photo_unit');
            $blueprint->string('photo_customer')->nullable();

            $blueprint->foreignId('user_id')->constrained('users');
            $blueprint->foreignId('branch_id')->nullable()->constrained('branches');

            $blueprint->timestamps();
            $blueprint->softDeletes();
        });

        // Add reference to product_details to track items specifically for Tukar Tambah if needed
        Schema::table('product_details', function (Blueprint $blueprint) {
            $blueprint->foreignId('tukar_tambah_id')->nullable()->constrained('tukar_tambahs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_details', function (Blueprint $blueprint) {
            $blueprint->dropConstrainedForeignId('tukar_tambah_id');
        });
        Schema::dropIfExists('tukar_tambahs');
    }
};
