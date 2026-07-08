<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockOut;
use App\Models\PaymentMethod;
use App\Utils\SimpleXLSXGen;
use Illuminate\Support\Facades\DB;
use App\Models\Branch;
use App\Models\OnlineShop;

// Let's see the categories available
$categories = DB::table('stock_outs')->distinct()->pluck('category');
echo "Categories: " . implode(', ', $categories->toArray()) . "\n";
