<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::create('/api/audit/inventory', 'GET', [
        'online_shop_id' => 1
    ])
);

// We need to be logged in. This script might not work without auth.
// Alternative: test the query logic directly.

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$user = User::where('email', 'like', 'audit%')->first();
if (!$user) {
    echo "No audit user found\n";
    exit;
}

Auth::login($user);

try {
    $controller = new \App\Http\Controllers\AuditController();
    $request = new \Illuminate\Http\Request(['online_shop_id' => 1]);
    $request->setUser($user);
    $res = $controller->inventory($request);
    echo $res->getContent();
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
