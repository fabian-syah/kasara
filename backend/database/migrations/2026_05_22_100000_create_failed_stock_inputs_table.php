<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('failed_stock_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('hp'); // hp or non-hp
            $table->string('product_name')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('imei')->nullable();
            $table->string('placement_type')->nullable();
            $table->unsignedBigInteger('placement_id')->nullable();
            $table->string('placement_name')->nullable();
            $table->string('distributor_name')->nullable();
            $table->string('condition')->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->integer('quantity')->nullable();
            $table->string('error_message');
            $table->string('error_type')->default('exception'); // validation, duplicate, exception
            $table->json('request_data')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_stock_inputs');
    }
};
