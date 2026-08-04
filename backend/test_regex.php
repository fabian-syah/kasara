<?php
$itemName = "PSTORE UNIT - XR 64 GB SECOND";
$itemName = trim(preg_replace('/\bPSTORE\s+UNIT\b\s*(?:-\s*)?/i', '', $itemName));
echo "Result: " . $itemName . "\n";
