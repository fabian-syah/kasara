<?php

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// Bootstrap Laravel
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: application/json');

$user = \App\Models\User::where('username', 'perintis')->first();

if (!$user) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

echo json_encode([
    'username' => $user->username,
    'branch_id' => $user->branch_id,
    'branch_name' => $user->branch?->name,
    'roles' => $user->roles()->pluck('name')->toArray(),
    'accessible_branch_ids' => $user->getAccessibleBranchIds(),
    'hasAnySpecificAssignment' => $user->hasAnySpecificAssignment(),
    'placements' => $user->placements()->get()->toArray(),
], JSON_PRETTY_PRINT);
