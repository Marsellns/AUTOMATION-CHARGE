<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Atomic daily guard shared by scheduled website and email notifications.
 */
final class DailyNotificationGate
{
    /**
     * Reserve a notification slot for the current business day.
     *
     * The cache key is returned so a failed delivery can release its slot and
     * be retried later without allowing a successful delivery to be repeated.
     */
    public static function reserve(string $channel, string $topic): ?string
    {
        $now = CarbonImmutable::now(self::timezone());
        $key = self::key($channel, $topic, $now->toDateString());

        return Cache::add($key, true, $now->addDays(2)) ? $key : null;
    }

    public static function release(string $key): void
    {
        Cache::forget($key);
    }

    private static function key(string $channel, string $topic, string $date): string
    {
        $identity = mb_strtolower(trim($channel).'|'.trim($topic), 'UTF-8');

        return sprintf('daily-notification:%s:%s', $date, hash('sha256', $identity));
    }

    private static function timezone(): string
    {
        return (string) config('notifications.timezone', 'Asia/Jakarta');
    }
}
