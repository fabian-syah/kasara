<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;

return new class extends Migration {
    public function up(): void
    {
        // Deprecated: automatic forced renaming disrupted active user logins.
    }

    public function down(): void
    {
        // Disambiguation cannot be reversed safely as original duplicate state was invalid
    }
};
