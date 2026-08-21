<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;

$branches = Branch::where('name', 'like', '%CIKARANG%')->get();
foreach($branches as $b) {
    echo "ID: " . $b->id . " | Name: " . $b->name . "\n";
}
