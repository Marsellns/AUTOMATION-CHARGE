<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RecurringTagihanSummary
{
    public static function siteCode(string $value): string
    {
        return strtoupper(trim($value));
    }

    public static function money(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }
        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/', $value)) {
            return $value;
        }
        $value = preg_replace('/[^0-9,.-]/', '', $value);
        if (preg_match('/^-?\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?$/', $value)) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }
        if (preg_match('/^-?\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?$/', $value)) {
            return str_replace(',', '', $value);
        }
        throw new \InvalidArgumentException('Nominal tagihan tidak valid: '.$value);
    }

    /** Compress only consecutive integer labels: 1, 3, 5 must never become 1–5. */
    public static function labels(Collection $values): ?string
    {
        $values = $values->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))->unique()->sort(SORT_NATURAL)->values();
        if ($values->isEmpty()) {
            return null;
        }
        $labels = [];
        for ($i = 0; $i < $values->count(); $i++) {
            $start = $end = $values[$i];
            while (ctype_digit($end) && isset($values[$i + 1]) && ctype_digit($values[$i + 1])
                && (int) $values[$i + 1] === (int) $end + 1) {
                $end = $values[++$i];
            }
            $labels[] = $start === $end ? $start : $start.'–'.$end;
        }

        return implode(', ', $labels);
    }

    public static function attributes(Collection $rows, ?string $amount): array
    {
        $result = ['term_count' => $rows->count(), 'amount' => $amount];
        foreach (['site_name', 'tp', 'contract_type', 'batch_name'] as $field) {
            $values = $rows->pluck($field)->filter(fn ($value) => $value !== null && $value !== '')->unique()->sort(SORT_NATURAL);
            $result[$field] = $values->isEmpty() ? null : $values->implode(' / ');
        }
        foreach (['termin', 'periode_ke'] as $field) {
            $result[$field] = self::labels($rows->pluck($field));
        }
        $result['termin_start'] = $rows->pluck('termin_start')->filter()->min();
        $result['termin_end'] = $rows->pluck('termin_end')->filter()->max();

        return $result;
    }

    /** SQL SUM keeps currency exact, including cents and totals above float precision. */
    public static function refresh(array $siteIds): void
    {
        foreach (array_chunk(array_unique($siteIds), 100) as $ids) {
            $groups = DB::table('recurring_tagihan_ipas_terms')->whereIn('site_id', $ids)->get()->groupBy('site_id');
            $totals = DB::table('recurring_tagihan_ipas_terms')->whereIn('site_id', $ids)
                ->selectRaw('site_id, SUM(amount) AS total')->groupBy('site_id')->pluck('total', 'site_id');
            foreach ($groups as $siteId => $rows) {
                DB::table('recurring_tagihan_ipas')->where('id', $siteId)
                    ->update(self::attributes($rows, $totals[$siteId] === null ? null : (string) $totals[$siteId]) + ['updated_at' => now()]);
            }
        }
    }
}
