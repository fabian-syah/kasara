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
        Schema::create('dp_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_id')->unique();
            $table->foreignId('stock_out_id')->constrained('stock_outs')->onDelete('cascade');
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->decimal('refund_amount', 15, 2);
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->json('split_payments')->nullable();
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('inventory_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dp_refunds');
    }
};
