<?php
require __DIR__ . '/backend/vendor/autoload.php';

$details = [];

$details[] = [
    'name' => 'IN: iPhone 11',
    'is_incoming' => true
];

$res = collect($details)->contains('is_incoming', true);

echo "Contains: " . ($res ? "YES" : "NO") . "\n";
