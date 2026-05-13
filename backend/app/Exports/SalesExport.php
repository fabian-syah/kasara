<?php

namespace App\Exports;

use App\Models\StockOut;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Schema;

class SalesExport
{
    protected $branchId;
    protected $onlineShopId;
    protected $startDate;
    protected $endDate;
    protected $user;
    protected $paymentMethods;

    public function __construct($branchId = null, $onlineShopId = null, $startDate = null, $endDate = null, $user = null)
    {
        $this->branchId = $branchId;
        $this->onlineShopId = $onlineShopId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->user = $user;

        $this->loadPaymentMethods();
    }

    protected function loadPaymentMethods()
    {
        try {
            $query = PaymentMethod::query()->where('is_active', true);
            
            if ($this->branchId) {
                $query->whereHas('branches', function($q) {
                    $q->where('branch_id', $this->branchId);
                });
                
                // Finalize and check if mapped
                $methods = (clone $query)->orderBy('category')->orderBy('name')->get();
                
                // Fallback to ALL ACTIVE if specific branch assignment is not explicitly declared for any
                if ($methods->isEmpty()) {
                    $this->paymentMethods = PaymentMethod::where('is_active', true)->orderBy('category')->orderBy('name')->get();
                } else {
                    $this->paymentMethods = $methods;
                }
            } else {
                $this->paymentMethods = $query->orderBy('category')->orderBy('name')->get();
            }

            // Exclude duplicate 'in TUKAR TAMBAH' column requested by user
            if ($this->paymentMethods) {
                $this->paymentMethods = $this->paymentMethods->reject(function($pm) {
                    return strtolower(trim($pm->name)) === 'in tukar tambah';
                });
            }
        } catch (\Exception $e) {
            $this->paymentMethods = collect();
        }
    }

    public function title(): string
    {
        return 'Laporan Penjualan';
    }

    public function collection()
    {
        $salesCategories = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'tukar_unit', 'tukar_tambah', 'downgrade', 'sale', 'pos', 'SALE', 'POS', 'Sale', 'Pos', 'PENJUALAN_STORE', 'Penjualan_Store', 'refund', 'angkat_barang', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship'];

        $query = StockOut::with(['items.product', 'items.distributor', 'nonHpItems.product', 'nonHpItems.distributor', 'user', 'inventoryUser', 'branch', 'onlineShop', 'paymentMethod'])
            ->whereIn('category', $salesCategories)
            ->whereBetween('reporting_date', [$this->startDate, $this->endDate]);

        if ($this->branchId) {
            $query->where('stock_outs.branch_id', $this->branchId);
        } elseif ($this->onlineShopId) {
            $query->where('stock_outs.online_shop_id', $this->onlineShopId);
        } else {
            if ($this->user && !$this->user->hasAnyRole(['super_admin', 'owner', 'analist', 'analis'])) {
                $branchIds = $this->user->getAccessibleBranchIds();
                $onlineShopIds = $this->user->getAccessibleOnlineShopIds();
                $query->where(function($q) use ($branchIds, $onlineShopIds) {
                    $q->whereIn('stock_outs.branch_id', $branchIds)
                      ->orWhereIn('stock_outs.online_shop_id', $onlineShopIds);
                });
            }
        }

        $stockOuts = $query->whereNull('deleted_at')
            ->orderBy('reporting_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
            
        $rows = [];
        $isStriped = false;

        $receiptIds = $stockOuts->pluck('receipt_id')->filter()->toArray();
        $ttData = \App\Models\TukarTambah::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');
        $dgData = \App\Models\Downgrade::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');
        $ueData = \App\Models\UnitExchange::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');

        foreach ($stockOuts as $so) {
            $isStriped = !$isStriped; // Toggle zebra color state for current transaction group
            
            $location = $so->branch_id ? ($so->branch->name ?? '-') : ($so->onlineShop->name ?? '-');
            $csName = $so->inventoryUser->name ?? ($so->user->name ?? $so->sales_account ?? '-');
            $cat = strtolower($so->category);
            $receiptId = $so->receipt_id;
            
            $exchangeInfo = null;
            if ($cat === 'tukar_tambah') $exchangeInfo = $ttData->get($receiptId);
            elseif ($cat === 'downgrade') $exchangeInfo = $dgData->get($receiptId);
            elseif ($cat === 'tukar_unit') $exchangeInfo = $ueData->get($receiptId);

            $isDeduction = in_array($cat, ['refund', 'angkat_barang']);
            
            // 1. Group all discrete logical products to create unique split rows
            $allOrderItems = [];
            $sumOutPrices = 0;

            // Handle HP Items
            foreach ($so->items as $item) {
                $storage = !empty($item->storage) ? " {$item->storage}" : "";
                $prodName = ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . $storage . " [" . ($item->condition === 'new' ? 'Baru' : 'Second') . "]";
                $imei = $item->imei ? "'" . $item->imei : '-';
                $dist = $item->distributor?->name ?? $item->supplier_name ?? 'PSTORE';
                $itemBase = (float)($item->pivot->selling_price ?? 0);
                $price = $itemBase - (float)($item->pivot->item_discount ?? 0);
                $sumOutPrices += $price;

                $allOrderItems[] = [
                    'type' => $isDeduction ? 'incoming' : 'outgoing', // Redirect Refund/Angkat to Incoming per prompt
                    'name' => $prodName,
                    'imei' => $imei,
                    'qty' => 1,
                    'price' => $price,
                    'dist' => $dist
                ];
            }

            // Handle Non-HP Items
            foreach ($so->nonHpItems as $nItem) {
                $nName = ($nItem->product?->name ?? 'Aksesoris');
                $qty = $nItem->quantity;
                $dist = $nItem->distributor?->name ?? $nItem->product?->brand ?? '-';
                $baseN = (float)($nItem->selling_price ?? 0);
                $pricePerItem = $baseN - (float)($nItem->item_discount ?? 0);
                $totalPrice = $pricePerItem * $qty;
                $sumOutPrices += $totalPrice;

                $allOrderItems[] = [
                    'type' => $isDeduction ? 'incoming' : 'outgoing',
                    'name' => $nName . ($qty > 1 ? " (Qty: $qty)" : ""),
                    'imei' => '-',
                    'qty' => $qty,
                    'price' => $pricePerItem,
                    'dist' => $dist
                ];
            }

            // Handle Dynamic Exchange Inbound Item (TT/DG)
            if ($exchangeInfo) {
                $iStorage = !empty($exchangeInfo->incoming_storage) ? " {$exchangeInfo->incoming_storage}" : "";
                $iName = ($exchangeInfo->incomingProductType->name ?? 'Unit Konsumen') . $iStorage . " [" . ($exchangeInfo->incoming_condition ?? 'Second') . "]";
                $iImei = !empty($exchangeInfo->incoming_imei) ? "'" . $exchangeInfo->incoming_imei : '-';
                $iPrice = (float)($exchangeInfo->incoming_cost_price ?? 0);
                $dIn = $exchangeInfo->distributor?->name ?? $exchangeInfo->incoming_source ?? 'Konsumen';

                $allOrderItems[] = [
                    'type' => 'incoming',
                    'name' => $iName,
                    'imei' => $iImei,
                    'qty' => 1,
                    'price' => $iPrice,
                    'dist' => $dIn
                ];
            }

            // Edge case ensure at least one entry
            if (empty($allOrderItems)) {
                $allOrderItems[] = ['type' => 'none', 'name' => '-', 'imei' => '-', 'qty' => 0, 'price' => 0, 'dist' => '-'];
            }

            // 2. Formulate Header Row Financial Constants
            $finalTotalPenjualan = 0;
            $finalTotalPengeluaran = 0;
            $currentSumPrice = abs($sumOutPrices);
            $discount = (float)($so->total_discount ?? 0);
            
            $isBaseSale = in_array($cat, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship']);
            $isTradeIn = in_array($cat, ['tukar_tambah', 'downgrade']);

            if ($isBaseSale) {
                $finalTotalPenjualan = max(0, $currentSumPrice - $discount);
            } elseif ($isTradeIn && $exchangeInfo) {
                $outVal = abs((float)($exchangeInfo->outgoing_price ?? ($cat === 'tukar_tambah' ? $currentSumPrice : 0)));
                $inVal = abs((float)($exchangeInfo->incoming_cost_price ?? 0));
                if ($cat === 'tukar_tambah') {
                    $finalTotalPenjualan = max(0, $outVal - $discount);
                    $finalTotalPengeluaran = $inVal;
                } elseif ($cat === 'downgrade') {
                    $finalTotalPenjualan = 0; // Downgrade outgoing is excluded from Total Omset as requested
                    $finalTotalPengeluaran = max(0, $inVal - $outVal); // Formula confirmed by calculator
                }
            }

            if ($isDeduction) {
                $finalTotalPengeluaran = $currentSumPrice;
            }

            // Parse Split Payment (Only shown once)
            $payData = [];
            foreach ($this->paymentMethods as $pm) { $payData[$pm->name] = 0; }
            $splitPayments = $so->split_payments_data;
            
            if ($cat !== 'cancel_penjualan' && $cat !== 'downgrade' && !$isDeduction) {
                if (empty($splitPayments)) {
                    $name = $so->paymentMethod->name ?? 'CASH TOKO';
                    $amt = abs((float)($so->paid_amount ?: $so->selling_price));
                    if (isset($payData[$name])) { $payData[$name] = $amt; }
                } else {
                    foreach ($splitPayments as $sp) {
                        $name = $sp['method_name'] ?? 'Lainnya';
                        $amt = abs((float)($sp['amount'] ?? 0));
                        if (isset($payData[$name])) { $payData[$name] += $amt; }
                    }
                }
            }

            // Specialized column requested by user
            $inTukarTambah = ($cat === 'tukar_tambah' && $exchangeInfo) ? (float)($exchangeInfo->incoming_cost_price ?? 0) : 0;

            // 3. Output logical split rows
            foreach ($allOrderItems as $subIdx => $detail) {
                $isFirstRow = ($subIdx === 0);
                
                $rowArr = [
                    'waktu' => date('d/m/Y', strtotime($so->reporting_date)) . ' ' . $so->created_at->format('H:i'),
                    'order_no' => $receiptId,
                    'lokasi' => $location,
                    'user' => $csName,
                    'customer' => $so->customer_name ?? '-',
                    'whatsapp' => $so->customer_wa ?? '-',
                    'category' => strtoupper($so->category),
                    'bundling' => ($cat === 'bundling') ? 'YA' : '-',
                    
                    // Conditional Outbound Columns
                    'produk_keluar' => ($detail['type'] === 'outgoing') ? $detail['name'] : '',
                    'imei_keluar' => ($detail['type'] === 'outgoing') ? $detail['imei'] : '',
                    'qty_keluar' => ($detail['type'] === 'outgoing') ? $detail['qty'] : '',
                    'harga_satuan_keluar' => ($detail['type'] === 'outgoing' && $detail['price'] > 0) ? (float)$detail['price'] : '',
                    'distributor_keluar' => ($detail['type'] === 'outgoing') ? $detail['dist'] : '',
                    
                    // Conditional Inbound Columns
                    'produk_masuk' => ($detail['type'] === 'incoming') ? $detail['name'] : '',
                    'imei_masuk' => ($detail['type'] === 'incoming') ? $detail['imei'] : '',
                    'qty_masuk' => ($detail['type'] === 'incoming') ? $detail['qty'] : '',
                    'harga_satuan_masuk' => ($detail['type'] === 'incoming' && $detail['price'] > 0) ? (float)$detail['price'] : '',
                    'distributor_masuk' => ($detail['type'] === 'incoming') ? $detail['dist'] : '',
                    
                    'in_tukar_tambah' => ($isFirstRow && $inTukarTambah > 0) ? (float)$inTukarTambah : '',
                ];

                // Inject Payments - Empty on downstream rows
                $rowPayData = [];
                foreach ($this->paymentMethods as $pm) {
                    $rowPayData[$pm->name] = $isFirstRow ? (float)($payData[$pm->name] ?? 0) : '';
                }
                $rowArr['payment_details'] = $rowPayData;
                $rowArr['total_discount'] = $isFirstRow ? (float)($so->total_discount ?? 0) : '';

                // Final Aggregation Values - Empty on downstream rows
                $rowArr['total_penjualan'] = ($isFirstRow && $cat !== 'cancel_penjualan') ? (float)$finalTotalPenjualan : '';
                $rowArr['total_pengeluaran'] = ($isFirstRow && $cat !== 'cancel_penjualan') ? (float)$finalTotalPengeluaran : '';
                $rowArr['status'] = $isFirstRow ? strtoupper($so->status ?? 'LUNAS') : '';
                
                // CRITICAL: Metadata for zebra striping in SimpleXLSXGen
                $rowArr['__bg_striped'] = $isStriped;

                $rows[] = $rowArr;
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        $heads = [
            'Waktu',
            'No Pesanan',
            'Lokasi',
            'Customer Service',
            'Nama Customer',
            'WhatsApp',
            'Kategori',
            'Bundling',
            'Produk Keluar',
            'IMEI',
            'Qty',
            'Harga Satuan',
            'Distributor',
            'Produk Masuk',
            'IMEI',
            'Qty',
            'Harga Satuan',
            'Distributor',
            'In Tukar Tambah'
        ];

        foreach ($this->paymentMethods as $pm) {
            $heads[] = $pm->name;
        }

        $heads = array_merge($heads, [
            'Diskon',
            'Total Penjualan',
            'Pengeluaran Refund Angkat Barang Downgrade',
            'Status'
        ]);

        return $heads;
    }
}
