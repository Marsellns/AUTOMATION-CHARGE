<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS search_all_resources');
        DB::statement('DROP VIEW IF EXISTS data_site_all_resources');

        DB::statement(<<<'SQL'
            CREATE VIEW data_site_all_resources AS
            SELECT
                so.site_code AS site_id,
                so.site_name,
                so.site_class,
                so.alamat,
                so.city,
                so.nop,
                so.coverage_type,
                so.status_mla,
                so.pln_connection,
                so.capacity,
                so.id_pel,
                so.tower_height,
                so.site_owner,
                so.tgl_update,
                ri.site_name AS ant_site,
                ri.site_owner AS ant_site_owner,
                ri.rtp AS ant_rtp,
                NULL AS ant_type,
                ri.alamat AS ant_alamat,
                ri.tgl_update AS ant_tgl_update,
                ri.contract_description AS ant_contract_description,
                ri.sow_id AS ant_sow_id,
                ri.year_amount AS ant_year_amount,
                ri.start_date AS ant_start_date,
                ri.end_date AS ant_end_date,
                rti.tp AS ipas_contract,
                rti.contract_type AS ipas_contract_type,
                rti.termin AS ipas_termin,
                rti.periode_ke AS ipas_periode_ke,
                rti.amount AS ipas_amount,
                rti.termin_start AS ipas_termin_start,
                rti.termin_end AS ipas_termin_end
            FROM site_owners so
            LEFT JOIN recurring_ipas ri
                ON UPPER(TRIM(ri.site_code)) = UPPER(TRIM(so.site_code))
            LEFT JOIN recurring_tagihan_ipas rti
                ON UPPER(TRIM(rti.site_code)) = UPPER(TRIM(so.site_code))
        SQL);

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

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS search_all_resources');
        DB::statement('DROP VIEW IF EXISTS data_site_all_resources');

        DB::statement(<<<'SQL'
            CREATE VIEW data_site_all_resources AS
            SELECT
                so.site_code AS site_id,
                so.site_name,
                so.site_class,
                so.alamat,
                so.city,
                so.nop,
                so.coverage_type,
                so.status_mla,
                so.pln_connection,
                so.capacity,
                so.id_pel,
                so.tower_height,
                so.site_owner,
                so.tgl_update,
                ri.rtp AS ant_rtp,
                ri.contract_description AS ant_contract_description,
                ri.sow_id AS ant_sow_id,
                ri.year_amount AS ant_year_amount,
                ri.start_date AS ant_start_date,
                ri.end_date AS ant_end_date,
                rti.tp AS ipas_tp,
                rti.contract_type AS ipas_contract_type,
                rti.termin AS ipas_termin,
                rti.periode_ke AS ipas_periode_ke,
                rti.amount AS ipas_amount,
                rti.termin_start AS ipas_termin_start,
                rti.termin_end AS ipas_termin_end
            FROM site_owners so
            LEFT JOIN recurring_ipas ri
                ON UPPER(TRIM(ri.site_code)) = UPPER(TRIM(so.site_code))
            LEFT JOIN recurring_tagihan_ipas rti
                ON UPPER(TRIM(rti.site_code)) = UPPER(TRIM(so.site_code))
        SQL);

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
