<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseModuleStatus extends Command
{
    protected $signature = 'simaster:database-status';

    protected $description = 'Periksa penyimpanan MySQL dan jumlah baris seluruh modul laporan';

    public const MODULE_TABLES = [
        'Profit & Loss' => ['sites', 'site_monthly_metrics'],
        'Sewa Lahan & Combat' => ['sewa_lahan_renewals', 'combat_sites'],
        'Infrastruktur lainnya' => ['recurring_ipas', 'recurring_tagihan_ipas', 'jaknet_contracts', 'data_site_unlocks', 'bapss', 'upload_files', 'document_circulations', 'document_approvals'],
        'Electricity Centralized' => ['listrik_pln', 'payment_pln', 'payment_pln_master_monthly', 'status_pembayaran', 'anomali_tagihan_pln', 'bongkar_rampung_mandiri', 'bongkar_rampung_pln', 'listrik_all'],
        'Electricity Inbuilding' => ['listrik_inbuilding', 'payment_ibc', 'anomali_tagihan_inbuilding', 'input_upload_tagihan_ibc', 'inbuilding_all'],
        'PO Monitoring' => ['po_hq', 'po_varcost'],
        'Equipment' => ['equipment_relocation_inventory', 'equipment_relocations'],
        'Feasibility Site' => ['site_owners', 'data_asset_towers', 'data_site_all_resources', 'search_all_resources'],
    ];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('Koneksi aplikasi bukan MySQL.');
            return self::FAILURE;
        }
        $this->info('MySQL: '.DB::connection()->getDatabaseName());
        $rows = [];
        $missing = false;
        foreach (self::MODULE_TABLES as $module => $tables) {
            foreach ($tables as $table) {
                $exists = Schema::hasTable($table) || Schema::hasView($table);
                $count = $exists ? DB::table($table)->count() : null;
                $rows[] = [$module, $table, $count === null ? '-' : number_format($count), ! $exists ? 'TABEL HILANG' : ($count === 0 ? 'KOSONG' : 'TERSEDIA')];
                $missing = $missing || ! $exists;
            }
        }
        $this->table(['Modul', 'Tabel / view MySQL', 'Baris', 'Status'], $rows);
        $this->line('KOSONG berarti tabel tersedia tetapi belum memiliki transaksi; tidak dibuat data contoh.');
        return $missing ? self::FAILURE : self::SUCCESS;
    }
}
