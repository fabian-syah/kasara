<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use App\Models\User;

return new class extends Migration {
    public function up(): void
    {
        try {
            // Find users whose usernames were appended with .cs or .<digits> suffixes
            $users = User::where('username', 'LIKE', '%.%')->get();

            foreach ($users as $u) {
                $clean = strtolower(trim($u->username));

                // Match suffixes like .cs, .cs12, .123, etc.
                if (preg_match('/^(.*?)(\.cs\d*|\.\d+)$/i', $clean, $matches)) {
                    $base = $matches[1];
                    if (!empty($base) && $base !== $clean && strlen($base) >= 2) {
                        $activeConflict = User::whereRaw('LOWER(TRIM(username)) = ?', [$base])
                            ->where('id', '!=', $u->id)
                            ->where('is_active', true)
                            ->first();

                        if (!$activeConflict) {
                            Log::info("Healing suffixed username for User ID {$u->id} ({$u->name}): '{$u->username}' -> '{$base}'");
                            $u->username = $base;
                            if (str_ends_with($u->email ?? '', '@apex-inventory.com') || str_ends_with($u->email ?? '', '@apexpos.com')) {
                                $u->email = $base . ($u->hasRole('inventory') ? '@apex-inventory.com' : '@apexpos.com');
                            }
                            $u->save();
                        } else if (!$u->is_active && $activeConflict) {
                            // Active user already has the base name, nothing to do
                            continue;
                        } else if ($u->is_active && $activeConflict && !$activeConflict->is_active) {
                            // Inactive user held the base name, free it up for the active user
                            $activeConflict->username = $base . '.inactive.' . $activeConflict->id;
                            $activeConflict->save();

                            $u->username = $base;
                            if (str_ends_with($u->email ?? '', '@apex-inventory.com') || str_ends_with($u->email ?? '', '@apexpos.com')) {
                                $u->email = $base . ($u->hasRole('inventory') ? '@apex-inventory.com' : '@apexpos.com');
                            }
                            $u->save();
                            Log::info("Reassigned base username '{$base}' to active User ID {$u->id} from inactive ID {$activeConflict->id}");
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('heal_auto_fixed_usernames migration failed: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
    }
};
