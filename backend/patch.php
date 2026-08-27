<?php
$files = [
    "app/Http/Controllers/DashboardController.php",
    "app/Http/Controllers/AuditController.php",
    "app/Http/Controllers/ReportController.php"
];
foreach($files as $file) {
    if(!file_exists($file)) continue;
    $content = file_get_contents($file);
    $new = str_replace("->orWhereBetween('created_at', [\$startTS, \$endTS]);", "/* ->orWhereBetween('created_at', [\$startTS, \$endTS]); */", $content);
    $new = str_replace("->orWhereBetween('stock_outs.created_at', [\$startTS, \$endTS]);", "/* ->orWhereBetween('stock_outs.created_at', [\$startTS, \$endTS]); */", $new);
    if ($content !== $new) {
        file_put_contents($file, $new);
        echo "Fixed $file\n";
    }
}
