<?php

namespace App\Console\Commands;

use App\Imports\SimawarPnLImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Support\ActivityLogStatus;

class ImportSimawarPnL extends Command
{
    protected $signature = 'simawar:import-pnl
                            {file : Path file Excel relatif ke storage/app/ (misal: imports/Simawar_PnL.xlsx)}
                            {--fresh : Hapus semua data metrics, sites, dan regions sebelum import}';

    protected $description = 'Import data PnL dari file Excel Simawar ke database (regions, sites, site_monthly_metrics)';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $fullPath = storage_path("app/{$filePath}");

        // Validasi file exists
        if (!file_exists($fullPath)) {
            $this->error("File tidak ditemukan: {$fullPath}");
            $this->newLine();
            $this->info("Cara copy file dari host ke container Docker:");
            $this->line("  docker compose cp /path/to/Simawar_PnL.xlsx laravel.test:/var/www/html/storage/app/imports/");
            $this->newLine();
            $this->info("Atau dari Windows PowerShell:");
            $this->line("  docker compose cp .\\Simawar_PnL.xlsx laravel.test:/var/www/html/storage/app/imports/");
            return self::FAILURE;
        }

        $this->info("╔══════════════════════════════════════════╗");
        $this->info("║     SIMAWAR PnL IMPORT                  ║");
        $this->info("╚══════════════════════════════════════════╝");
        $this->newLine();
        $this->info("File: {$filePath}");
        $this->info("Size: " . number_format(filesize($fullPath) / 1024 / 1024, 2) . " MB");

        // Fresh import: hapus data lama
        if ($this->option('fresh')) {
            if ($this->confirm('⚠️  --fresh akan MENGHAPUS semua data metrics, sites, dan regions. Lanjutkan?', false)) {
                $this->warn("Menghapus data lama...");
                \App\Models\SiteMonthlyMetric::query()->delete();
                \App\Models\Site::query()->delete();
                \App\Models\Region::query()->delete();
                $this->info("Data lama dihapus.");
            } else {
                $this->info("Import dibatalkan.");
                return self::SUCCESS;
            }
        }

        $this->newLine();
        $this->info("Memulai import... (progress di-log setiap 1000 site)");
        $this->info("Audit trail (activitylog) dinonaktifkan selama bulk import;");
        $this->info("perubahan manual lewat model tetap tercatat seperti biasa.");
        $this->newLine();

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Run import
        $import = new SimawarPnLImport();

        // Bulk import tidak perlu menghasilkan ribuan baris audit log.
        // Status ini scoped di container dan dicek oleh trait LogsActivity
        // di setiap event model; di-enable lagi di finally agar tidak bocor.
        $activityLogStatus = app(ActivityLogStatus::class);
        $activityLogStatus->disable();

        try {
            Excel::import($import, $fullPath);
        } catch (\Exception $e) {
            $this->error("Import gagal: " . $e->getMessage());
            $this->newLine();
            $this->error("Stack trace:");
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        } finally {
            $activityLogStatus->enable();
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $peakMemory = round((memory_get_peak_usage(true) - $startMemory) / 1024 / 1024, 2);

        // Data berubah -> agregat dashboard yang di-cache harus dibuang.
        app(\App\Services\SiteStatusSummaryService::class)->invalidateCache();

        // Report
        $stats = $import->getStats();

        $this->newLine();
        $this->info("╔══════════════════════════════════════════╗");
        $this->info("║     IMPORT COMPLETE                     ║");
        $this->info("╚══════════════════════════════════════════╝");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Sites processed', number_format($stats['processed_rows'])],
                ['Sites skipped (no ID)', number_format($stats['skipped_rows'])],
                ['Metrics upserted', number_format($stats['metrics_upserted'])],
                ['Regions created/found', number_format($stats['regions_cached'])],
                ['Total sites in DB', number_format(\App\Models\Site::count())],
                ['Total metrics in DB', number_format(\App\Models\SiteMonthlyMetric::count())],
                ['Time elapsed', "{$elapsed}s"],
                ['Peak memory delta', "{$peakMemory} MB"],
            ]
        );

        $this->newLine();
        $this->info("✅ Import selesai! Jalankan `php artisan simawar:check-headings {$filePath}` untuk debug jika ada masalah.");

        return self::SUCCESS;
    }
}
