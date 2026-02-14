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
        Schema::table('stock_outs', function (Blueprint $table) {
            // Polymorphic Destination
            // destination_type: App\Models\Branch, App\Models\Warehouse, etc.
            // But usually we store shorter string map in AppServiceProvider or just full class name.
            // Or use string key: 'branch', 'warehouse', etc. and handle mapping in code.
            // Standard Laravel structure: destination_type (string), destination_id (unsignedBigInteger)
            $table->string('destination_type')->nullable()->after('destination_branch_id');
            $table->unsignedBigInteger('destination_id')->nullable()->after('destination_type');

            // Tracking
            $table->string('status')->default('pending')->after('category'); // pending, received, partial, rejected
            $table->timestamp('received_at')->nullable()->after('updated_at');
            $table->unsignedBigInteger('received_by')->nullable()->after('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropColumn(['destination_type', 'destination_id', 'status', 'received_at', 'received_by']);
        });
    }
};
