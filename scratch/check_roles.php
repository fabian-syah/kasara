<?php
require __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use App\Models\User;

$roles = Role::all()->pluck('name');
echo "Roles in DB: " . $roles->implode(', ') . "\n";

$user = User::where('username', 'audittrial')->first();
if ($user) {
    echo "User audittrial roles: " . $user->getRoleNames()->implode(', ') . "\n";
    echo "hasRole('audit'): " . ($user->hasRole('audit') ? 'YES' : 'NO') . "\n";
    echo "hasRole('Audit'): " . ($user->hasRole('Audit') ? 'YES' : 'NO') . "\n";
} else {
    echo "User audittrial not found\n";
}
