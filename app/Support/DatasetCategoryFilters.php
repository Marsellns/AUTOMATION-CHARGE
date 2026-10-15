<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DatasetCategoryFilters
{
    /** Column names come only from fixed dataset definitions in application code. */
    public static function label(string $column): string
    {
        return "COALESCE(NULLIF(TRIM(`{$column}`), ''), 'Belum diisi')";
    }

    public static function apply(Builder $query, Request $request, array $columns): Builder
    {
        $values = $request->validate(array_fill_keys($columns, ['nullable', 'string', 'max:255']));
        foreach ($columns as $column) {
            $value = trim((string) ($values[$column] ?? ''));
            if ($value === '' || ($column === 'nop' && strcasecmp($value, 'all') === 0)) {
                continue;
            }
            // Preserve links from the existing main dashboard.
            if ($column === 'site_owner' && strcasecmp($value, 'Belum teridentifikasi') === 0) {
                $value = 'Belum diisi';
            }
            $query->whereRaw('UPPER('.self::label($column).') = ?', [mb_strtoupper($value)]);
        }

        return $query;
    }
}
