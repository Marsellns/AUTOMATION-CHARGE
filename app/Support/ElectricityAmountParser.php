<?php

namespace App\Support;

final class ElectricityAmountParser
{
    /**
     * Parse nominal Rupiah dari dataset Electricity.
     *
     * Beberapa cell sumber berisi pemisah ribuan Indonesia, misalnya
     * "467.216". Excel dapat membacanya sebagai angka desimal 467.216,
     * sehingga nominal perlu dikembalikan menjadi 467216 sebelum disimpan.
     */
    public static function parse(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $number = (float) $value;

            if (! is_finite($number)) {
                return null;
            }

            if ($number !== 0.0 && abs($number) < 10000 && floor($number) !== $number) {
                return round($number * 1000);
            }

            return $number;
        }

        $raw = trim((string) $value);
        if ($raw === '' || $raw === '-') {
            return null;
        }

        // PhpSpreadsheet tidak selalu konsisten: cell seperti 20.2 dapat
        // dikembalikan sebagai float atau numeric string, tergantung style
        // workbook. Untuk kolom nominal Rupiah, keduanya berarti 20.200.
        if (is_numeric($raw)) {
            $number = (float) $raw;

            if ($number !== 0.0 && abs($number) < 10000 && floor($number) !== $number) {
                return round($number * 1000);
            }

            return $number;
        }

        $sanitized = preg_replace('/[^0-9,.\-]/u', '', $raw);
        if ($sanitized === null || in_array($sanitized, ['', '-', '-.', '-,'], true)) {
            return null;
        }

        $negative = str_starts_with($sanitized, '-');
        $sanitized = ltrim($sanitized, '-');
        $commaCount = substr_count($sanitized, ',');
        $dotCount = substr_count($sanitized, '.');

        if ($commaCount > 0 && $dotCount > 0) {
            if (strrpos($sanitized, ',') > strrpos($sanitized, '.')) {
                $sanitized = str_replace('.', '', $sanitized);
                $sanitized = str_replace(',', '.', $sanitized);
            } else {
                $sanitized = str_replace(',', '', $sanitized);
            }
        } elseif ($commaCount > 0) {
            $fraction = substr($sanitized, (int) strrpos($sanitized, ',') + 1);
            $sanitized = $commaCount > 1 || strlen($fraction) === 3
                ? str_replace(',', '', $sanitized)
                : str_replace(',', '.', $sanitized);
        } elseif ($dotCount > 0) {
            $fraction = substr($sanitized, (int) strrpos($sanitized, '.') + 1);
            if ($dotCount > 1 || strlen($fraction) === 3) {
                $sanitized = str_replace('.', '', $sanitized);
            }
        }

        $normalized = ($negative ? '-' : '').$sanitized;

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    public static function parseOrZero(mixed $value): float
    {
        return self::parse($value) ?? 0.0;
    }
}
