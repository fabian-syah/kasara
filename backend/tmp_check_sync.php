<?php
$contents = file_get_contents('/var/www/html/app/Http/Controllers/ReportController.php') ?: file_get_contents(__DIR__ . '/app/Http/Controllers/ReportController.php');
if ($contents) {
    echo "Found ReportController.php. Size: strlen($contents)\n";
    if (strpos($contents, 'md5(preg_replace') !== false) {
        echo "Contains md5(preg_replace) -> YES MY FIX IS THERE.\n";
    } else {
        echo "Contains md5(preg_replace) -> NO!!!! THE CODE ON VPS IS OLD!!!!\n";
    }
} else {
    echo "Could not read ReportController.php\n";
}
