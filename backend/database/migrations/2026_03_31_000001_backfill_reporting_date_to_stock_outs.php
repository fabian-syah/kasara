<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\StockOut;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // BACKFILL DATA FOR EXISTING STOCK_OUTS
        $this->backfillReportingDates();
    }

    private function backfillReportingDates(): void
    {
        $batchSize = 500;
        
        // Only target null reporting_dates
        $total = StockOut::whereNull('reporting_date')->count();

        if ($total === 0) return;

        StockOut::whereNull('reporting_date')
            ->with(['user.branch', 'user.onlineShop'])
            ->chunkById($batchSize, function (\Illuminate\Database\Eloquent\Collection $records) {
                foreach ($records as $record) {
                    /** @var \App\Models\StockOut $record */
                    $location = null;
                    if ($record->user) {
                        $location = $record->user->branch ?: ($record->user->onlineShop ?: null);
                    }

                    $record->reporting_date = StockOut::calculateReportingDate(
                        $record->category,
                        $location,
                        $record->created_at
                    );
                    $record->save(['timestamps' => false]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optional: Reset reporting_date if needed, but usually kept
        // StockOut::whereNotNull('reporting_date')->update(['reporting_date' => null]);
    }
};
