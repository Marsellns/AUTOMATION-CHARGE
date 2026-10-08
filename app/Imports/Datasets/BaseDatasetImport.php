<?php

namespace App\Imports\Datasets;

use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Base import untuk file-file dataset Simawar.
 *
 * Import CLI memakai snapshot: command menghapus isi tabel sebelum bulk insert.
 * Upload dari halaman modul memakai mode incremental. Header default di baris 2;
 * modul dengan header baris 1 mengubah headingRow().
 */
abstract class BaseDatasetImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected int $inserted = 0;
    protected int $skipped = 0;
    private bool $incremental = false;

    /** Nama tabel tujuan. */
    abstract protected function table(): string;

    /**
     * Mapping satu baris Excel (key = slug heading) ke array kolom DB.
     * Return null untuk skip baris (misal tanpa Site ID).
     */
    abstract protected function mapRow(Collection $row): ?array;

    /** Kolom pembeda satu entri saat upload dari halaman modul. */
    protected function uploadKey(): array
    {
        return ['site_code'];
    }

    public function incremental(): static
    {
        $this->incremental = true;
        return $this;
    }

    /** Existing attachments must survive spreadsheet metadata updates. */
    protected function preserveWhenEmpty(): array
    {
        return [];
    }

    public function headingRow(): int
    {
        return 2;
    }

    protected function sourceDetails(Collection $row): array
    {
        return $row->toArray();
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function collection(Collection $rows): void
    {
        $batch = [];

        foreach ($rows as $row) {
            $mapped = $this->mapRow($row);

            if ($mapped === null) {
                $this->skipped++;
                continue;
            }

            $mapped['created_at'] = now();
            $mapped['updated_at'] = now();
            $batch[] = $mapped;
        }

        if ($this->incremental) {
            $hasSoftDeletes = in_array($this->table(), [
                'sewa_lahan_renewals', 'jaknet_contracts', 'data_site_unlocks', 'bapss',
            ], true);
            foreach ($batch as $record) {
                $preserveWhenEmpty = $this->preserveWhenEmpty();
                $key = array_intersect_key($record, array_flip($this->uploadKey()));
                if ($hasSoftDeletes) {
                    $record['deleted_at'] = null;
                }
                DB::table($this->table())->updateOrInsert($key, static function (bool $exists) use ($record, $preserveWhenEmpty): array {
                    if ($exists) {
                        unset($record['created_at']);
                        foreach ($preserveWhenEmpty as $column) {
                            if (($record[$column] ?? null) === null || $record[$column] === '') {
                                unset($record[$column]);
                            }
                        }
                    }
                    return $record;
                });
            }
        } else {
            foreach (array_chunk($batch, 500) as $chunk) {
                DB::table($this->table())->insert($chunk);
            }
        }

        $this->inserted += count($batch);
    }

    public function getStats(): array
    {
        return [
            'inserted' => $this->inserted,
            'skipped'  => $this->skipped,
        ];
    }

    // ── Helper parsing ──────────────────────────────────────────────────

    /**
     * Bersihkan teks: trim, kosong / "-" → null. Whitespace internal
     * (misal sisa tombol "Edit\n Hapus") dirapatkan jadi satu spasi.
     */
    protected function cleanText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return ($s === '' || $s === '-') ? null : $s;
    }

    /**
     * Parse nominal uang dari cell Excel.
     * - Numeric → dipakai langsung.
     * - String format Indonesia ("Rp 61.111.111", "23.932.204",
     *   "1.543.712 (Profit)") → buang semua kecuali digit & minus.
     */
    protected function parseMoney(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $s = trim((string) $value);

        if ($s === '' || $s === '-') {
            return null;
        }

        $cleaned = preg_replace('/[^0-9\-]/', '', $s);

        return ($cleaned === '' || $cleaned === '-') ? null : (float) $cleaned;
    }

    /**
     * Parse tahun dari cell numeric/string.
     */
    protected function parseYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $n = (int) $value;

        return ($n >= 1900 && $n <= 2100) ? $n : null;
    }

    /**
     * Parse tanggal dari cell Excel. Sumber data campuran:
     * - serial Excel (46246) → konversi via PhpSpreadsheet\Shared\Date
     * - string: "2026-04-17 03:52:57", "01-09-2001", "30 Dec 2024"
     * - "0000-00-00" / "-" / kosong → null
     */
    protected function parseDate(mixed $value, bool $withTime = false): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
            // Rentang serial yang masuk akal (~1954–2075).
            if ($serial < 20000 || $serial > 80000) {
                return null;
            }

            $dt = ExcelDate::excelToDateTimeObject($serial);

            return $withTime ? $dt->format('Y-m-d H:i:s') : $dt->format('Y-m-d');
        }

        $s = trim((string) $value);

        if ($s === '' || str_starts_with($s, '0000')) {
            return null;
        }

        $formats = $withTime
            ? ['Y-m-d H:i:s', 'd-m-Y H:i:s', 'Y-m-d']
            : ['Y-m-d', 'd-m-Y', 'd/m/Y', 'j M Y', 'd M Y', 'j-M-Y'];

        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $s);

            if ($dt !== false) {
                return $withTime ? $dt->format('Y-m-d H:i:s') : $dt->format('Y-m-d');
            }
        }

        try {
            return (new DateTime($s))->format($withTime ? 'Y-m-d H:i:s' : 'Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
