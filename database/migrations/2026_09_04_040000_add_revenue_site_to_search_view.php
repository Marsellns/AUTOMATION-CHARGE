<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS search_all_resources');
        DB::statement(<<<'SQL'
            CREATE VIEW search_all_resources AS
            SELECT
                dsr.*,
                sf.site_id AS rev_site,
                sf.revenue,
                sf.cost,
                (sf.revenue - sf.cost) AS profit_value,
                sf.profit_status,
                sf.periode
            FROM data_site_all_resources dsr
            LEFT JOIN site_financials sf
                ON UPPER(TRIM(sf.site_id)) = UPPER(TRIM(dsr.site_id))
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS search_all_resources');
        DB::statement(<<<'SQL'
            CREATE VIEW search_all_resources AS
            SELECT
                dsr.*,
                sf.revenue,
                sf.cost,
                (sf.revenue - sf.cost) AS profit_value,
                sf.profit_status,
                sf.periode
            FROM data_site_all_resources dsr
            LEFT JOIN site_financials sf
                ON UPPER(TRIM(sf.site_id)) = UPPER(TRIM(dsr.site_id))
        SQL);
    }
};
