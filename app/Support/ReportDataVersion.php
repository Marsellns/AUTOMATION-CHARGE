<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class ReportDataVersion
{
    private const KEY = 'reports.operational-version.v1';

    public static function current(): string
    {
        return Cache::rememberForever(self::KEY, fn () => (string) Str::uuid());
    }

    public static function changed(): void
    {
        Cache::forever(self::KEY, (string) Str::uuid());
    }
}
