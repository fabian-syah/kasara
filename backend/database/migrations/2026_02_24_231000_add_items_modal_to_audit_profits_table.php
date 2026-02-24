<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_profits', function (Blueprint $table) {
            $table->json('items_modal')->nullable()->after('harga_modal');
        });
    }

    public function down(): void
    {
        Schema::table('audit_profits', function (Blueprint $table) {
            $table->dropColumn('items_modal');
        });
    }
};
