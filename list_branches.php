<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Branch;

$branches = Branch::all();
echo "Branches List:\n";
foreach($branches as $b) {
    echo " - ID: " . $b->id . " | Name: " . $b->name . "\n";
}
