<?php
/**
 * fix_distributor_database.php
 * Skrip cepat untuk migrasi kolom distributor tanpa lewat artisan migrate.
 */
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Mulai migrasi kolom distributor...\n";

try {
    // 1. Tambah kolom di stock_out_items
    if (!Schema::hasColumn('stock_out_items', 'distributor_id')) {
        Schema::table('stock_out_items', function (Blueprint $table) {
            $table->integer('distributor_id')->nullable();
        });
        echo "- Kolom distributor_id berhasil ditambahkan ke stock_out_items\n";
    } else {
        echo "- Kolom distributor_id sudah ada di stock_out_items (Dilewati)\n";
    }

    // 2. Tambah kolom di stock_out_non_hp_items
    if (!Schema::hasColumn('stock_out_non_hp_items', 'distributor_id')) {
        Schema::table('stock_out_non_hp_items', function (Blueprint $table) {
            $table->integer('distributor_id')->nullable();
        });
        echo "- Kolom distributor_id berhasil ditambahkan ke stock_out_non_hp_items\n";
    } else {
        echo "- Kolom distributor_id sudah ada di stock_out_non_hp_items (Dilewati)\n";
    }

    // 3. Backfill data lama untuk HP
    echo "Mencoba mengisi data distributor lama (Backfill)...\n";
    try {
        $affected = DB::statement("
            UPDATE stock_out_items 
            SET distributor_id = pd.distributor_id
            FROM product_details pd
            WHERE stock_out_items.product_detail_id = pd.id
            AND stock_out_items.distributor_id IS NULL
        ");
        echo "- Backfill data HP selesai.\n";
    } catch (\Exception $e) {
        echo "- Backfill gagal: " . $e->getMessage() . " (Mungkin bukan database PostgreSQL, dilewati)\n";
    }

    echo "\nSUKSES: Database sudah siap!\n";
} catch (\Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
}
