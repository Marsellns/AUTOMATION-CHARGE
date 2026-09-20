<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Klasifikasi masa sewa yang sama untuk seluruh dashboard Infrastruktur.
 *
 * Sumber Sewa Lahan tidak menyediakan kolom status, sedangkan Combat
 * menyimpan formula Excel yang dapat menjadi kedaluwarsa. Karena itu status
 * selalu diturunkan dari tanggal akhir kontrak yang tersimpan di snapshot.
 */
final class LeaseStatus
{
    public const NO_END_DATE = 'Belum ada tanggal akhir';
    public const EXPIRED = 'Sudah berakhir';
    public const WITHIN_90_DAYS = 'Berakhir ≤90 hari';
    public const WITHIN_180_DAYS = 'Berakhir ≤180 hari';
    public const SAFE = 'Aman (>180 hari)';

    public static function fromEndDate(?CarbonInterface $endDate, ?CarbonInterface $today = null): string
    {
        if ($endDate === null) {
            return self::NO_END_DATE;
        }

        $today ??= now();
        $daysRemaining = $today->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay(), false);

        return match (true) {
            $daysRemaining < 0 => self::EXPIRED,
            $daysRemaining <= 90 => self::WITHIN_90_DAYS,
            $daysRemaining <= 180 => self::WITHIN_180_DAYS,
            default => self::SAFE,
        };
    }

    /** @return list<string> */
    public static function labels(): array
    {
        return [self::NO_END_DATE, self::EXPIRED, self::WITHIN_90_DAYS, self::WITHIN_180_DAYS, self::SAFE];
    }
}
