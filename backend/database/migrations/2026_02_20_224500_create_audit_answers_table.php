<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_out_id')->constrained('stock_outs')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
            $table->boolean('answer')->default(false); // true = yes, false = no
            $table->foreignId('auditor_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['stock_out_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_answers');
    }
};
