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
        Schema::create('unit_exchanges', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('receipt_id')->unique();
            $blueprint->string('customer_name');
            $blueprint->string('customer_phone');
            $blueprint->enum('incoming_source', ['ex_pstore', 'luar_pstore']);

            // Incoming Unit
            $blueprint->foreignId('incoming_product_type_id')->constrained('product_types');
            $blueprint->string('incoming_imei')->nullable();
            $blueprint->string('incoming_storage')->nullable();
            $blueprint->enum('incoming_condition', ['new', 'second', 'ex_ibox']);
            $blueprint->decimal('incoming_cost_price', 15, 2);

            // Outgoing Unit
            $blueprint->foreignId('outgoing_product_detail_id')->constrained('product_details');

            $blueprint->text('reason');
            $blueprint->text('notes')->nullable();
            $blueprint->string('photo_unit');
            $blueprint->string('photo_customer')->nullable();

            $blueprint->foreignId('user_id')->constrained('users');
            $blueprint->foreignId('branch_id')->nullable()->constrained('branches');

            $blueprint->timestamps();
            $blueprint->softDeletes();
        });

        Schema::table('product_details', function (Blueprint $blueprint) {
            $blueprint->foreignId('unit_exchange_id')->nullable()->constrained('unit_exchanges')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_details', function (Blueprint $blueprint) {
            $blueprint->dropConstrainedForeignId('unit_exchange_id');
        });
        Schema::dropIfExists('unit_exchanges');
    }
};
