<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InventoryLog;
use App\Models\ProductDetail;
use App\Models\Downgrade;
use App\Models\TukarTambah;
use App\Models\Refund;
use App\Models\UnitExchange;

class FixHistoricalInventoryLogs extends Command
{
    protected $signature = 'inventory:fix-historical-logs';
    protected $description = 'Updates reference_id in historical Inventory Logs to correctly map to ProductDetail integer ID instead of string receipt tags.';

    public function handle()
    {
        $this->info('Starting historical inventory log repair...');

        // Prefix configurations
        $types = [
            [
                'prefix' => 'DG IN: ',
                'model' => Downgrade::class,
                'pd_foreign_key' => 'downgrade_id',
                'label' => 'Downgrade'
            ],
            [
                'prefix' => 'TT IN: ',
                'model' => TukarTambah::class,
                'pd_foreign_key' => 'tukar_tambah_id',
                'label' => 'Tukar Tambah'
            ],
            [
                'prefix' => 'Refund: ',
                'model' => Refund::class,
                'pd_foreign_key' => 'refund_id',
                'label' => 'Refund'
            ],
            [
                'prefix' => 'Exchange IN: ',
                'model' => UnitExchange::class,
                'pd_foreign_key' => 'unit_exchange_id',
                'label' => 'Tukar Unit'
            ]
        ];

        $successCount = 0;
        $failCount = 0;

        foreach ($types as $config) {
            $logs = InventoryLog::where('type', 'in')
                ->where('reference_id', 'like', $config['prefix'] . '%')
                ->get();

            if ($logs->count() === 0) {
                $this->info("No historical logs found for {$config['label']}.");
                continue;
            }

            $this->info("Found {$logs->count()} logs for {$config['label']}. Processing...");
            
            foreach ($logs as $log) {
                $receiptId = str_replace($config['prefix'], '', $log->reference_id);
                
                // 1. Find transaction
                $transaction = $config['model']::where('receipt_id', $receiptId)->first();
                
                if (!$transaction) {
                    $this->warn("  [FAIL] Cannot find {$config['label']} receipt: {$receiptId}");
                    $failCount++;
                    continue;
                }

                // 2. Find associated ProductDetail
                $pd = ProductDetail::where($config['pd_foreign_key'], $transaction->id)->first();

                if (!$pd) {
                    $this->warn("  [FAIL] Found receipt {$receiptId} but no matching ProductDetail with {$config['pd_foreign_key']}={$transaction->id}");
                    $failCount++;
                    continue;
                }

                // 3. Update log
                $log->update([
                    'reference_id' => (string)$pd->id,
                    'notes' => 'Fixed: ' . $config['prefix'] . $receiptId . ($log->notes ? ' | ' . $log->notes : '')
                ]);

                $this->info("  [SUCCESS] Map log ID {$log->id} -> ProductDetail ID {$pd->id} (IMEI: {$pd->imei})");
                $successCount++;
            }
        }

        // Optionally fix outgoing descriptions (historical missing IMEIs in combined exports)
        $this->newLine();
        $this->info('Fixing outgoing descriptions missing IMEI brackets for historical logs...');
        
        $outConfigs = [
            [
                'prefix' => 'DG OUT: ',
                'model' => Downgrade::class,
                'pd_foreign_key' => 'outgoing_product_detail_id',
                'label' => 'Downgrade Out'
            ],
            [
                'prefix' => 'TT OUT: ',
                'model' => TukarTambah::class,
                'pd_foreign_key' => 'outgoing_product_detail_id',
                'label' => 'Tukar Tambah Out'
            ],
            [
                'prefix' => 'Exchange OUT: ',
                'model' => UnitExchange::class,
                'pd_foreign_key' => 'outgoing_product_detail_id',
                'label' => 'Exchange Out'
            ],
        ];

        $outFixCount = 0;

        foreach ($outConfigs as $config) {
            $logs = InventoryLog::where('type', 'out')
                ->where('reference_id', 'like', $config['prefix'] . '%')
                ->get();

            foreach ($logs as $log) {
                // If description already has parentheses with something that looks like alphanumeric/IMEI, skip
                if (preg_match('/\([a-zA-Z0-9\s-]+\)/', $log->description)) {
                    continue;
                }

                $receiptId = str_replace($config['prefix'], '', $log->reference_id);
                $transaction = $config['model']::where('receipt_id', $receiptId)->first();

                if ($transaction && $transaction->{$config['pd_foreign_key']}) {
                    $pd = ProductDetail::find($transaction->{$config['pd_foreign_key']});
                    if ($pd && $pd->imei) {
                        $cleanDesc = rtrim($log->description);
                        $log->update([
                            'description' => $cleanDesc . ' (' . $pd->imei . ')'
                        ]);
                        $outFixCount++;
                    }
                }
            }
        }
        
        $this->info("Fixed {$outFixCount} outgoing descriptions by appending IMEI brackets.");

        $this->newLine();
        $this->info("COMPLETED: {$successCount} incoming logs repaired, {$failCount} failed/skipped, {$outFixCount} outgoing descriptions updated.");
        return 0;
    }
}
