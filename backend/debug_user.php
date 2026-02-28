<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$username = 'leaderdistributortrial';
$user = User::where('username', $username)->first();

if (!$user) {
    echo "User not found\n";
    exit;
}

echo "User: " . $user->username . " (ID: " . $user->id . ")\n";
echo "Role: " . ($user->roles->first()->name ?? 'None') . "\n";
echo "Primary Distributor ID: " . ($user->distributor_id ?? 'None') . "\n";
echo "Placements:\n";
foreach ($user->placements as $p) {
    echo "- " . $p->model_type . ": " . $p->model_id . "\n";
}
echo "Accessible Distributor IDs: " . implode(', ', $user->getAccessibleDistributorIds()) . "\n";
