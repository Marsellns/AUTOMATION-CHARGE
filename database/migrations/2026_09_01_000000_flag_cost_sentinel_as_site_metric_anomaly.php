<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ANOMALY_SENTINEL = 2147483647;

    /**
     * The source-data sentinel can occur in either financial column. A row
     * containing it cannot produce a trustworthy PnL and is excluded from
     * dashboard financial aggregates.
     */
    public function up(): void
    {
        DB::table('site_monthly_metrics')
            ->where('revenue', self::ANOMALY_SENTINEL)
            ->orWhere('cost', self::ANOMALY_SENTINEL)
            ->update(['is_anomaly' => true]);
    }

    public function down(): void
    {
        DB::table('site_monthly_metrics')
            ->where('cost', self::ANOMALY_SENTINEL)
            ->where('revenue', '!=', self::ANOMALY_SENTINEL)
            ->update(['is_anomaly' => false]);
    }
};
