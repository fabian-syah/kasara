<?php

$code = <<<'PHP'
    public function exportRankingExcel(Request $request)
    {
        $logicalNow = now()->hour < 5 ? now()->subDay() : now();
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $user = $request->user();
        if (!$user->hasRole(['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist', 'analis'])) {
            $today = $logicalNow->toDateString();
            $sevenDaysAgo = $logicalNow->copy()->subDays(7)->toDateString();
            $startOfThisMonth = $logicalNow->copy()->startOfMonth()->toDateString();
            $startOfLastMonth = $logicalNow->copy()->subMonth()->startOfMonth()->toDateString();

            if ($startDate && $endDate && $startDate === $endDate) {
                if ($startDate < $sevenDaysAgo) {
                    $startDate = $today;
                    $endDate = $today;
                }
            } elseif ($startDate) {
                if ($startDate < $startOfLastMonth) {
                    $startDate = $startOfThisMonth;
                }
                if (date('Y', strtotime($startDate)) < $logicalNow->format('Y')) {
                    $startDate = $startOfThisMonth;
                }
            }
        }
        
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'angkat_barang', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship', 'pos', 'sale', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store'];
        $salesCategoriesExtended = array_merge($salesCategories, ['refund']);

        // 1. Get Payment Methods
        $paymentMethods = \App\Models\PaymentMethod::all();
        
        // Let's get the base stats from DB
        $baseQuery = DB::table('stock_outs')
            ->join('users', 'stock_outs.user_id', '=', 'users.id')
            ->whereIn('stock_outs.category', $salesCategoriesExtended)
            ->whereNull('stock_outs.deleted_at');

        if ($startDate) $baseQuery->where('stock_outs.reporting_date', '>=', $startDate);
        if ($endDate) $baseQuery->where('stock_outs.reporting_date', '<=', $endDate);

        // Fetch all transactions to aggregate in PHP (since we have JSON arrays for split payments and complex conditions)
        // For a ranking report across a month, the number of records might be 1000-5000, which is perfectly fine to aggregate in PHP.
        // We'll join stock_out_items, product_details, and products.

        $transactions = $baseQuery->select(
            'stock_outs.id',
            'stock_outs.category',
            'stock_outs.selling_price',
            'stock_outs.payment_method_id',
            'stock_outs.split_payments',
            'stock_outs.notes',
            'stock_outs.sales_account',
            'stock_outs.receipt_id',
            DB::raw('COALESCE(stock_outs.branch_id, users.branch_id) as branch_id'),
            DB::raw('COALESCE(stock_outs.online_shop_id, users.online_shop_id) as online_shop_id')
        )->get();

        // We also need items for iphone/android classification
        $itemsQuery = DB::table('stock_out_items')
            ->join('stock_outs', 'stock_out_items.stock_out_id', '=', 'stock_outs.id')
            ->join('product_details', 'stock_out_items.product_detail_id', '=', 'product_details.id')
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->whereIn('stock_outs.category', $salesCategoriesExtended)
            ->whereNull('stock_outs.deleted_at')
            ->where('products.type', 'hp');

        if ($startDate) $itemsQuery->where('stock_outs.reporting_date', '>=', $startDate);
        if ($endDate) $itemsQuery->where('stock_outs.reporting_date', '<=', $endDate);

        $items = $itemsQuery->select(
            'stock_outs.id as stock_out_id',
            'products.brand',
            'product_details.condition',
            'stock_out_items.selling_price as item_price' // Although selling price is often at receipt level
        )->get();

        // Group items by stock_out_id
        $itemsByTx = $items->groupBy('stock_out_id');

        // We need tukar tambah diffs and downgrade diffs
        $tukarTambahs = DB::table('tukar_tambahs')
            ->whereIn('receipt_id', $transactions->pluck('receipt_id')->unique())
            ->get()->groupBy('receipt_id');
            
        $downgrades = DB::table('downgrades')
            ->whereIn('receipt_id', $transactions->pluck('receipt_id')->unique())
            ->get()->groupBy('receipt_id');

        // Init stats arrays
        $statsByLocation = [];

        foreach ($transactions as $tx) {
            $locKey = $tx->branch_id ? 'B_' . $tx->branch_id : 'O_' . $tx->online_shop_id;
            if (!isset($statsByLocation[$locKey])) {
                $statsByLocation[$locKey] = [
                    'omset' => 0,
                    'omset_bersih' => 0,
                    'payments' => [],
                    'iphone_new_qty' => 0, 'iphone_new_amt' => 0,
                    'iphone_scd_qty' => 0, 'iphone_scd_amt' => 0,
                    'android_qty' => 0, 'android_amt' => 0,
                    'refund_qty' => 0, 'refund_amt' => 0,
                    'angkat_barang_qty' => 0, 'angkat_barang_amt' => 0,
                    'tukar_tambah_qty' => 0, 'tukar_tambah_amt' => 0,
                    'tukar_unit_qty' => 0, 'tukar_unit_amt' => 0,
                    'downgrade_qty' => 0, 'downgrade_amt' => 0,
                ];
                foreach ($paymentMethods as $pm) {
                    $statsByLocation[$locKey]['payments'][$pm->id] = 0;
                }
            }

            $cat = strtolower(str_replace(' ', '_', $tx->category));
            $notes = strtolower($tx->notes ?? '');
            $account = strtolower($tx->sales_account ?? '');

            $isTukarTambah = $cat === 'tukar_tambah' || str_contains($notes, 'tukar tambah') || str_contains($notes, 'tukar_tambah') || str_contains($account, 'tukar tambah') || str_contains($account, 'tukar_tambah');
            $isRefund = $cat === 'refund' || str_contains($notes, 'refund') || str_contains($account, 'refund');
            $isAngkatBarang = $cat === 'angkat_barang' || str_contains($notes, 'barang angkat') || str_contains($notes, 'angkat barang') || str_contains($notes, 'angkat_barang') || str_contains($account, 'barang angkat') || str_contains($account, 'angkat barang') || str_contains($account, 'angkat_barang');
            $isTukarUnit = $cat === 'tukar_unit' || str_contains($notes, 'tukar unit') || str_contains($notes, 'tukar_unit') || str_contains($account, 'tukar unit') || str_contains($account, 'tukar_unit');
            $isDowngrade = $cat === 'downgrade' || str_contains($notes, 'downgrade') || str_contains($account, 'downgrade');
            
            $isNormalSales = in_array($cat, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship']);

            $sellingPrice = (float) $tx->selling_price;
            $txOmset = 0;
            $txOmsetBersih = 0;

            if ($isTukarTambah) {
                $tt = $tukarTambahs->get($tx->receipt_id);
                $ttOutgoing = $tt ? $tt->sum('outgoing_price') : $sellingPrice;
                $ttCost = $tt ? $tt->sum('incoming_cost_price') : 0;
                $txOmset = max(0, abs($ttOutgoing));
                $txOmsetBersih = max(0, $ttOutgoing - $ttCost);
                $statsByLocation[$locKey]['tukar_tambah_qty'] += 1;
                $statsByLocation[$locKey]['tukar_tambah_amt'] += $txOmset;
            } elseif ($isNormalSales) {
                $txOmset = max(0, abs($sellingPrice));
                $txOmsetBersih = $txOmset;
            } elseif ($isAngkatBarang) {
                $txOmsetBersih = -abs($sellingPrice);
                $statsByLocation[$locKey]['angkat_barang_qty'] += 1;
                $statsByLocation[$locKey]['angkat_barang_amt'] += abs($sellingPrice);
            } elseif ($isRefund) {
                $txOmsetBersih = -abs($sellingPrice);
                $statsByLocation[$locKey]['refund_qty'] += 1;
                $statsByLocation[$locKey]['refund_amt'] += abs($sellingPrice);
            } elseif ($isDowngrade) {
                $dg = $downgrades->get($tx->receipt_id);
                $txOmsetBersih = $dg ? $dg->sum(fn($d) => $d->outgoing_price - $d->incoming_cost_price) : -abs($sellingPrice);
                $statsByLocation[$locKey]['downgrade_qty'] += 1;
                $statsByLocation[$locKey]['downgrade_amt'] += abs($sellingPrice);
            } elseif ($isTukarUnit) {
                $statsByLocation[$locKey]['tukar_unit_qty'] += 1;
                $statsByLocation[$locKey]['tukar_unit_amt'] += abs($sellingPrice);
            }

            $statsByLocation[$locKey]['omset'] += $txOmset;
            $statsByLocation[$locKey]['omset_bersih'] += $txOmsetBersih;

            // Payments
            $splits = json_decode($tx->split_payments, true);
            if (is_array($splits) && count($splits) > 0) {
                foreach ($splits as $split) {
                    if (isset($split['payment_method_id']) && isset($statsByLocation[$locKey]['payments'][$split['payment_method_id']])) {
                        $statsByLocation[$locKey]['payments'][$split['payment_method_id']] += (float) $split['amount'];
                    }
                }
            } elseif ($tx->payment_method_id) {
                if (isset($statsByLocation[$locKey]['payments'][$tx->payment_method_id])) {
                    $statsByLocation[$locKey]['payments'][$tx->payment_method_id] += $txOmset > 0 ? $txOmset : abs($sellingPrice);
                }
            }

            // Items breakdown
            $txItems = $itemsByTx->get($tx->id, []);
            foreach ($txItems as $item) {
                $brand = strtolower($item->brand);
                $isIphone = str_contains($brand, 'iphone') || str_contains($brand, 'apple');
                $price = $txOmset > 0 ? ($txOmset / count($txItems)) : (abs($sellingPrice) / count($txItems)); // Approximate if we don't have item_price
                
                if ($isIphone) {
                    if ($item->condition === 'new') {
                        $statsByLocation[$locKey]['iphone_new_qty'] += 1;
                        $statsByLocation[$locKey]['iphone_new_amt'] += $price;
                    } else {
                        $statsByLocation[$locKey]['iphone_scd_qty'] += 1;
                        $statsByLocation[$locKey]['iphone_scd_amt'] += $price;
                    }
                } else {
                    $statsByLocation[$locKey]['android_qty'] += 1;
                    $statsByLocation[$locKey]['android_amt'] += $price;
                }
            }
        }

        // Get Names for Branches/Shops
        $branches = \App\Models\Branch::all()->keyBy('id');
        $shops = \App\Models\OnlineShop::all()->keyBy('id');

        $accessibleBranchIds = $user->getAccessibleBranchIds();
        $accessibleOnlineShopIds = $user->getAccessibleOnlineShopIds();
        $isRestricted = !$user->hasRole('super_admin') && !$user->hasRole('analist');

        $finalData = [];
        foreach ($statsByLocation as $locKey => $stats) {
            $type = $locKey[0]; // B or O
            $id = (int) substr($locKey, 2);
            
            if ($isRestricted) {
                if ($type === 'B' && !in_array($id, $accessibleBranchIds)) continue;
                if ($type === 'O' && !in_array($id, $accessibleOnlineShopIds)) continue;
            }
            
            if ($request->boolean('include_zero', false) === false && $stats['omset'] == 0 && $stats['omset_bersih'] == 0) {
                continue;
            }

            $name = $type === 'B' ? ($branches[$id]->name ?? 'Unknown') : ($shops[$id]->name ?? 'Unknown');
            
            $stats['name'] = $name;
            $stats['type'] = $type === 'B' ? 'Offline' : 'Online';
            $finalData[] = $stats;
        }

        $sortBy = $request->query('sort_by', 'omset');
        if ($sortBy === 'omset') {
            usort($finalData, fn($a, $b) => $b['omset'] <=> $a['omset']);
        } else {
            usort($finalData, fn($a, $b) => strcmp($a['name'], $b['name']));
        }

        // Generate Excel
        $header = ['No', 'Nama Unit', 'Tipe', 'Total Omset Kotor', 'Total Omset Bersih'];
        foreach ($paymentMethods as $pm) {
            $header[] = "Pay: " . $pm->name;
        }
        $header = array_merge($header, [
            'iPhone New (Qty)', 'iPhone New (Rp)',
            'iPhone Scd (Qty)', 'iPhone Scd (Rp)',
            'Android (Qty)', 'Android (Rp)',
            'Refund (Qty)', 'Refund (Rp)',
            'Angkat Barang (Qty)', 'Angkat Barang (Rp)',
            'Tukar Tambah (Qty)', 'Tukar Tambah (Rp)',
            'Tukar Unit (Qty)', 'Tukar Unit (Rp)',
            'Downgrade (Qty)', 'Downgrade (Rp)'
        ]);

        $rows = [$header];
        $no = 1;
        foreach ($finalData as $row) {
            $excelRow = [
                $no++,
                $row['name'],
                $row['type'],
                $row['omset'],
                $row['omset_bersih'],
            ];
            
            foreach ($paymentMethods as $pm) {
                $excelRow[] = $row['payments'][$pm->id] ?? 0;
            }

            $excelRow = array_merge($excelRow, [
                $row['iphone_new_qty'], $row['iphone_new_amt'],
                $row['iphone_scd_qty'], $row['iphone_scd_amt'],
                $row['android_qty'], $row['android_amt'],
                $row['refund_qty'], $row['refund_amt'],
                $row['angkat_barang_qty'], $row['angkat_barang_amt'],
                $row['tukar_tambah_qty'], $row['tukar_tambah_amt'],
                $row['tukar_unit_qty'], $row['tukar_unit_amt'],
                $row['downgrade_qty'], $row['downgrade_amt'],
            ]);
            
            $rows[] = $excelRow;
        }

        $xlsx = \App\Utils\SimpleXLSXGen::fromArray($rows);
        $fileName = 'Ranking_Performa_' . ($startDate ?: 'All') . '.xlsx';
        
        $tempPath = storage_path('app/public/' . $fileName);
        $xlsx->saveAs($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend(true);
    }
PHP;

file_put_contents('backend/export_code.php', $code);
echo "Written\n";
