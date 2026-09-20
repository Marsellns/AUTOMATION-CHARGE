<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class CityClassifier
{
    public static function expression(string $column): string
    {
        return "TRIM(REPLACE(REPLACE(REPLACE(UPPER({$column}), 'KABUPATEN ', ''), 'KOTA ', ''), 'KAB. ', ''))";
    }

    public static function normalize(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/^(KABUPATEN|KOTA|KAB\.)\s+/u', '', $value);

        return trim($value);
    }

    public static function options(EloquentBuilder|QueryBuilder $query, string $column = 'city')
    {
        $expression = self::expression($column);

        return $query
            ->whereNotNull($column)
            ->whereRaw("TRIM({$column}) <> ''")
            ->selectRaw("{$expression} AS city")
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }
}
