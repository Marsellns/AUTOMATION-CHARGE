<?php

namespace App\Console\Commands;

use App\Models\Region;
use App\Models\Site;
use App\Models\SiteMonthlyMetric;
use App\Services\SiteStatusSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Support\ActivityLogStatus;
use XMLReader;
use ZipArchive;

class ImportSimawarPnL extends Command
{
    /** Nilai sentinel INT32 max yang menandakan data sumber tidak valid. */
    private const ANOMALY_SENTINEL = 2147483647;

    protected $signature = 'simawar:import-pnl
                            {file? : Path file Excel relatif ke storage/app/ (default: imports/Simawar_PnL.xlsx)}
                            {--dapot= : Path file Excel Site Owner / Dapot (relatif ke base project)}
                            {--fresh : Hapus semua data metrics, sites, dan regions sebelum import}';

    protected $description = 'Import data PnL dari file Excel Simawar ke database berdasarkan daftar Site ID Dapot (regions, sites, site_monthly_metrics)';

    public function handle(): int
    {
        ini_set('memory_limit', '-1');

        $filePath = $this->argument('file') ?: 'imports/Simawar_PnL.xlsx';
        $fullPath = storage_path("app/{$filePath}");

        if (!file_exists($fullPath)) {
            if (file_exists(base_path($filePath))) {
                $fullPath = base_path($filePath);
            } elseif (file_exists(base_path('DATASET/01 PnL/Simawar PnL.xlsx'))) {
                $fullPath = base_path('DATASET/01 PnL/Simawar PnL.xlsx');
            } else {
                $this->error("File PnL tidak ditemukan: {$fullPath}");
                return self::FAILURE;
            }
        }

        $this->info("╔══════════════════════════════════════════════════════════╗");
        $this->info("║     SIMAWAR PnL IMPORT (FILTERED BY DAPOT SITE ID)       ║");
        $this->info("╚══════════════════════════════════════════════════════════╝");
        $this->newLine();
        $this->info("File PnL : {$fullPath}");
        $this->info("Size     : " . number_format(filesize($fullPath) / 1024 / 1024, 2) . " MB");

        // 1. Cari dan load Master Sites dari File Dapot
        $dapotPath = $this->resolveDapotPath();
        $dapotSites = [];

        if ($dapotPath && file_exists($dapotPath)) {
            $this->info("File Dapot: {$dapotPath}");
            $this->info("Memuat daftar Site ID dari Dapot...");
            $dapotSites = $this->fastLoadDapotSites($dapotPath);
            $this->info("✓ Ditemukan " . number_format(count($dapotSites)) . " Site unik di file Dapot.");
        } else {
            $this->warn("⚠️ File Dapot tidak ditemukan! Import akan memproses seluruh data tanpa filter.");
        }

        $this->newLine();

        // 2. Fresh import: hapus data lama
        if ($this->option('fresh')) {
            $proceed = !$this->input->isInteractive() || $this->confirm('⚠️  --fresh akan MENGHAPUS semua data metrics, sites, dan regions. Lanjutkan?', true);
            if ($proceed) {
                $this->warn("Menghapus data lama...");
                SiteMonthlyMetric::query()->delete();
                Site::query()->delete();
                Region::query()->delete();
                $this->info("Data lama dihapus.");
            } else {
                $this->info("Import dibatalkan.");
                return self::SUCCESS;
            }
        }

        $this->newLine();
        $this->info("Memulai proses streaming XML import...");

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $activityLogStatus = app(ActivityLogStatus::class);
        $activityLogStatus->disable();

        try {
            // 3. Pre-seed seluruh site Dapot ke DB
            $this->info("Menyimpan " . number_format(count($dapotSites)) . " master sites ke database...");
            $siteMap = $this->seedDapotSitesAndGetMap($dapotSites);
            $this->info("✓ Seluruh master site berhasil tersimpan.");

            // 4. Baca streaming PnL dan upsert metrik bulanan
            $this->info("Memproses histori metrik bulanan dari PnL...");
            $stats = $this->fastStreamPnlMetrics($fullPath, $dapotSites, $siteMap);

        } catch (\Throwable $e) {
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

        // 5. Invalidate agregat cache dashboard
        app(SiteStatusSummaryService::class)->invalidateCache();

        // 6. Report Statistics
        $this->newLine();
        $this->info("╔══════════════════════════════════════════════════════════╗");
        $this->info("║     IMPORT COMPLETE                                      ║");
        $this->info("╚══════════════════════════════════════════════════════════╝");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Dapot Sites Target', number_format(count($dapotSites))],
                ['Sites matched & metrics imported', number_format($stats['matched_sites'])],
                ['Non-Dapot rows skipped from PnL', number_format($stats['skipped_non_dapot'])],
                ['Monthly metrics upserted', number_format($stats['metrics_upserted'])],
                ['Total sites in Database', number_format(Site::count())],
                ['Total metrics in Database', number_format(SiteMonthlyMetric::count())],
                ['Time elapsed', "{$elapsed}s"],
                ['Peak memory delta', "{$peakMemory} MB"],
            ]
        );

        $this->newLine();
        $this->info("✅ Data PnL berhasil disesuaikan dengan " . number_format(Site::count()) . " Site dari master Dapot!");

        return self::SUCCESS;
    }

    /**
     * Fast parser Shared Strings dari XLSX.
     */
    private function readSharedStrings(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return [];
        }

        $content = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if ($content === false) {
            return [];
        }

        $strings = [];
        $reader = new XMLReader();
        $reader->XML($content, 'UTF-8', LIBXML_PARSEHUGE | LIBXML_NOBLANKS);

        $current = '';
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $current = '';
            } elseif ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::SIGNIFICANT_WHITESPACE) {
                $current .= $reader->value;
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $strings[] = $current;
            }
        }
        $reader->close();

        return $strings;
    }

    /**
     * Fast reader Dapot sites (Row 1 Header, Col B=Site ID, Col C=Site Name).
     */
    private function fastLoadDapotSites(string $filePath): array
    {
        $strings = $this->readSharedStrings($filePath);

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [];
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $reader = new XMLReader();
        $reader->XML($sheetXml, 'UTF-8', LIBXML_PARSEHUGE | LIBXML_NOBLANKS);

        $sites = [];
        $row = 0;
        $siteId = null;
        $siteName = null;
        $currentCol = '';
        $cellType = '';
        $inV = false;
        $inInlineText = false;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($reader->localName === 'row') {
                    $row++;
                    $siteId = null;
                    $siteName = null;
                } elseif ($reader->localName === 'c') {
                    $r = $reader->getAttribute('r') ?? '';
                    $cellType = $reader->getAttribute('t') ?? '';
                    $currentCol = preg_replace('/[0-9]/', '', $r);
                } elseif ($reader->localName === 'v') {
                    $inV = true;
                } elseif ($reader->localName === 't') {
                    $inInlineText = $row > 1 && ($currentCol === 'B' || $currentCol === 'C');
                }
            } elseif ($reader->nodeType === XMLReader::TEXT && ($inV || $inInlineText)) {
                if ($row > 1 && ($currentCol === 'B' || $currentCol === 'C')) {
                    $val = $reader->value;
                    if ($cellType === 's' && is_numeric($val)) {
                        $idx = (int)$val;
                        if (isset($strings[$idx])) {
                            $val = $strings[$idx];
                        }
                    }
                    if ($currentCol === 'B') $siteId = trim($val);
                    if ($currentCol === 'C') $siteName = trim($val);
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                if ($reader->localName === 'v') {
                    $inV = false;
                } elseif ($reader->localName === 't') {
                    $inInlineText = false;
                } elseif ($reader->localName === 'row') {
                    if ($row > 1 && !empty($siteId) && $siteId !== '-' && strtolower($siteId) !== 'site id') {
                        $upperId = strtoupper($siteId);
                        if (!isset($sites[$upperId])) {
                            $sites[$upperId] = [
                                'site_id'   => $siteId,
                                'site_name' => (!empty($siteName) && $siteName !== '-') ? $siteName : null,
                            ];
                        }
                    }
                }
            }
        }
        $reader->close();

        return $sites;
    }

    /**
     * Pre-seed seluruh site Dapot ke DB dan buat mapping DB ID.
     */
    private function seedDapotSitesAndGetMap(array $dapotSites): array
    {
        $regionKodes = [];
        foreach ($dapotSites as $s) {
            $code = strtoupper(substr($s['site_id'], 0, 3));
            if ($code !== '') {
                $regionKodes[$code] = true;
            }
        }

        $regionMap = [];
        foreach (array_keys($regionKodes) as $kode) {
            $reg = Region::firstOrCreate(['kode' => $kode], ['nama' => null]);
            $regionMap[$kode] = $reg->id;
        }

        $siteRows = [];
        $now = now();
        foreach ($dapotSites as $s) {
            $code = strtoupper(substr($s['site_id'], 0, 3));
            $regId = $regionMap[$code] ?? null;
            $siteRows[] = [
                'site_id'    => $s['site_id'],
                'site_name'  => $s['site_name'],
                'region_id'  => $regId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($siteRows, 500) as $chunk) {
            DB::table('sites')->upsert($chunk, ['site_id'], ['site_name', 'region_id', 'updated_at']);
        }

        $allSites = DB::table('sites')->select('id', 'site_id')->get();
        $map = [];
        foreach ($allSites as $s) {
            $map[strtoupper($s->site_id)] = (int)$s->id;
        }

        return $map;
    }

    /**
     * Fast streaming parser untuk Simawar PnL.xlsx.
     */
    private function fastStreamPnlMetrics(string $filePath, array $dapotSites, array $siteMap): array
    {
        $strings = $this->readSharedStrings($filePath);

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("Tidak dapat membuka file PnL ZIP.");
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new \RuntimeException("Sheet1.xml tidak ditemukan di file PnL.");
        }

        $reader = new XMLReader();
        $reader->XML($sheetXml, 'UTF-8', LIBXML_PARSEHUGE | LIBXML_NOBLANKS);

        $monthList = [
            [1, 2025, 'jan_25'],  [2, 2025, 'feb_25'],  [3, 2025, 'mar_25'],
            [4, 2025, 'apr_25'],  [5, 2025, 'may_25'],  [6, 2025, 'jun_25'],
            [7, 2025, 'jul_25'],  [8, 2025, 'aug_25'],  [9, 2025, 'sep_25'],
            [10, 2025, 'oct_25'], [11, 2025, 'nov_25'], [12, 2025, 'dec_25'],
            [1, 2026, 'jan_26'],  [2, 2026, 'feb_26'],  [3, 2026, 'mar_26'],
            [4, 2026, 'apr_26'],  [5, 2026, 'may_26'],  [6, 2026, 'jun_26'],
        ];

        $headerRow = [];
        $monthColDefs = []; // [ [bulan, tahun, revColLetter, costColLetter, detail columns] ]
        $row = 0;
        $currentRowCells = [];
        $currentCol = '';
        $cellType = '';
        $inV = false;

        $matchedSites = 0;
        $skippedNonDapot = 0;
        $metricsUpserted = 0;

        $metricsBatch = [];
        $siteNameUpdates = [];
        $now = now();

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($reader->localName === 'row') {
                    $row++;
                    $currentRowCells = [];
                } elseif ($reader->localName === 'c') {
                    $r = $reader->getAttribute('r') ?? '';
                    $cellType = $reader->getAttribute('t') ?? '';
                    $currentCol = preg_replace('/[0-9]/', '', $r);
                } elseif ($reader->localName === 'v') {
                    $inV = true;
                }
            } elseif ($reader->nodeType === XMLReader::TEXT && $inV) {
                $val = $reader->value;
                if ($cellType === 's' && is_numeric($val)) {
                    $idx = (int)$val;
                    if (isset($strings[$idx])) {
                        $val = $strings[$idx];
                    }
                }
                $currentRowCells[$currentCol] = $val;
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                if ($reader->localName === 'v') {
                    $inV = false;
                } elseif ($reader->localName === 'row') {
                    if ($row === 2) {
                        // Header Row (Row 2 in Simawar PnL)
                        $normalizedHeader = [];
                        foreach ($currentRowCells as $col => $title) {
                            $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', $title));
                            $normalizedHeader[$col] = $slug;
                        }

                        foreach ($monthList as $m) {
                            [$b, $t, $lbl] = $m;
                            $slugKey = str_replace(['_', '-'], '', $lbl);
                            $slugRev = 'rev' . $slugKey;
                            $slugCost = 'cost' . $slugKey;

                            $revCol = null;
                            $costCol = null;
                            $detailCols = [];
                            foreach ($normalizedHeader as $col => $slug) {
                                if ($slug === $slugRev) $revCol = $col;
                                if ($slug === $slugCost) $costCol = $col;
                            }
                            $detailSlugs = [
                                'opex_freq' => ['opexfreq', 'opex_freq'],
                                'opex_isr' => ['opexisr', 'opex_isr'],
                                'opex_trans' => ['opextrans', 'opex_trans'],
                                'opex_power' => ['opexpower', 'opex_power'],
                                'opex_rm' => ['opexrm', 'opex_rm'],
                                'total_direct_dep' => ['totaldirectdep', 'total_direct_dep'],
                                'rev_voice' => ['revvoice', 'rev_voice'],
                                'rev_sms' => ['revsms', 'rev_sms'],
                                'rev_broath' => ['revbroath', 'rev_broath'],
                                'rev_digi' => ['revdigi', 'rev_digi'],
                                'rev_tapout' => ['revtapout', 'rev_tapout'],
                            ];
                            foreach ($detailSlugs as $field => $prefixes) {
                                foreach ($prefixes as $prefix) {
                                    $detailSlug = $prefix . $slugKey;
                                    $detailCol = array_search($detailSlug, $normalizedHeader, true);
                                    if ($detailCol !== false) {
                                        $detailCols[$field] = $detailCol;
                                        break;
                                    }
                                }
                            }
                            if ($revCol && $costCol) {
                                $monthColDefs[] = [$b, $t, $revCol, $costCol, $detailCols];
                            }
                        }
                    } elseif ($row > 2) {
                        // Data Row: Col C = Site ID, Col D = Site Name
                        $siteId = trim((string)($currentRowCells['C'] ?? ''));
                        if ($siteId === '' || $siteId === '-' || strtolower($siteId) === 'site id') {
                            continue;
                        }

                        $upperId = strtoupper($siteId);
                        if (!empty($dapotSites) && !isset($dapotSites[$upperId])) {
                            $skippedNonDapot++;
                            continue;
                        }

                        $siteDbId = $siteMap[$upperId] ?? null;
                        if (!$siteDbId) {
                            continue;
                        }

                        $matchedSites++;

                        // Nama site dari PnL
                        $pnlSiteName = trim((string)($currentRowCells['D'] ?? ''));
                        if ($pnlSiteName !== '' && $pnlSiteName !== '-') {
                            $siteNameUpdates[] = [
                                'id' => $siteDbId,
                                'site_id' => $siteId,
                                'site_name' => $pnlSiteName,
                            ];
                        }

                        // Ekstrak 18 bulan
                        foreach ($monthColDefs as $mDef) {
                            [$bulan, $tahun, $revCol, $costCol, $detailCols] = $mDef;

                            $revRaw = $currentRowCells[$revCol] ?? null;
                            $costRaw = $currentRowCells[$costCol] ?? null;

                            $revenue = $this->parseNumeric($revRaw);
                            $cost = $this->parseNumeric($costRaw);

                            if ($revenue == 0.0 && $cost == 0.0) {
                                continue;
                            }

                            $pnl = $revenue - $cost;
                            // Sentinel ini dapat muncul di revenue *atau* cost.
                            // Jika salah satunya tidak valid, seluruh PnL baris juga
                            // tidak dapat dipercaya dan harus dikecualikan dari total.
                            $isAnomaly = $this->isAnomaly($revenue, $cost);

                            $metric = [
                                'site_id'     => $siteDbId,
                                'bulan'       => $bulan,
                                'tahun'       => $tahun,
                                'revenue'     => $revenue,
                                'cost'        => $cost,
                                'profit_loss' => $pnl,
                                'is_anomaly'  => $isAnomaly ? 1 : 0,
                                'created_at'  => $now,
                                'updated_at'  => $now,
                            ];

                            foreach ($detailCols as $field => $column) {
                                $metric[$field] = $this->parseNumeric($currentRowCells[$column] ?? null);
                            }

                            $metricsBatch[] = $metric;

                            $metricsUpserted++;

                            if (count($metricsBatch) >= 1000) {
                                DB::table('site_monthly_metrics')->upsert(
                                    $metricsBatch,
                                    ['site_id', 'bulan', 'tahun'],
                                    ['revenue', 'cost', 'profit_loss', 'is_anomaly', 'updated_at',
                                        'opex_freq', 'opex_isr', 'opex_trans', 'opex_power', 'opex_rm',
                                        'total_direct_dep', 'rev_voice', 'rev_sms', 'rev_broath', 'rev_digi', 'rev_tapout']
                                );
                                $metricsBatch = [];
                            }
                        }
                    }
                }
            }
        }

        $reader->close();

        if (!empty($metricsBatch)) {
            DB::table('site_monthly_metrics')->upsert(
                $metricsBatch,
                ['site_id', 'bulan', 'tahun'],
                ['revenue', 'cost', 'profit_loss', 'is_anomaly', 'updated_at',
                    'opex_freq', 'opex_isr', 'opex_trans', 'opex_power', 'opex_rm',
                    'total_direct_dep', 'rev_voice', 'rev_sms', 'rev_broath', 'rev_digi', 'rev_tapout']
            );
        }

        if (!empty($siteNameUpdates)) {
            foreach (array_chunk($siteNameUpdates, 500) as $chunk) {
                DB::table('sites')->upsert($chunk, ['id'], ['site_name']);
            }
        }

        return [
            'matched_sites'    => $matchedSites,
            'skipped_non_dapot' => $skippedNonDapot,
            'metrics_upserted' => $metricsUpserted,
        ];
    }

    /**
     * Cari path file Dapot.
     */
    private function resolveDapotPath(): ?string
    {
        if ($this->option('dapot')) {
            $customPath = base_path($this->option('dapot'));
            if (file_exists($customPath)) return $customPath;
            if (file_exists($this->option('dapot'))) return $this->option('dapot');
        }

        $candidates = [
            base_path('DATASET/05 Data Potensi/Site Owner (Dapot & ANT)/Data Dapot _2026-08-31.xlsx'),
            base_path('DATASET/05 Data Potensi/Site Owner (Dapot & ANT)/Simawar.xlsx'),
            base_path('DATASET/Data Potensi/Site Owner (Dapot & ANT)/Data Dapot _2026-08-31.xlsx'),
            base_path('DATASET/Data Potensi/Site Owner (Dapot & ANT)/Simawar.xlsx'),
            storage_path('app/imports/Data Dapot _2026-08-31.xlsx'),
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) return $path;
        }

        $dapotDir = base_path('DATASET/05 Data Potensi/Site Owner (Dapot & ANT)');
        if (is_dir($dapotDir)) {
            $files = glob($dapotDir . '/*.xlsx');
            if (!empty($files)) return $files[0];
        }

        return null;
    }

    private function parseNumeric(mixed $value): float
    {
        if (is_null($value) || $value === '' || $value === '-') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return $cleaned !== '' ? (float) $cleaned : 0.0;
    }

    private function isAnomaly(float $revenue, float $cost): bool
    {
        return $revenue === self::ANOMALY_SENTINEL || $cost === self::ANOMALY_SENTINEL;
    }
}
