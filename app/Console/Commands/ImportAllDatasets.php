<?php

namespace App\Console\Commands;

use App\Imports\Datasets\BapssImport;
use App\Imports\Datasets\CombatSiteImport;
use App\Imports\Datasets\CombatWorkbookImport;
use App\Imports\Datasets\DataAssetTowerImport;
use App\Imports\Datasets\DataSiteUnlockImport;
use App\Imports\Datasets\JaknetContractImport;
use App\Imports\Datasets\ListrikPlnImport;
use App\Imports\Datasets\RecurringIpasImport;
use App\Imports\Datasets\SewaLahanRenewalImport;
use App\Imports\Datasets\SiteOwnerImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import semua file Excel di folder DATASET/ ke tabel masing-masing.
 *
 * Pola snapshot: isi tabel tujuan dihapus dulu lalu diisi ulang dari Excel,
 * sehingga command ini idempotent (aman dijalankan berulang).
 * Data PnL utama (sites, site_monthly_metrics) TIDAK disentuh.
 */
class ImportAllDatasets extends Command
{
    protected $signature = 'dataset:import-all
                            {--only= : Jalankan satu dataset saja (kunci: site-owner, asset-tower, sewa-lahan, combat, recurring, recurring-tagihan-ipas, jaknet, site-unlock, bapss, listrik-pln, listrik-inbuilding)}';

    protected $description = 'Import semua file dataset Simawar (folder DATASET/) ke database';

    /**
     * Definisi dataset: kunci → [label, path Excel (relatif base), tabel, class import].
     */
    private function datasets(): array
    {
        return [
            'site-owner' => [
                'label'  => 'Data Potensi — Site Owner (Dapot & ANT)',
                'file'   => 'DATASET/05 Data Potensi/Site Owner (Dapot & ANT)/Simawar (2).xlsx',
                'table'  => 'site_owners',
                'import' => SiteOwnerImport::class,
            ],
            'asset-tower' => [
                'label'  => 'Data Potensi — Data Asset Tower',
                'file'   => 'DATASET/05 Data Potensi/Data Asset Tower/Data Asset Tower.xlsx',
                'table'  => 'data_asset_towers',
                'import' => DataAssetTowerImport::class,
            ],
            'sewa-lahan' => [
                'label'  => 'Infra 01 — Sewa Lahan (Renewal Eastern Jabotabek)',
                'file'   => 'DATASET/02 Infrastruktur management/01 Sewa Lahan/DATABASE SIMAWAR RENEWAL.xlsx',
                'table'  => 'sewa_lahan_renewals',
                'import' => SewaLahanRenewalImport::class,
            ],
            'combat' => [
                'label'  => 'Infra 02 — Combat',
                // Workbook aktif berisi DATABASE dan DATABASE_REVENUE; file
                // lama yang sudah dibuang tidak lagi menjadi dependensi.
                'file'   => 'DATASET/02 Infrastruktur management/02 Combat/NEW DATABASE COMBAT SIMAWAR.xlsx',
                'table'  => 'combat_sites',
                'import' => CombatSiteImport::class,
            ],
            'recurring' => [
                'label'  => 'Infra 04 — Recurring (ANT & Ipas)',
                'file'   => 'DATASET/02 Infrastruktur management/04 Recurring (ANT & Ipas)/Simawar (1).xlsx',
                'table'  => 'recurring_ipas',
                'import' => RecurringIpasImport::class,
            ],
            'recurring-tagihan-ipas' => [
                'label'  => 'Infra 03 — Recurring (Tagihan Ipas)',
                'file'   => 'DATASET/02 Infrastruktur management/03 Recurring ( Tagihan Ipas)/ExportTagihan-09-09-2026 gg.xlsx',
                'table'  => 'recurring_tagihan_ipas',
                'import' => null,
            ],
            'jaknet' => [
                'label'  => 'Infra 05 — Sewa Lahan (Jaknet & Dapot)',
                'file'   => 'DATASET/02 Infrastruktur management/05 Sewa  Lahan ( Jaknet & Dapot)/Data Jaknet.xlsx',
                'table'  => 'jaknet_contracts',
                'import' => JaknetContractImport::class,
            ],
            'site-unlock' => [
                'label'  => 'Infra 06 — Data Site Unlock',
                'file'   => 'DATASET/02 Infrastruktur management/06 Data Site Unlock/Site UnlockSimawar.xlsx',
                'table'  => 'data_site_unlocks',
                'import' => DataSiteUnlockImport::class,
            ],
            'bapss' => [
                'label'  => 'Infra 07 — BAPSS',
                'file'   => 'DATASET/02 Infrastruktur management/07 BAPSS/Simawar (2).xlsx',
                'table'  => 'bapss',
                'import' => BapssImport::class,
            ],
            'listrik-pln' => [
                'label'  => 'Electricity 01 — Listrik PLN',
                'file'   => 'DATASET/03 Electricity/Centralized/Listrik PLN/Data Master Export.xlsx',
                'table'  => 'listrik_pln',
                'import' => ListrikPlnImport::class,
            ],
            'listrik-inbuilding' => [
                'label'  => 'Electricity Inbuilding — Listrik Inbuilding',
                'file'   => 'DATASET/03 Electricity/Inbuilding/Listrik Inbuilding/Data Inbuilding Export.xlsx',
                'table'  => 'listrik_inbuilding',
                'import' => null,
            ],
        ];
    }

    public function handle(): int
    {
        $datasets = $this->datasets();
        $only = $this->option('only');

        if ($only !== null) {
            if (!isset($datasets[$only])) {
                $this->error("Dataset '{$only}' tidak dikenal. Pilihan: " . implode(', ', array_keys($datasets)));
                return self::FAILURE;
            }
            $datasets = [$only => $datasets[$only]];
        }

        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║     IMPORT DATASET SIMAWAR (SNAPSHOT)        ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->newLine();

        $summary = [];

        foreach ($datasets as $key => $def) {
            $this->info("▶ [{$key}] {$def['label']}");

            $files = [['file' => $def['file'], 'import' => $def['import']]];
            $missing = array_values(array_filter($files, static fn (array $file): bool => ! file_exists(base_path($file['file']))));

            if ($missing !== []) {
                $missingNames = implode(', ', array_map(static fn (array $file): string => $file['file'], $missing));
                $this->error("  File tidak ditemukan: {$missingNames} — dilewati.");
                $summary[] = [$key, $def['table'], 'FILE MISSING', '-', '-'];
                continue;
            }

            $start = microtime(true);

            try {
                if ($key === 'recurring-tagihan-ipas') {
                    $exitCode = $this->call('dataset:import-recurring-tagihan-ipas');
                    if ($exitCode !== self::SUCCESS) {
                        throw new \RuntimeException('Importer streaming mengembalikan status gagal.');
                    }

                    $inserted = DB::table($def['table'])->count();
                    $skipped = 0;
                } elseif ($key === 'listrik-inbuilding') {
                    $exitCode = $this->call('dataset:import-inbuilding-dataset', ['--skip-notifications' => true]);
                    if ($exitCode !== self::SUCCESS) {
                        throw new \RuntimeException('Importer Inbuilding mengembalikan status gagal.');
                    }

                    $inserted = DB::table($def['table'])->count();
                    $skipped = 0;
                } else {
                    [$inserted, $skipped] = DB::transaction(function () use ($key, $def, $files): array {
                        DB::table($def['table'])->delete();

                        $inserted = 0;
                        $skipped = 0;
                        foreach ($files as $file) {
                            $import = $key === 'combat'
                                ? new CombatWorkbookImport()
                                : new $file['import']();

                            if ($import instanceof CombatWorkbookImport) {
                                $import->import(base_path($file['file']));
                            } else {
                                Excel::import($import, base_path($file['file']));
                            }
                            $stats = $import->getStats();
                            $inserted += $stats['inserted'];
                            $skipped += $stats['skipped'];
                        }

                        // status_pembayaran memiliki foreign key cascade ke listrik_pln.
                        // Impor ulang di transaksi yang sama agar snapshot lama kembali
                        // utuh apabila rekonstruksi status pembayaran gagal.
                        if ($key === 'listrik-pln' && $this->call('dataset:import-payment-status') !== self::SUCCESS) {
                            throw new \RuntimeException('Rekonstruksi status pembayaran gagal.');
                        }

                        return [$inserted, $skipped];
                    });
                }
            } catch (\Throwable $e) {
                $this->error("  Import gagal: " . $e->getMessage());
                $summary[] = [$key, $def['table'], 'FAILED', '-', '-'];
                continue;
            }

            $elapsed = round(microtime(true) - $start, 2);
            $dbCount = DB::table($def['table'])->count();

            $this->line("  ✓ inserted: " . number_format($inserted)
                . " | skipped: " . number_format($skipped)
                . " | rows in DB: " . number_format($dbCount)
                . " | {$elapsed}s");

            $summary[] = [
                $key,
                $def['table'],
                number_format($inserted),
                number_format($skipped),
                "{$elapsed}s",
            ];
        }

        $this->newLine();
        $this->table(
            ['Dataset', 'Tabel', 'Inserted', 'Skipped', 'Waktu'],
            $summary
        );

        return self::SUCCESS;
    }
}
