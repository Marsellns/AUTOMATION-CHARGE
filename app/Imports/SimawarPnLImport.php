<?php

namespace App\Imports;

use App\Models\Region;
use App\Models\Site;
use App\Models\SiteMonthlyMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SimawarPnLImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    /** Nilai sentinel INT32 max yang menandakan data sumber tidak valid. */
    private const ANOMALY_SENTINEL = 2147483647;

    /**
     * Mapping 18 bulan ke heading keys.
    * Format: [bulan, tahun, revenue_key, cost_key, detail_keys]
     *
     * PENTING: Keys di sini harus match persis dengan output dari
     * `php artisan simawar:check-headings`.
     */
    private array $monthColumns;

    /**
     * Master Site list dari Dapot (Site Owner).
     * Format: [UPPERCASE_SITE_ID => ['site_id' => ..., 'site_name' => ...]]
     */
    private array $allowedSites = [];

    /** Cache regions yang sudah di-firstOrCreate (kode => id) */
    private array $regionCache = [];

    /** Cache sites yang sudah di-updateOrCreate (site_id => id) */
    private array $siteCache = [];

    /** Counter untuk progress reporting */
    private int $processedRows = 0;
    private int $skippedRows = 0;
    private int $skippedNonDapot = 0;
    private int $metricsUpserted = 0;

    public function __construct(array $allowedSites = [])
    {
        $this->monthColumns = $this->buildMonthColumns();
        if (!empty($allowedSites)) {
            $this->setAllowedSites($allowedSites);
        }
    }

    /**
     * Set master sites dari Dapot dan pastikan semua site terdaftar di database.
     */
    public function setAllowedSites(array $allowedSites): void
    {
        $this->allowedSites = [];
        foreach ($allowedSites as $key => $val) {
            $sId = is_array($val) ? ($val['site_id'] ?? $key) : (is_string($val) ? $val : $key);
            $sName = is_array($val) ? ($val['site_name'] ?? null) : null;
            $cleanId = $this->cleanString($sId);
            if (!empty($cleanId)) {
                $upper = strtoupper($cleanId);
                $this->allowedSites[$upper] = [
                    'site_id'   => $cleanId,
                    'site_name' => $this->cleanSiteName($sName),
                ];
            }
        }

        // Pre-seed seluruh site Dapot ke DB
        $this->seedAllowedSitesToDatabase();
    }

    /**
     * Pastikan semua Site dari Dapot memiliki entri di tabel `sites`.
     */
    private function seedAllowedSitesToDatabase(): void
    {
        foreach ($this->allowedSites as $siteData) {
            $sId = $siteData['site_id'];
            $sName = $siteData['site_name'];
            $regionKode = strtoupper(substr($sId, 0, 3));
            $regionId = $this->getOrCreateRegionId($regionKode);
            $this->getOrCreateSiteId($sId, $sName, $regionId);
        }
    }

    /**
     * Baris ke-2 adalah header (baris 1 = judul "Simawar").
     */
    public function headingRow(): int
    {
        return 2;
    }

    /**
     * Chunk size untuk memory efficiency.
     * 500 baris per chunk = ~27K metrics per chunk (500 x 18 bulan x 3 kolom).
     */
    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Process setiap chunk of rows.
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->processRow($row);
        }
    }

    /**
     * Process satu baris Excel.
     */
    private function processRow(Collection $row): void
    {
        // Skip baris tanpa Site ID
        $siteId = $this->cleanString($row['site_id'] ?? null);
        if (empty($siteId)) {
            $this->skippedRows++;
            return;
        }

        $upperSiteId = strtoupper($siteId);

        // Jika master Dapot ditentukan, hanya proses site yang terdaftar di Dapot
        if (!empty($this->allowedSites) && !isset($this->allowedSites[$upperSiteId])) {
            $this->skippedNonDapot++;
            return;
        }

        // 1. Extract region kode dari 3 huruf pertama Site ID
        $regionKode = strtoupper(substr($siteId, 0, 3));

        // 2. FirstOrCreate region (cached)
        $regionId = $this->getOrCreateRegionId($regionKode);

        // 3. Clean site name (prioritaskan dari file PnL jika ada, fallback ke Dapot)
        $siteName = $this->cleanSiteName($row['site_name'] ?? null);
        if ($siteName === null && isset($this->allowedSites[$upperSiteId]['site_name'])) {
            $siteName = $this->allowedSites[$upperSiteId]['site_name'];
        }

        // 4. UpdateOrCreate site (cached)
        $siteDbId = $this->getOrCreateSiteId($siteId, $siteName, $regionId);

        // 5. Loop 18 bulan: insert/update metrics
        foreach ($this->monthColumns as $monthDef) {
            [$bulan, $tahun, $revKey, $costKey, $detailKeys] = $monthDef;

            $revenue = $this->parseNumeric($row[$revKey] ?? null);
            $cost = $this->parseNumeric($row[$costKey] ?? null);

            // Skip bulan yang tidak ada data (kedua kolom null/0)
            if ($revenue == 0 && $cost == 0) {
                continue;
            }

            // profit_loss dihitung otomatis oleh event saving di model SiteMonthlyMetric (revenue - cost)
            $details = [];
            foreach ($detailKeys as $column => $keys) {
                $details[$column] = $this->parseDetailValue($row, $keys);
            }

            SiteMonthlyMetric::updateOrCreate(
                [
                    'site_id' => $siteDbId,
                    'bulan'   => $bulan,
                    'tahun'   => $tahun,
                ],
                [
                    'revenue' => $revenue,
                    'cost'    => $cost,
                    'is_anomaly' => $this->isAnomaly($revenue, $cost),
                    ...$details,
                ]
            );

            $this->metricsUpserted++;
        }

        $this->processedRows++;

        // Log progress setiap 500 baris yang berhasil diproses
        if ($this->processedRows % 500 === 0) {
            Log::info("SimawarPnLImport progress: {$this->processedRows} matching sites processed, {$this->metricsUpserted} metrics upserted");
        }
    }

    /**
     * Build mapping 18 bulan ke heading keys.
     *
     * Menghasilkan array of [bulan, tahun, rev_key, cost_key].
     * PnL key sengaja TIDAK di-include karena kita hitung sendiri.
     */
    private function buildMonthColumns(): array
    {
        $months = [
            // [bulan_num, tahun, bulan_label]
            [1, 2025, 'jan_25'],   [2, 2025, 'feb_25'],   [3, 2025, 'mar_25'],
            [4, 2025, 'apr_25'],   [5, 2025, 'may_25'],   [6, 2025, 'jun_25'],
            [7, 2025, 'jul_25'],   [8, 2025, 'aug_25'],   [9, 2025, 'sep_25'],
            [10, 2025, 'oct_25'],  [11, 2025, 'nov_25'],  [12, 2025, 'dec_25'],
            [1, 2026, 'jan_26'],   [2, 2026, 'feb_26'],   [3, 2026, 'mar_26'],
            [4, 2026, 'apr_26'],   [5, 2026, 'may_26'],   [6, 2026, 'jun_26'],
        ];

        return array_map(function ($m) {
            [$bulan, $tahun, $label] = $m;
            return [
                $bulan,
                $tahun,
                "rev_{$label}",   // e.g. "rev_jan_25"
                "cost_{$label}",  // e.g. "cost_jan_25"
                $this->detailKeys($label),
            ];
        }, $months);
    }

    private function detailKeys(string $label): array
    {
        return [
            'opex_freq' => ["opexfreq_{$label}", "opex_freq_{$label}"],
            'opex_isr' => ["opexisr_{$label}", "opex_isr_{$label}"],
            'opex_trans' => ["opextrans_{$label}", "opex_trans_{$label}"],
            'opex_power' => ["opexpower_{$label}", "opex_power_{$label}"],
            'opex_rm' => ["opexrm_{$label}", "opex_rm_{$label}"],
            'total_direct_dep' => ["totaldirectdep_{$label}", "total_direct_dep_{$label}"],
            'rev_voice' => ["revvoice_{$label}", "rev_voice_{$label}"],
            'rev_sms' => ["revsms_{$label}", "rev_sms_{$label}"],
            'rev_broath' => ["revbroath_{$label}", "rev_broath_{$label}"],
            'rev_digi' => ["revdigi_{$label}", "rev_digi_{$label}"],
            'rev_tapout' => ["revtapout_{$label}", "rev_tapout_{$label}"],
        ];
    }

    private function parseDetailValue(Collection $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            if ($row->has($key)) {
                return $this->parseNumeric($row->get($key));
            }
        }

        return null;
    }

    /**
     * Get or create region ID (cached untuk avoid repeated DB queries).
     */
    private function getOrCreateRegionId(string $kode): int
    {
        if (!isset($this->regionCache[$kode])) {
            $region = Region::firstOrCreate(
                ['kode' => $kode],
                ['nama' => null] // Nama diisi manual / seeder
            );
            $this->regionCache[$kode] = $region->id;
        }

        return $this->regionCache[$kode];
    }

    /**
     * Get or create site DB ID (cached).
     */
    private function getOrCreateSiteId(string $siteId, ?string $siteName, int $regionId): int
    {
        if (!isset($this->siteCache[$siteId])) {
            $site = Site::updateOrCreate(
                ['site_id' => $siteId],
                [
                    'site_name' => $siteName,
                    'region_id' => $regionId,
                ]
            );
            $this->siteCache[$siteId] = $site->id;
        }

        return $this->siteCache[$siteId];
    }

    /**
     * Parse numeric value dari cell Excel.
     * Handle format: "50,000,000" atau 50000000 atau null.
     */
    private function parseNumeric(mixed $value): float
    {
        if (is_null($value) || $value === '' || $value === '-') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        // Remove commas dan non-numeric chars (kecuali minus dan dot)
        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return $cleaned !== '' ? (float) $cleaned : 0.0;
    }

    /**
     * Nilai sentinel dapat berada pada revenue maupun cost. Dalam kedua
     * kondisi, PnL hasil pengurangan tidak layak dimasukkan ke agregat.
     */
    private function isAnomaly(float $revenue, float $cost): bool
    {
        return $revenue === self::ANOMALY_SENTINEL || $cost === self::ANOMALY_SENTINEL;
    }

    /**
     * Clean site name: null, empty, atau "-" → null.
     */
    private function cleanSiteName(mixed $value): ?string
    {
        $cleaned = $this->cleanString($value);

        if (empty($cleaned) || $cleaned === '-') {
            return null;
        }

        return $cleaned;
    }

    /**
     * Trim dan clean string value.
     */
    private function cleanString(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Get import statistics.
     */
    public function getStats(): array
    {
        return [
            'total_dapot_sites' => count($this->allowedSites),
            'processed_rows'    => $this->processedRows,
            'skipped_rows'      => $this->skippedRows,
            'skipped_non_dapot' => $this->skippedNonDapot,
            'metrics_upserted'  => $this->metricsUpserted,
            'regions_cached'    => count($this->regionCache),
            'sites_cached'      => count($this->siteCache),
        ];
    }
}
