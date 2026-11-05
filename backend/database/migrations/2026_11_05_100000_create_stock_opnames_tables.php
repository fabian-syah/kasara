<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('stock_opnames')) {
            Schema::create('stock_opnames', function (Blueprint $table) {
                $table->id();
                $table->string('opname_number', 50)->unique();
                $table->date('date');
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('user_id'); // User who logged in & created
                $table->unsignedBigInteger('cs_user_id'); // Selected CS user PIC
                $table->string('category_type', 20); // 'hp' or 'non_hp'
                $table->string('method', 20); // 'by_unit' or 'by_imei'
                $table->string('brand', 100)->nullable();
                $table->string('product_type', 150)->nullable();
                $table->integer('total_system')->default(0);
                $table->integer('total_physical')->default(0);
                $table->integer('total_missing')->default(0);
                $table->integer('total_excess')->default(0);
                $table->json('photos')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 30)->default('completed'); // 'completed', 'has_difference'
                $table->timestamps();

                $table->index(['date', 'branch_id']);
                $table->index(['cs_user_id', 'date']);
            });
        }

        if (!Schema::hasTable('stock_opname_items')) {
            Schema::create('stock_opname_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_opname_id')->constrained('stock_opnames')->onDelete('cascade');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('product_detail_id')->nullable();
                $table->string('brand', 100)->nullable();
                $table->string('product_name', 200)->nullable();
                $table->string('storage', 50)->nullable();
                $table->string('condition', 50)->nullable();
                $table->string('imei', 100)->nullable();
                $table->integer('system_qty')->default(1);
                $table->integer('physical_qty')->default(1);
                $table->integer('difference')->default(0);
                $table->boolean('is_present')->default(true); // For by_imei checklist: true = Ada, false = Tidak Ada
                $table->string('notes', 500)->nullable();
                $table->timestamps();

                $table->index('imei');
                $table->index('product_id');
                $table->index('product_detail_id');
            });
        }

        if (!Schema::hasTable('stock_opname_excess_items')) {
            Schema::create('stock_opname_excess_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_opname_id')->constrained('stock_opnames')->onDelete('cascade');
                $table->string('brand', 100)->nullable();
                $table->string('product_name', 200)->nullable();
                $table->string('storage', 50)->nullable();
                $table->string('imei', 100)->nullable();
                $table->integer('quantity')->default(1);
                $table->text('notes')->nullable();
                $table->json('photos')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_excess_items');
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
