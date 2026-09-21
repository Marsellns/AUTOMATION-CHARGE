<?php

namespace App\Imports\Datasets;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Mengimpor workbook Combat sebagai snapshot kanonis satu baris per Site ID.
 *
 * DATABASE menjadi sumber atribut kontrak/site, sedangkan DATABASE_REVENUE
 * melengkapi tahun serta revenue/cost/PnL. Baris revenue lintas tahun untuk
 * Site ID yang sama dikonsolidasikan ke baris dengan tahun terbaru.
 */
class CombatWorkbookImport
{
    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun'];

    private int $inserted = 0;
    private int $skipped = 0;

    public function import(string $path): void
    {
        $spreadsheet = IOFactory::load($path);
        $masterSheet = $spreadsheet->getSheetByName('DATABASE') ?? $spreadsheet->getActiveSheet();
        $revenueSheet = $spreadsheet->getSheetByName('DATABASE_REVENUE');

        $masterRows = $this->readRows($masterSheet);
        $revenueRows = $revenueSheet ? $this->readRows($revenueSheet) : [];
        $records = $this->consolidate($masterRows, $revenueRows);
        $timestamp = now();

        foreach ($records as &$record) {
            $record['created_at'] = $timestamp;
            $record['updated_at'] = $timestamp;
        }
        unset($record);

        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('combat_sites')->insert($chunk);
        }

        $this->inserted = count($records);
    }

    /**
     * @param array<int, array<string, mixed>> $masterRows
     * @param array<int, array<string, mixed>> $revenueRows
     * @return array<int, array<string, mixed>>
     */
    public function consolidate(array $masterRows, array $revenueRows): array
    {
        $mapper = new CombatSiteImport();
        $masterBySite = [];
        $revenueBySite = [];

        foreach ($masterRows as $row) {
            $siteCode = $this->siteCode($row);
            if ($siteCode === null) {
                $this->skipped++;
                continue;
            }
            $masterBySite[$siteCode] = $row;
        }

        foreach ($revenueRows as $row) {
            $siteCode = $this->siteCode($row);
            if ($siteCode === null) {
                $this->skipped++;
                continue;
            }

            $current = $revenueBySite[$siteCode] ?? null;
            if ($current === null || $this->sourceYear($row) >= $this->sourceYear($current)) {
                $revenueBySite[$siteCode] = $row;
            }
        }

        $siteCodes = array_values(array_unique(array_merge(array_keys($masterBySite), array_keys($revenueBySite))));
        sort($siteCodes, SORT_NATURAL | SORT_FLAG_CASE);
        $records = [];

        foreach ($siteCodes as $siteCode) {
            $master = $masterBySite[$siteCode] ?? null;
            $revenue = $revenueBySite[$siteCode] ?? null;
            $mapped = $mapper->mapSourceRow($master ?? $revenue ?? []);

            if ($mapped === null) {
                $this->skipped++;
                continue;
            }

            if ($revenue !== null) {
                $revenueMapped = $mapper->mapSourceRow($revenue);
                if ($revenueMapped !== null) {
                    if ($revenueMapped['tahun_justi_dirnet'] !== null) {
                        $mapped['tahun_justi_dirnet'] = $revenueMapped['tahun_justi_dirnet'];
                    }

                    foreach (self::MONTHS as $month) {
                        foreach (['revenue', 'cost', 'pnl'] as $metric) {
                            $column = "{$metric}_{$month}_2026";
                            if ($revenueMapped[$column] !== null) {
                                $mapped[$column] = $revenueMapped[$column];
                            }
                        }
                    }
                }
            }

            $this->reconcileIndonesianThousands($mapped);
            $mapped['source_details'] = json_encode([
                'database' => $master,
                'database_revenue' => $revenue,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $records[] = $mapped;
        }

        return $records;
    }

    public function getStats(): array
    {
        return ['inserted' => $this->inserted, 'skipped' => $this->skipped];
    }

    /** @return array<int, array<string, mixed>> */
    private function readRows(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        if ($highestRow < 1 || $highestColumn < 1) {
            return [];
        }

        $headers = [];
        for ($column = 1; $column <= $highestColumn; $column++) {
            $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column).'1');
            $headers[$column] = Str::slug(trim((string) $this->cellValue($cell)), '_');
        }

        $rows = [];
        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $row = [];
            foreach ($headers as $column => $header) {
                if ($header === '') {
                    continue;
                }

                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$rowNumber);
                $value = $this->cellValue($cell);
                // Header ganda kadang ada pada ekspor; pertahankan nilai
                // non-kosong pertama agar tidak tertimpa kolom kosong.
                if (!array_key_exists($header, $row) || $row[$header] === null || $row[$header] === '') {
                    $row[$header] = $value;
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function cellValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): mixed
    {
        if ($cell->getDataType() !== DataType::TYPE_FORMULA) {
            return $cell->getValue();
        }

        // Workbook menyimpan hasil kalkulasi terakhir dari Excel. Mengambil
        // cache ini menghindari evaluasi ulang 900+ formula dan tetap dapat
        // membaca satu formula _xlfn.NUMBERVALUE yang tidak didukung penuh.
        $cached = $cell->getOldCalculatedValue();
        if ($cached !== null && $cached !== '') {
            return $cached;
        }

        $formula = (string) $cell->getValue();
        if (preg_match('/NUMBERVALUE\("([^"]+)"/i', $formula, $match)) {
            return $match[1];
        }

        try {
            return $cell->getCalculatedValue();
        } catch (\Throwable) {
            return null;
        }
    }

    private function siteCode(array $row): ?string
    {
        $siteCode = strtoupper(trim((string) ($row['site_id'] ?? '')));
        return $siteCode === '' ? null : $siteCode;
    }

    private function sourceYear(array $row): int
    {
        $year = (int) ($row['tahun_justi_dirnet'] ?? 0);
        return $year >= 1900 && $year <= 2100 ? $year : 0;
    }

    /**
     * Excel tertentu membaca format Indonesia 302.699 sebagai 302.699,
     * padahal Cost/PnL membuktikan nilainya 302.699 rupiah. Koreksi hanya saat
     * identitas Revenue - Cost = PnL tepat setelah revenue dikali 1.000.
     */
    private function reconcileIndonesianThousands(array &$record): void
    {
        foreach (self::MONTHS as $month) {
            $revenueKey = "revenue_{$month}_2026";
            $costKey = "cost_{$month}_2026";
            $pnlKey = "pnl_{$month}_2026";
            $revenue = $record[$revenueKey] ?? null;
            $cost = $record[$costKey] ?? null;
            $pnl = $record[$pnlKey] ?? null;

            if ($revenue === null || $cost === null || $pnl === null) {
                continue;
            }

            $normalDifference = abs(((float) $revenue - (float) $cost) - (float) $pnl);
            $scaledDifference = abs(((float) $revenue * 1000 - (float) $cost) - (float) $pnl);
            if ($normalDifference > 0.01 && $scaledDifference <= 0.01) {
                $record[$revenueKey] = (float) $revenue * 1000;
            }
        }
    }
}
