<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/reports/ranking', 'GET');
$user = \App\Models\User::first(); // get a user to authenticate
$request->setUserResolver(function() use ($user) { return $user; });

$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";

$kernel->terminate($request, $response);
