<?php
$so = \App\Models\StockOut::with(['items.product', 'nonHpItems.product'])->where('receipt_id', 'O30APR-XB5')->first();
if (!$so) {
    echo "Not found";
    exit;
}

$groups = [];
$singles = [];

foreach ($so->items as $item) {
    $note = trim($item->pivot->notes ?? '');
    if ($note !== '') {
        $groups[$note][] = ['type' => 'hp', 'data' => $item];
    } else {
        $singles[] = ['type' => 'hp', 'data' => $item];
    }
}
foreach ($so->nonHpItems as $item) {
    $note = trim($item->notes ?? '');
    if ($note !== '') {
        $groups[$note][] = ['type' => 'non_hp', 'data' => $item];
    } else {
        $singles[] = ['type' => 'non_hp', 'data' => $item];
    }
}

echo "Groups: " . count($groups) . "\n";
foreach ($groups as $name => $items) {
    echo "- Group: $name (" . count($items) . " items)\n";
}

echo "Singles: " . count($singles) . "\n";
foreach ($singles as $s) {
    $name = $s['type'] === 'hp' ? $s['data']->productDetail->product->name : $s['data']->product->name;
    echo "- Single: $name\n";
}
