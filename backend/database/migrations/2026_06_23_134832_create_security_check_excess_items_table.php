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
        Schema::create('security_check_excess_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_check_id')->constrained()->cascadeOnDelete();
            $table->string('brand')->nullable();
            $table->string('type')->nullable();
            $table->string('storage')->nullable();
            $table->integer('excess_qty')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_check_excess_items');
    }
};
