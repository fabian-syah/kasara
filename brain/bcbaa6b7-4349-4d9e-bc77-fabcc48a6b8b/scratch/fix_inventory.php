<?php
$file = 'd:/bian/apex-frontend/backend/app/Http/Controllers/InventoryController.php';
$content = file_get_contents($file);

$logicalLogic = <<<'PHP'
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        if ($request->date) {
            $d = $request->date;
            if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner'])) {
                $today = $logicalNow->toDateString();
                $yesterday = $logicalNow->copy()->subDay()->toDateString();
                if ($d < $yesterday) $d = $today;
            }
            $query->whereDate('created_at', $d);
        } elseif ($request->month && $request->year) {
            $m = (int) $request->month;
            $y = (int) $request->year;
            if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner'])) {
                $currentMonth = (int) $logicalNow->format('m');
                $currentYear = (int) $logicalNow->format('Y');
                $lastMonthTemp = $logicalNow->copy()->subMonth();
                $lastMonth = (int) $lastMonthTemp->format('m');
                if ($y < $currentYear) {
                    $m = $currentMonth;
                    $y = $currentYear;
                } elseif ($y == $currentYear && $m < $lastMonth && !($currentMonth == 1 && $m == 12)) {
                    $m = $currentMonth;
                }
            }
            $query->whereMonth('created_at', $m)->whereYear('created_at', $y);
        }
PHP;

$patterns = [
    // Pattern 1: Multi-line whereMonth/whereYear
    <<<'PHP'
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        } elseif ($request->month && $request->year) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
        }
PHP
    ,
    // Pattern 2: Multi-line with comment
    <<<'PHP'
        // DATE FILTER
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        } elseif ($request->month && $request->year) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
        }
PHP
    ,
    // Pattern 3: One-line whereMonth/whereYear
    <<<'PHP'
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        } elseif ($request->month && $request->year) {
            $query->whereMonth('created_at', $request->month)->whereYear('created_at', $request->year);
        }
PHP
];

$content = str_replace("\r\n", "\n", $content);
$logicalLogic = str_replace("\r\n", "\n", $logicalLogic);

$totalReplaced = 0;
foreach ($patterns as $old) {
    $old = str_replace("\r\n", "\n", $old);
    $content = str_replace($old, $logicalLogic, $content, $count);
    $totalReplaced += $count;
}

if ($totalReplaced > 0) {
    echo "Total replaced: $totalReplaced\n";
    file_put_contents($file, $content);
} else {
    echo "No occurrences found.\n";
}
?>
