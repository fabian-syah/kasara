<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_profits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_out_id')->constrained('stock_outs')->onDelete('cascade');
            $table->decimal('harga_modal', 15, 2)->default(0);
            $table->foreignId('auditor_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique('stock_out_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_profits');
    }
};
