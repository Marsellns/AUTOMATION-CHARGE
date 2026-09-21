<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Canonical, formula-free metrics derived from Infrastruktur source rows.
 *
 * The Excel workbooks contain volatile formulas (TODAY, LET, structured
 * references) whose cached values are not guaranteed to be available during
 * import.  Keep the raw cells in source_details for traceability, but derive
 * every value shown by the application from normalized dates and statuses.
 */
final class InfrastructureMetrics
{
    public const ACTIVE = 'Operational / Active';
    public const OFF_AIR = 'Off Air / Non-Operational';
    public const UNKNOWN_STATUS = 'Status Site Tidak Diisi';

    public static function leaseDurationLabel(object $row): ?string
    {
        $details = self::details($row);
        $start = self::dateValue(
            $row->start_date_baru
                ?? $details['new_period_awal']
                ?? $details['periode_awal_baru']
                ?? null
        );
        $end = self::dateValue(
            $row->end_date_baru
                ?? $details['new_period_akhir']
                ?? $details['periode_akhir_baru']
                ?? null
        );

        if ($start === null || $end === null || $end->lessThan($start)) {
            return null;
        }

        $interval = $start->diff($end);
        $months = ($interval->y * 12) + $interval->m;

        return $months < 12
            ? $months.' bulan'
            : (int) ceil($months / 12).' tahun';
    }

    public static function processStartDate(object $row): ?CarbonImmutable
    {
        $details = self::details($row);
        $keys = match (self::pipelineBucket($row)) {
            'Negosiasi' => ['tgl_negosiasi'],
            'BAK' => ['tanggal_bak', 'tgl_bak_baru'],
            'Pending PKS' => ['tgl_pksbak_ke_owner', 'tgl_pks_bak_ke_owner'],
            'PKS' => ['tgl_pks_ke_legal'],
            'Finance' => ['tgl_finance'],
            'Paid' => ['tgl_paid'],
            'Drop' => ['tgl_drop'],
            default => [],
        };

        if (self::pipelineBucket($row) === 'BAK' && isset($row->tgl_bak_baru)) {
            $normalized = self::dateValue($row->tgl_bak_baru);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        foreach ($keys as $key) {
            $date = self::dateValue($details[$key] ?? null);
            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    public static function processAgingDays(object $row, ?CarbonInterface $asOf = null): ?int
    {
        $start = self::processStartDate($row);
        if ($start === null) {
            return null;
        }

        $today = $asOf === null
            ? CarbonImmutable::today(config('app.timezone'))
            : CarbonImmutable::instance($asOf)->startOfDay();
        $days = (int) floor($start->startOfDay()->diffInDays($today, false));

        // A future process date is invalid source data, not a negative aging.
        return $days >= 0 ? $days : null;
    }

    public static function pipelineBucket(object $row): string
    {
        $details = self::details($row);
        $rawValue = self::firstFilled([
            $details['current_stage'] ?? null,
            $row->status_perpanjangan ?? null,
            $details['status_perpanjangan'] ?? null,
            $row->status_dokumen ?? null,
        ]);
        $raw = mb_strtolower($rawValue ?? '', 'UTF-8');

        if ($raw === '') return 'Tidak Diisi';
        if (str_contains($raw, 'paid') || str_contains($raw, 'bayar')) return 'Paid';
        if (str_contains($raw, 'drop') || str_contains($raw, 'dismantle') || str_contains($raw, 'relokasi')) return 'Drop';
        if (str_contains($raw, 'negos')) return 'Negosiasi';
        if (str_contains($raw, 'bak')) return 'BAK';
        if (str_contains($raw, 'pending') && str_contains($raw, 'pks')) return 'Pending PKS';
        if (str_contains($raw, 'pks') || str_contains($raw, 'legal')) return 'PKS';
        if (str_contains($raw, 'budget')) return 'Budget';
        if (str_contains($raw, 'finance') || str_contains($raw, 'financ')) return 'Finance';

        return 'Lainnya';
    }

    public static function geographyBucket(object $row): string
    {
        $details = self::details($row);

        // Kabupaten/kota is the requested concentration.  Region R12 is the
        // umbrella region for every site and must never become a chart bar.
        foreach (['kabupaten', 'city', 'kota', 'area', 'wilayah', 'region'] as $key) {
            $value = trim((string) ($details[$key] ?? ''));
            if ($value === '' || $value === '-') {
                continue;
            }
            if (preg_match('/^R?12\b|eastern\s+jabo(?:de)?tabek/i', $value)) {
                continue;
            }

            return $value;
        }

        return 'Tidak Diisi';
    }

    public static function operationalBucket(object $row): string
    {
        $details = self::details($row);
        $raw = mb_strtolower(trim((string) ($details['status'] ?? '')), 'UTF-8');

        if ($raw === '') {
            return self::UNKNOWN_STATUS;
        }
        if (preg_match('/off\s*air|non.?operational|non.?aktif|dismantle|relokasi|unlock|migrasi/u', $raw)) {
            return self::OFF_AIR;
        }
        if (preg_match('/on\s*air|\bactive\b|operational/u', $raw)) {
            return self::ACTIVE;
        }

        return self::UNKNOWN_STATUS;
    }

    public static function pksStatusLabel(object $row): string
    {
        return filled($row->no_pks_baru ?? null) || filled($row->no_pks_lama ?? null)
            ? 'Ada PKS'
            : 'Tanpa PKS';
    }

    /**
     * Nilai finansial bulanan yang sudah dinormalisasi dari kolom tabel atau
     * source_details workbook. PnL diturunkan dari Revenue - Cost bila kedua
     * komponen tersedia agar diagram tidak bergantung pada format/formula Excel.
     *
     * @return array{revenue: float, cost: float, pnl: float}
     */
    public static function monthlyFinancials(object $row, string $month): array
    {
        $details = self::details($row);
        $engMonth = $month === 'mei' ? 'may' : $month;
        $revenue = self::moneyValue(
            $row->{"revenue_{$month}_2026"}
                ?? $details["rev_{$month}_26"]
                ?? $details["rev_{$engMonth}_26"]
                ?? null
        );
        $cost = self::moneyValue(
            $row->{"cost_{$month}_2026"}
                ?? $details["cost_{$month}_26"]
                ?? $details["cost_{$engMonth}_26"]
                ?? null
        );
        $sourcePnl = self::moneyValue(
            $row->{"pnl_{$month}_2026"}
                ?? $details["pnl_{$month}_26"]
                ?? $details["pnl_{$engMonth}_26"]
                ?? null
        );

        return [
            'revenue' => $revenue ?? 0.0,
            'cost' => $cost ?? 0.0,
            'pnl' => $revenue !== null && $cost !== null
                ? $revenue - $cost
                : ($sourcePnl ?? 0.0),
        ];
    }

    public static function moneyValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '-' || str_starts_with($text, '=')) {
            return null;
        }

        $negative = str_starts_with($text, '-') || (str_contains($text, '(') && str_contains($text, ')'));
        $number = preg_replace('/[^0-9,.]/', '', $text);
        if ($number === '') {
            return null;
        }

        $commas = substr_count($number, ',');
        $dots = substr_count($number, '.');
        if ($commas > 0 && $dots > 0) {
            $lastComma = strrpos($number, ',');
            $lastDot = strrpos($number, '.');
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $decimalDigits = strlen($number) - max($lastComma, $lastDot) - 1;
            if ($decimalDigits >= 1 && $decimalDigits <= 2) {
                $thousandsSeparator = $decimalSeparator === ',' ? '.' : ',';
                $number = str_replace($thousandsSeparator, '', $number);
                $number = str_replace($decimalSeparator, '.', $number);
            } else {
                $number = str_replace([',', '.'], '', $number);
            }
        } elseif ($commas > 0 || $dots > 0) {
            $separator = $commas > 0 ? ',' : '.';
            $separatorCount = $commas + $dots;
            $lastSeparator = strrpos($number, $separator);
            $decimalDigits = strlen($number) - $lastSeparator - 1;
            if ($separatorCount === 1 && $decimalDigits >= 1 && $decimalDigits <= 2) {
                $number = str_replace($separator, '.', $number);
            } else {
                $number = str_replace($separator, '', $number);
            }
        }

        if (!is_numeric($number)) {
            return null;
        }

        $parsed = (float) $number;
        return $negative ? -abs($parsed) : $parsed;
    }

    public static function nextActionLabel(object $row): ?string
    {
        return match (self::pipelineBucket($row)) {
            'Negosiasi' => 'Tindak lanjuti proses negosiasi',
            'BAK' => 'Tindak lanjuti proses BAK',
            'Pending PKS', 'PKS' => 'Tindak lanjuti proses PKS',
            'Finance' => 'Tindak lanjuti proses Finance',
            'Budget' => 'Tindak lanjuti proses Budget',
            'Paid' => 'Monitoring pembayaran',
            'Drop' => 'Tidak diperlukan tindak lanjut',
            default => null,
        };
    }

    public static function priorityLabel(object $row, ?CarbonInterface $asOf = null): ?string
    {
        $aging = self::processAgingDays($row, $asOf);
        if ($aging === null) {
            return null;
        }

        return match (true) {
            $aging >= 90 => 'Urgent',
            $aging >= 60 => 'High',
            $aging >= 30 => 'Medium',
            default => 'Low',
        };
    }

    private static function details(object $row): array
    {
        $details = is_array($row->source_details ?? null) ? $row->source_details : [];
        $database = $details['database'] ?? null;
        $revenue = $details['database_revenue'] ?? null;

        // Import Combat terbaru mengarsipkan dua sheet workbook di bawah dua
        // key ini. Flatten untuk seluruh metrik turunan agar chart tetap dapat
        // membaca tanggal proses dan area tanpa bergantung pada bentuk JSON.
        if (is_array($database) || is_array($revenue)) {
            return array_replace(
                is_array($revenue) ? $revenue : [],
                is_array($database) ? $database : []
            );
        }

        return $details;
    }

    private static function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $text = trim((string) ($value ?? ''));
            if ($text !== '' && $text !== '-') {
                return $text;
            }
        }

        return null;
    }

    private static function dateValue(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }
        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial < 20000 || $serial > 80000) {
                return null;
            }

            return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($serial))->startOfDay();
        }

        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '-' || str_starts_with($text, '=') || preg_match('/^(NY|N\/A|NA)$/i', $text)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($text, config('app.timezone'))->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
