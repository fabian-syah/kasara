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

        $receiptIds = $stockOuts->pluck('receipt_id')->filter()->toArray();
        $ttData = \App\Models\TukarTambah::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');
        $dgData = \App\Models\Downgrade::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');
        $ueData = \App\Models\UnitExchange::with(['incomingProductType', 'distributor'])->whereIn('receipt_id', $receiptIds)->get()->keyBy('receipt_id');

        foreach ($stockOuts as $so) {
            $location = $so->branch_id ? ($so->branch->name ?? '-') : ($so->onlineShop->name ?? '-');
            $csName = $so->inventoryUser->name ?? ($so->user->name ?? $so->sales_account ?? '-');
            $cat = strtolower($so->category);
            $receiptId = $so->receipt_id;
            
            $exchangeInfo = null;
            if ($cat === 'tukar_tambah') $exchangeInfo = $ttData->get($receiptId);
            elseif ($cat === 'downgrade') $exchangeInfo = $dgData->get($receiptId);
            elseif ($cat === 'tukar_unit') $exchangeInfo = $ueData->get($receiptId);

            // 1. Prepare Output Collections (Flattened to 1 row per sale as requested)
            $outProds = [];
            $outImeis = [];
            $outDists = [];
            $qtyOut = 0;
            $sumOutPrices = 0;

            foreach ($so->items as $item) {
                $qtyOut++;
                $prodName = ($item->product->brand ?? '') . ' ' . ($item->product->name ?? '') . " [" . ($item->condition === 'new' ? 'Baru' : 'Second') . "]";
                $outProds[] = $prodName;
                if ($item->imei) $outImeis[] = "'" . $item->imei;
                
                $d = $item->distributor?->name ?? $item->supplier_name ?? 'PSTORE';
                if (!in_array($d, $outDists)) $outDists[] = $d;

                $itemBase = (float)($item->pivot->selling_price ?? 0);
                $netItem = $itemBase - (float)($item->pivot->item_discount ?? 0) - (float)($item->pivot->distributed_discount ?? 0);
                $sumOutPrices += $netItem;
            }

            foreach ($so->nonHpItems as $nItem) {
                $qtyOut += $nItem->quantity;
                $nName = ($nItem->product?->name ?? 'Aksesoris') . " (Qty: {$nItem->quantity})";
                $outProds[] = $nName;

                $d = $nItem->distributor?->name ?? $nItem->product?->brand ?? '-';
                if ($d !== '-' && !in_array($d, $outDists)) $outDists[] = $d;

                $baseN = (float)($nItem->selling_price ?? 0);
                $netN = ($baseN - (float)($nItem->item_discount ?? 0) - (float)($nItem->distributed_discount ?? 0)) * $nItem->quantity;
                $sumOutPrices += $netN;
            }

            // 2. Prepare Incoming Collections
            $inProds = [];
            $inImeis = [];
            $inDists = [];
            $qtyIn = 0;
            $sumInPrices = 0;

            if ($exchangeInfo) {
                $qtyIn = 1;
                $iName = ($exchangeInfo->incomingProductType->name ?? 'Unit Konsumen') . " [" . ($exchangeInfo->incoming_condition ?? 'Second') . "]";
                $inProds[] = $iName;
                if (!empty($exchangeInfo->incoming_imei)) {
                    $inImeis[] = "'" . $exchangeInfo->incoming_imei;
                }
                
                $sumInPrices = (float)($exchangeInfo->incoming_cost_price ?? 0);
                $dIn = $exchangeInfo->distributor?->name ?? $exchangeInfo->incoming_source ?? 'Konsumen';
                $inDists[] = $dIn;
            }

            // 3. Standardized Financial Aggregation mapping EXACTLY to Unified View logic confirmed earlier today
            $baseSales = 0;
            $tradeOutgoingTotal = 0;
            $tradeIncomingTotal = 0;
            $outlay = 0;

            $isBaseSale = in_array($cat, ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship']);
            $isTradeIn = in_array($cat, ['tukar_tambah', 'downgrade']);
            $isDeduction = in_array($cat, ['refund', 'angkat_barang']);

            $currentSumPrice = abs($sumOutPrices); 

            if ($isBaseSale) {
                $baseSales = $currentSumPrice;
            } elseif ($isTradeIn && $exchangeInfo) {
                $outVal = (float)($exchangeInfo->outgoing_price ?? ($cat === 'tukar_tambah' ? $currentSumPrice : 0));
                $tradeOutgoingTotal = abs($outVal);

                $inVal = (float)($exchangeInfo->incoming_cost_price ?? ($cat === 'downgrade' ? $tradeOutgoingTotal + $currentSumPrice : 0));
                $tradeIncomingTotal = abs($inVal);
            }

            if ($isDeduction) {
                $outlay = $currentSumPrice;
            }

            // User Specific Formula: Omset Bersih = Total Sales - Total Deductions (Confirmed targeting -8.299.000)
            $finalTotalOmset = $baseSales + $tradeOutgoingTotal;
            $finalOmsetBersih = $baseSales - ($outlay + $tradeIncomingTotal);

            // 4. Parse Split Payment detailed mapping
            $payData = [];
            foreach ($this->paymentMethods as $pm) {
                $payData[$pm->name] = 0;
            }

            $splitPayments = $so->split_payments_data;
            
            if ($cat === 'cancel_penjualan') {
                $finalTotalOmset = 0;
                $finalOmsetBersih = 0;
            } else {
                if (empty($splitPayments)) {
                    $name = $so->paymentMethod->name ?? 'CASH TOKO';
                    $amt = $so->paid_amount ?: $so->selling_price;
                    if (isset($payData[$name])) {
                        $payData[$name] = (float)$amt;
                    }
                } else {
                    foreach ($splitPayments as $sp) {
                        $name = $sp['method_name'] ?? 'Lainnya';
                        $amt = $sp['amount'] ?? 0;
                        if (isset($payData[$name])) {
                            $payData[$name] += (float)$amt;
                        }
                    }
                }
            }

            // Output ONE fully descriptive combined row
            $rows[] = [
                'waktu' => date('d/m/Y', strtotime($so->reporting_date)) . ' ' . $so->created_at->format('H:i'),
                'order_no' => $receiptId,
                'lokasi' => $location,
                'user' => $csName,
                'customer' => $so->customer_name ?? '-',
                'whatsapp' => $so->customer_wa ?? '-',
                'category' => strtoupper($so->category),
                'produk_keluar' => implode("\n", $outProds) ?: '-',
                'imei_keluar' => implode(", ", $outImeis) ?: '-',
                'qty_keluar' => $qtyOut,
                'harga_satuan_keluar' => (float)$sumOutPrices,
                'distributor_keluar' => implode(", ", $outDists) ?: '-',
                'produk_masuk' => implode("\n", $inProds) ?: '-',
                'imei_masuk' => implode(", ", $inImeis) ?: '-',
                'qty_masuk' => $qtyIn,
                'harga_satuan_masuk' => (float)$sumInPrices,
                'distributor_masuk' => implode(", ", $inDists) ?: '-',
                'payment_details' => $payData,
                'total_penjualan' => (float)$baseSales,
                'total_pengeluaran' => (float)($outlay + $tradeIncomingTotal),
                'status' => strtoupper($so->status ?? 'LUNAS'),
                'total_omset' => (float)$finalTotalOmset,
                'omset_bersih' => (float)$finalOmsetBersih
            ];
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
        ];

        foreach ($this->paymentMethods as $pm) {
            $heads[] = $pm->name;
        }

        $heads = array_merge($heads, [
            'Total Penjualan',
            'Total Pengeluaran',
            'Status',
            'Total Omset',
            'Omset Bersih'
        ]);

        return $heads;
    }
}
