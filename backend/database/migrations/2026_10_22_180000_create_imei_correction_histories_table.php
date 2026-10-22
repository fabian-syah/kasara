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
        Schema::create('imei_correction_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_detail_id')->nullable()->constrained('product_details')->nullOnDelete();
            $table->string('imei')->index();
            $table->foreignId('old_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('old_product_name');
            $table->string('old_brand')->nullable();
            $table->foreignId('old_brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('old_storage')->nullable();
            $table->string('old_color')->nullable();
            $table->string('old_ram')->nullable();

            $table->foreignId('new_product_id')->constrained('products');
            $table->string('new_product_name');
            $table->string('new_brand')->nullable();
            $table->foreignId('new_brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('new_storage')->nullable();
            $table->string('new_color')->nullable();
            $table->string('new_ram')->nullable();

            $table->string('cs_name_mistake'); // Nama CS yang salah input
            $table->text('notes')->nullable(); // Alasan / keterangan koreksi
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // Super Admin user
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imei_correction_histories');
    }
};
