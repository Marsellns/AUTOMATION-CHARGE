<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_owners') && Schema::hasColumn('site_owners', 'status_mla')) {
            DB::table('site_owners')
                ->whereRaw("UPPER(REPLACE(TRIM(status_mla), '-', ' ')) = 'NON MLA'")
                ->update(['status_mla' => 'Non MLA']);
            DB::statement("ALTER TABLE site_owners MODIFY status_mla ENUM('MLA', 'Non MLA') NULL");
        }

        Schema::create('data_asset_towers', function (Blueprint $table) {
            $table->id();
            $table->string('site_id', 30)->index();
            $table->string('site_name')->nullable();
            $table->string('site_company')->nullable();
            $table->string('site_type')->nullable();
            $table->string('grouping')->nullable();
            $table->string('brand')->nullable();
            $table->string('part_name')->nullable();
            $table->string('owner')->nullable();
            $table->string('ownership_status')->nullable();
            $table->text('note')->nullable();
            $table->decimal('tower_height', 8, 2)->nullable();
            $table->decimal('building_height', 8, 2)->nullable();
            $table->string('tower_type')->nullable();
            $table->string('update_by')->nullable();
            $table->date('tanggal_update')->nullable();
            $table->timestamps();
        });

        Schema::create('site_financials', function (Blueprint $table) {
            $table->id();
            $table->string('site_id', 30)->index();
            $table->decimal('revenue', 20, 2)->default(0);
            $table->decimal('cost', 20, 2)->default(0);
            $table->enum('profit_status', ['Profit', 'Loss', 'Break Even'])->nullable();
            $table->string('periode', 20);
            $table->timestamps();
        });

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

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS search_all_resources');
        DB::statement('DROP VIEW IF EXISTS data_site_all_resources');
        Schema::dropIfExists('site_financials');
        Schema::dropIfExists('data_asset_towers');
    }
};
