<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all users who are inventory accounts and have branch_id = null
        $inventoryUsers = User::role('inventory')->get();

        foreach ($inventoryUsers as $user) {
            $accessibleBranchIds = $user->getAccessibleBranchIds();
            if (!empty($accessibleBranchIds)) {
                $branchId = $accessibleBranchIds[0];

                // Fix stock_outs
                DB::table('stock_outs')
                    ->where('user_id', $user->id)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $branchId]);

                // Fix inventory_logs
                DB::table('inventory_logs')
                    ->where('user_id', $user->id)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $branchId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed for data fixing
    }
};
