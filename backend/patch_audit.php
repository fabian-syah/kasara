<?php
$file = 'd:/bian/apex-frontend/backend/app/Http/Controllers/AuditController.php';
$content = file_get_contents($file);

// We need to add 'pelunasan_dp' to $salesCategories definitions that don't have it yet.
$content = preg_replace(
    "/\['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'cancel_penjualan'\]/",
    "['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'cancel_penjualan', 'pelunasan_dp']",
    $content
);

$content = preg_replace(
    "/\['shopee', 'orderan_online', 'penjualan_offline'\]/",
    "['shopee', 'orderan_online', 'penjualan_offline', 'pelunasan_dp']",
    $content
);

$content = preg_replace(
    "/\['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'angkat_barang', 'brand_ambassador', 'event_\/_sponsorship', 'event_sponsorship', 'pos', 'sale', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store'\]/",
    "['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'angkat_barang', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pos', 'sale', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'pelunasan_dp']",
    $content
);

// We need to also add it to the base sales array where we classify 'base_sale'
// In AuditController.php there's a line:
// in_array($cat, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship'])
$content = preg_replace(
    "/\['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_\/_sponsorship', 'event_sponsorship'\]/",
    "['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pelunasan_dp']",
    $content
);


file_put_contents($file, $content);
echo "Patched successfully\n";
