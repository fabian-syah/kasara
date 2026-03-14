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
        Schema::create('refunds', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('receipt_id')->unique();
            $blueprint->string('customer_name');
            $blueprint->string('customer_phone');
            $blueprint->foreignId('product_type_id')->constrained('product_types');
            $blueprint->string('imei')->nullable();
            $blueprint->string('ram')->nullable();
            $blueprint->string('condition')->nullable();
            $blueprint->decimal('refund_price', 15, 2);
            $blueprint->foreignId('payment_method_id')->constrained('payment_methods');
            $blueprint->text('reason');
            $blueprint->text('notes')->nullable();
            $blueprint->string('photo_unit')->nullable();
            $blueprint->string('photo_customer')->nullable();
            $blueprint->foreignId('user_id')->constrained('users');
            $blueprint->foreignId('branch_id')->nullable()->constrained('branches');
            $blueprint->timestamps();
            $blueprint->softDeletes();
        });

        Schema::table('product_details', function (Blueprint $table) {
            $table->foreignId('refund_id')->nullable()->after('trade_in_id')->constrained('refunds')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            $table->dropForeign(['refund_id']);
            $table->dropColumn('refund_id');
        });
        Schema::dropIfExists('refunds');
    }
};
