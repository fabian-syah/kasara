<?php

$files = [
    'd:/bian/apex-frontend/backend/app/Http/Controllers/AuditController.php',
    'd:/bian/apex-frontend/backend/app/Http/Controllers/SalesController.php'
];

foreach ($files as $file) {
    if (!file_exists($file))
        continue;

    $content = file_get_contents($file);

    // Replace brand_names logic
    $oldBrand1 = "'brand_names' => \$trx->items->map(fn(\$i) => \$i->product->brand ?? '-')->unique()->filter(fn(\$b) => \$b !== '-')->implode(', ') ?: (\$trx->nonHpItems->map(fn(\$i) => \$i->product->brand ?? '-')->unique()->filter(fn(\$b) => \$b !== '-')->implode(', ') ?: '-'),";
    $oldBrand2 = "'brand_names' => \$trx->items->map(fn(\$i) => \$i->product->brand ?? '-')->unique()->implode(', ') ?: (\$trx->nonHpItems->map(fn(\$i) => \$i->product->brand ?? '-')->unique()->implode(', ') ?: '-'),";

    $newBrand = "'brand_names' => collect()->concat(\$trx->items->map(fn(\$i) => \$i->product->brand ?? '-'))->concat(\$trx->nonHpItems->map(fn(\$i) => \$i->product->brand ?? '-'))->unique()->filter(fn(\$b) => \$b !== '-')->implode(', ') ?: '-',";

    $content = str_replace($oldBrand1, $newBrand, $content);
    $content = str_replace($oldBrand2, $newBrand, $content);

    // Replace product_names logic
    $oldProduct1 = "'product_names' => \$trx->items->map(fn(\$i) => \$i->product->name ?? '-')->unique()->filter(fn(\$n) => \$n !== '-')->implode(', ') ?: (\$trx->nonHpItems->map(fn(\$i) => \$i->product->name ?? '-')->unique()->filter(fn(\$n) => \$n !== '-')->implode(', ') ?: '-'),";
    $oldProduct2 = "'product_names' => \$trx->items->map(fn(\$i) => \$i->product->name ?? '-')->unique()->implode(', ') ?: (\$trx->nonHpItems->map(fn(\$i) => \$i->product->name ?? '-')->unique()->implode(', ') ?: '-'),";

    $newProduct = "'product_names' => collect()->concat(\$trx->items->map(fn(\$i) => \$i->product->name ?? '-'))->concat(\$trx->nonHpItems->map(fn(\$i) => \$i->product->name ?? '-'))->unique()->filter(fn(\$n) => \$n !== '-')->implode(', ') ?: '-',";

    $content = str_replace($oldProduct1, $newProduct, $content);
    $content = str_replace($oldProduct2, $newProduct, $content);

    file_put_contents($file, $content);
    echo "Updated $file\n";
}
