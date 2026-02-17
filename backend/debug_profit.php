<?php

use App\Models\StockOut;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$transactions = StockOut::with(['items.productDetail', 'nonHpItems', 'user.branch', 'inventoryUser.branch'])
    ->whereIn('category', ['shopee', 'orderan_online', 'penjualan_offline'])
    ->get();

$negativeProfits = [];
$groupedByBranch = [];

foreach ($transactions as $trx) {
    $cost = 0;
    $details = [];

    foreach ($trx->items as $item) {
        $cost += $item->cost_price;
        $details[] = "HP: {$item->cost_price}";
    }

    // Non-HP assumed 0 for now as per previous logic, but let's check if there's any weirdness
    foreach ($trx->nonHpItems as $nhp) {
        // $details[] = "NonHP: Qty {$nhp->quantity}";
    }

    $profit = $trx->selling_price - $cost;

    if ($profit < 0) {
        $negativeProfits[] = [
            'id' => $trx->id,
            'receipt' => $trx->receipt_id,
            'sell' => $trx->selling_price,
            'cost' => $cost,
            'profit' => $profit,
            'details' => $details
        ];
    }

    // Check Branch Grouping Logic
    $creatorBranch = $trx->user?->branch?->name ?? 'NoBranch';
    $invBranch = $trx->inventoryUser?->branch?->name ?? 'NoInvBranch';

    // Current logic uses creator
    $source = 'Unknown';
    if ($trx->user) {
        if ($trx->user->branch)
            $source = $trx->user->branch->name;
        elseif ($trx->user->onlineShop)
            $source = $trx->user->onlineShop->name;
    }

    if (!isset($groupedByBranch[$source]))
        $groupedByBranch[$source] = 0;
    $groupedByBranch[$source]++;
}

echo "Total Transactions: " . $transactions->count() . "\n";
echo "Negative Profit Transactions: " . count($negativeProfits) . "\n";
echo "Top 5 Negative Profits:\n";
foreach (array_slice($negativeProfits, 0, 5) as $np) {
    print_r($np);
}

echo "\nBranch Grouping (Current Logic):\n";
print_r($groupedByBranch);

echo "\nCheck 'ANU':\n";
// Check if any user name or branch name is 'ANU'
$anuUser = User::where('name', 'like', '%ANU%')->orWhere('full_name', 'like', '%ANU%')->first();
if ($anuUser)
    echo "Found User 'ANU': " . $anuUser->name . "\n";

$anuBranch = \App\Models\Branch::where('name', 'like', '%ANU%')->first();
if ($anuBranch)
    echo "Found Branch 'ANU': " . $anuBranch->name . "\n";
