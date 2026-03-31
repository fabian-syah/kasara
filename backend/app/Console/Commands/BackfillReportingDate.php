<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockOut;
use App\Models\Branch;
use App\Models\OnlineShop;
use Carbon\Carbon;

class BackfillReportingDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stockout:backfill-reporting-date {--batch=1000 : Number of records to process per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate reporting_date for historical stock_outs records based on branch/shop timezone and category cutoff times.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting backfill for stock_outs.reporting_date...');

        $batchSize = (int) $this->option('batch');
        $total = StockOut::whereNull('reporting_date')->count();

        if ($total === 0) {
            $this->info('No records found with null reporting_date.');
            return 0;
        }

        $this->info("Processing {$total} records...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        StockOut::whereNull('reporting_date')
            ->orderBy('id', 'asc')
            ->chunk($batchSize, function ($records) use ($bar) {
                foreach ($records as $record) {
                    $location = null;
                    if ($record->user && $record->user->branch_id) {
                        $location = $record->user->branch;
                    } elseif ($record->user && $record->user->online_shop_id) {
                        $location = $record->user->onlineShop;
                    }

                    $record->reporting_date = StockOut::calculateReportingDate(
                        $record->category,
                        $location,
                        $record->created_at
                    );
                    $record->save();
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info('Backfill completed successfully.');

        return 0;
    }
}
