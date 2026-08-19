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
    /**
     * Mapping 18 bulan ke heading keys.
     * Format: [bulan, tahun, revenue_key, cost_key]
     *
     * PENTING: Keys di sini harus match persis dengan output dari
     * `php artisan simawar:check-headings`. Jalankan command itu dulu
     * sebelum import penuh untuk verifikasi.
     *
     * Default assumption: Maatwebsite Str::slug($heading, '_') mengkonversi
     * "Rev Jan-25" → "rev_jan_25", "Cost Jan-25" → "cost_jan_25"
     */
    private array $monthColumns;

    /** Cache regions yang sudah di-firstOrCreate (kode => id) */
    private array $regionCache = [];

    /** Cache sites yang sudah di-updateOrCreate (site_id => id) */
    private array $siteCache = [];

    /** Counter untuk progress reporting */
    private int $processedRows = 0;
    private int $skippedRows = 0;
    private int $metricsUpserted = 0;

    public function __construct()
    {
        $this->monthColumns = $this->buildMonthColumns();
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

        // 1. Extract region kode dari 3 huruf pertama Site ID
        $regionKode = strtoupper(substr($siteId, 0, 3));

        // 2. FirstOrCreate region (cached)
        $regionId = $this->getOrCreateRegionId($regionKode);

        // 3. Clean site name
        $siteName = $this->cleanSiteName($row['site_name'] ?? null);

        // 4. UpdateOrCreate site (cached)
        $siteDbId = $this->getOrCreateSiteId($siteId, $siteName, $regionId);

        // 5. Loop 18 bulan: insert/update metrics
        foreach ($this->monthColumns as $monthDef) {
            [$bulan, $tahun, $revKey, $costKey] = $monthDef;

            $revenue = $this->parseNumeric($row[$revKey] ?? null);
            $cost = $this->parseNumeric($row[$costKey] ?? null);

            // Skip bulan yang tidak ada data (kedua kolom null/0)
            if ($revenue == 0 && $cost == 0) {
                continue;
            }

            // profit_loss tidak dikirim: dihitung otomatis oleh event saving
            // di model SiteMonthlyMetric (revenue - cost)
            SiteMonthlyMetric::updateOrCreate(
                [
                    'site_id' => $siteDbId,
                    'bulan'   => $bulan,
                    'tahun'   => $tahun,
                ],
                [
                    'revenue' => $revenue,
                    'cost'    => $cost,
                ]
            );

            $this->metricsUpserted++;
        }

        $this->processedRows++;

        // Log progress setiap 1000 baris
        if ($this->processedRows % 1000 === 0) {
            Log::info("SimawarPnLImport progress: {$this->processedRows} sites processed, {$this->metricsUpserted} metrics upserted");
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
            ];
        }, $months);
    }

    /**
     * Get or create region ID (cached untuk avoid repeated DB queries).
     */
    private function getOrCreateRegionId(string $kode): int
    {
        if (!isset($this->regionCache[$kode])) {
            $region = Region::firstOrCreate(
                ['kode' => $kode],
                ['nama' => null] // Nama diisi manual nanti
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
            'processed_rows'   => $this->processedRows,
            'skipped_rows'     => $this->skippedRows,
            'metrics_upserted' => $this->metricsUpserted,
            'regions_cached'   => count($this->regionCache),
            'sites_cached'     => count($this->siteCache),
        ];
    }
}
