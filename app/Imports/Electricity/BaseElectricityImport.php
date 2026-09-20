<?php

namespace App\Imports\Electricity;

use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Base import untuk modul Electricity.
 *
 * Pola: upsert idempotent — data dengan key unik yang sama akan diupdate,
 * data baru akan diinsert. Menggunakan DB::upsert() per chunk.
 */
abstract class BaseElectricityImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected int $inserted = 0;
    protected int $updated = 0;
    protected int $skipped = 0;
    protected int $errors = 0;

    /** Nama tabel tujuan. */
    abstract protected function table(): string;

    /** Kolom unique key untuk upsert (array). */
    abstract protected function uniqueKey(): array;

    /**
     * Mapping satu baris Excel (key = slug heading) ke array kolom DB.
     * Return null untuk skip baris (misal tanpa ID Pelanggan/Site ID).
     */
    abstract protected function mapRow(Collection $row): ?array;

    public function headingRow(): int
    {
        return 1;
    }

    public function chunkSize(): int
    {
        return 500;
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

        if (!empty($batch)) {
            try {
                foreach (array_chunk($batch, 200) as $chunk) {
                    DB::table($this->table())->upsert($chunk, $this->uniqueKey(), array_keys($chunk[0]));
                    $this->inserted += count($chunk);
                }
            } catch (Throwable $e) {
                $this->errors += count($batch);
                throw $e;
            }
        }
    }

    public function getStats(): array
    {
        return [
            'inserted' => $this->inserted,
            'updated'  => $this->updated,
            'skipped'  => $this->skipped,
            'errors'   => $this->errors,
        ];
    }

    // ── Helper parsing ──────────────────────────────────────────────────

    protected function cleanText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return ($s === '' || $s === '-') ? null : $s;
    }

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

        $sanitized = preg_replace('/[^0-9,\.\-]/u', '', $s);
        if ($sanitized === '' || $sanitized === '-' || $sanitized === '-.' || $sanitized === '-,') {
            return null;
        }

        $negative = str_starts_with($sanitized, '-');
        $sanitized = ltrim($sanitized, '-');

        if (str_contains($sanitized, ',') && str_contains($sanitized, '.')) {
            $lastComma = strrpos($sanitized, ',');
            $lastDot = strrpos($sanitized, '.');

            if ($lastComma > $lastDot) {
                $sanitized = str_replace('.', '', $sanitized);
                $sanitized = str_replace(',', '.', $sanitized);
            } else {
                $sanitized = str_replace(',', '', $sanitized);
            }
        } elseif (str_contains($sanitized, ',')) {
            $commaPos = strrpos($sanitized, ',');
            $afterComma = substr($sanitized, $commaPos + 1);

            if (strlen($afterComma) === 3 && preg_match('/^\d{3}$/', $afterComma)) {
                $sanitized = str_replace(',', '', $sanitized);
            } else {
                $sanitized = str_replace(',', '.', $sanitized);
            }
        } elseif (str_contains($sanitized, '.')) {
            $dotPos = strrpos($sanitized, '.');
            $afterDot = substr($sanitized, $dotPos + 1);

            if (strlen($afterDot) === 3 && preg_match('/^\d{3}$/', $afterDot)) {
                $sanitized = str_replace('.', '', $sanitized);
            }
        }

        $normalized = $negative ? '-' . $sanitized : $sanitized;

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    protected function parseInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $s = trim((string) $value);
        if ($s === '' || $s === '-') {
            return null;
        }
        $cleaned = preg_replace('/[^0-9\-]/', '', $s);
        return ($cleaned === '' || $cleaned === '-') ? null : (int) $cleaned;
    }

    protected function parseDate(mixed $value, bool $withTime = false): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
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