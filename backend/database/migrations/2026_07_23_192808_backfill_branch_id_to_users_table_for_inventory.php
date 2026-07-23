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
        // Get all users who are inventory accounts
        $inventoryUsers = User::role('inventory')->get();

        foreach ($inventoryUsers as $user) {
            // Only update if they don't have a branch_id natively
            if (!$user->branch_id) {
                $accessibleBranchIds = $user->getAccessibleBranchIds();
                if (!empty($accessibleBranchIds)) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['branch_id' => $accessibleBranchIds[0]]);
                }
            }
            
            if (!$user->warehouse_id) {
                $accessibleWarehouseIds = $user->getAccessibleWarehouseIds();
                if (!empty($accessibleWarehouseIds)) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['warehouse_id' => $accessibleWarehouseIds[0]]);
                }
            }
            
            if (!$user->online_shop_id) {
                $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();
                if (!empty($accessibleOnlineShopIds)) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['online_shop_id' => $accessibleOnlineShopIds[0]]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed
    }
};
