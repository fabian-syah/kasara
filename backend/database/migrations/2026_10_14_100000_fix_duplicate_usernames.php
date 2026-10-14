<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;

return new class extends Migration {
    public function up(): void
    {
        User::autoFixDuplicateUsernames();
    }

    public function down(): void
    {
        // Disambiguation cannot be reversed safely as original duplicate state was invalid
    }
};
