<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $u = \App\Models\User::first();
    auth()->login($u);
    $req = app()->make(\Illuminate\Http\Request::class);
    $req->setUserResolver(function() use ($u) { return $u; });
    $req->merge(['start_date'=>'2026-08-30']);
    $res = app()->call('App\Http\Controllers\ReportController@getRankingReport', ['request' => $req]);
    echo "SUCCESS\n";
} catch (\Throwable $e) {
    echo $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n";
}
