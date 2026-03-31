<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('stock_outs', 'reporting_date')) {
            Schema::table('stock_outs', function (Blueprint $table) {
                $table->date('reporting_date')->nullable()->after('receipt_id')->index();
            });
        }

        // BACKFILL DATA
        $this->backfillReportingDates();
    }

    private function backfillReportingDates(): void
    {
        $batchSize = 500;
        $total = \App\Models\StockOut::whereNull('reporting_date')->count();

        if ($total === 0) return;

        \App\Models\StockOut::whereNull('reporting_date')
            ->with(['user.branch', 'user.onlineShop'])
            ->chunkById($batchSize, function (\Illuminate\Database\Eloquent\Collection $records) {
                foreach ($records as $record) {
                    /** @var \App\Models\StockOut $record */
                    $location = null;
                    if ($record->user) {
                        $location = $record->user->branch ?: ($record->user->onlineShop ?: null);
                    }

                    $record->reporting_date = \App\Models\StockOut::calculateReportingDate(
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
        Schema::table('stock_outs', function (Blueprint $row) {
            $row->dropColumn('reporting_date');
        });
    }
};
