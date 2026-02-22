<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_answers', function (Blueprint $table) {
            $table->text('question_content')->nullable()->after('answer');
            $table->text('notes')->nullable()->after('question_content');
        });

        // Make question_id nullable so deleted questions don't delete answers
        Schema::table('audit_answers', function (Blueprint $table) {
            // Drop old foreign key and unique constraint
            $table->dropForeign(['question_id']);
            $table->dropUnique(['stock_out_id', 'question_id']);

            // Re-add as nullable
            $table->unsignedBigInteger('question_id')->nullable()->change();
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('set null');
            $table->unique(['stock_out_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_answers', function (Blueprint $table) {
            $table->dropColumn(['question_content', 'notes']);
        });

        Schema::table('audit_answers', function (Blueprint $table) {
            $table->dropForeign(['question_id']);
            $table->dropUnique(['stock_out_id', 'question_id']);

            $table->unsignedBigInteger('question_id')->nullable(false)->change();
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
            $table->unique(['stock_out_id', 'question_id']);
        });
    }
};
