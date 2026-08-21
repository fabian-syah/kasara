<?php
$file = 'd:/bian/apex-frontend/backend/app/Http/Controllers/DashboardController.php';
$content = file_get_contents($file);

// Add pelunasan_dp to array in getTokoOfflineStats
$content = str_replace(
    "'event_sponsorship', 'cancel_penjualan']",
    "'event_sponsorship', 'cancel_penjualan', 'pelunasan_dp']",
    $content
);

// Add to SQL in getRankingData (total_units)
$content = str_replace(
    "'sale', 'tukar_tambah', 'bundling', 'brand_ambassador', 'event_/_sponsorship')",
    "'sale', 'tukar_tambah', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'pelunasan_dp')",
    $content
);

// Add to SQL in getRankingData (net_units) - maybe not needed if it matches above but we'll try to match all 'event_/_sponsorship'
$content = preg_replace(
    "/('event_\/_sponsorship'|'event_sponsorship')(, 'pelunasan_dp')?\)/",
    "$1, 'pelunasan_dp')",
    $content
);

// Add to PHP logic in getRankingData (omset arrays)
$content = preg_replace(
    "/\['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'tukar_tambah', 'brand_ambassador', 'event_\/_sponsorship', 'event_sponsorship'\]/",
    "['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'tukar_tambah', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pelunasan_dp']",
    $content
);
$content = preg_replace(
    "/\['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_\/_sponsorship', 'event_sponsorship'\]/",
    "['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pelunasan_dp']",
    $content
);

// salesCategories in getBranchRankingData
$content = preg_replace(
    "/\['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_tambah', 'downgrade', 'refund', 'angkat_barang', 'sale', 'pos', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'brand_ambassador', 'event_\/_sponsorship', 'event_sponsorship', 'cancel_penjualan'\]/",
    "['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_tambah', 'downgrade', 'refund', 'angkat_barang', 'sale', 'pos', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'cancel_penjualan', 'pelunasan_dp']",
    $content
);


file_put_contents($file, $content);
echo "Patched successfully\n";
