<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('audit_profits') && !Schema::hasColumn('audit_profits', 'notes')) {
            Schema::table('audit_profits', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('items_modal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_profits') && Schema::hasColumn('audit_profits', 'notes')) {
            Schema::table('audit_profits', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
