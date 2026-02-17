<?php

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Http\Request;

require __DIR__ . '/bootstrap/app.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate finding a user with audit permissions or just a super admin
$user = User::where('role', 'super_admin')->first();
if (!$user) {
    die("No super_admin found");
}

Auth::login($user);

$controller = new \App\Http\Controllers\AuditController();
$request = new Request();
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Mock request data if needed
$request->merge(['year' => 2024]); // or current year

try {
    $response = $controller->analysis($request);
    echo "Status: " . $response->status() . "\n";
    print_r($response->getData(true));
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
    echo $e->getTraceAsString();
}
