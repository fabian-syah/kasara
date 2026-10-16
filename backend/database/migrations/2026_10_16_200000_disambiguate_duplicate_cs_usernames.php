<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;

return new class extends Migration {
    public function up(): void
    {
        try {
            // Find all duplicate usernames (case-insensitive, ignoring empty)
            $duplicates = DB::table('users')
                ->select(DB::raw('LOWER(TRIM(username)) as clean_username'), DB::raw('count(*) as cnt'))
                ->whereNotNull('username')
                ->whereRaw("TRIM(username) != ''")
                ->groupBy(DB::raw('LOWER(TRIM(username))'))
                ->havingRaw('count(*) > 1')
                ->get();

            foreach ($duplicates as $dup) {
                $clean = $dup->clean_username;

                // Order candidate users:
                // Non-inventory (store / main accounts) come FIRST, inventory (CS) comes LAST.
                // Among same role, oldest (id asc) comes FIRST.
                $users = User::whereRaw('LOWER(TRIM(username)) = ?', [$clean])
                    ->with('roles')
                    ->orderByRaw("CASE 
                        WHEN EXISTS (SELECT 1 FROM model_has_roles mhr JOIN roles r ON r.id = mhr.role_id WHERE mhr.model_id = users.id AND r.name = 'inventory') THEN 1 
                        ELSE 0 END ASC")
                    ->orderBy('id', 'asc')
                    ->get();

                if ($users->count() <= 1) {
                    continue;
                }

                // The first user keeps the username
                $primary = $users->first();
                $cleanPrimary = strtolower(trim($primary->username));
                if ($primary->username !== $cleanPrimary) {
                    $primary->username = $cleanPrimary;
                    $primary->save();
                }

                // Disambiguate subsequent users (especially CS/inventory)
                foreach ($users->slice(1) as $u) {
                    $nameSlug = Str::slug($u->full_name ?: $u->name, '');
                    $suffix = (!empty($nameSlug) && $nameSlug !== $clean) ? $nameSlug : 'cs';
                    $newUsername = $clean . '.' . $suffix;

                    $counter = 1;
                    while (User::whereRaw('LOWER(TRIM(username)) = ?', [$newUsername])->where('id', '!=', $u->id)->exists()) {
                        $counter++;
                        $newUsername = $clean . '.' . $suffix . $counter;
                    }

                    Log::info("Disambiguating duplicate username for User ID {$u->id} ({$u->name}): '{$u->username}' -> '{$newUsername}'");
                    $u->username = $newUsername;
                    if (str_ends_with($u->email ?? '', '@apex-inventory.com') || str_ends_with($u->email ?? '', '@apexpos.com')) {
                        $u->email = $newUsername . ($u->hasRole('inventory') ? '@apex-inventory.com' : '@apexpos.com');
                    }
                    $u->save();
                }
            }
        } catch (\Throwable $e) {
            Log::error('disambiguate_duplicate_cs_usernames migration failed: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
    }
};
