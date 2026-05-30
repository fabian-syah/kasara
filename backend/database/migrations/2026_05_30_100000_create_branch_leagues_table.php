<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->string('league'); // liga_1, liga_2, zona_merah, non_liga
            $table->integer('month'); // 1-12
            $table->integer('year');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'month', 'year']);
            $table->index(['month', 'year', 'league']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_leagues');
    }
};
