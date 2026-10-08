<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Atomic guard shared by website and email notifications for each daily slot.
 */
final class DailyNotificationGate
{
    /**
     * Reserve the latest due morning/evening slot in the business timezone.
     *
     * The cache key is returned so a failed delivery can release its slot and
     * be retried later without allowing a successful delivery to be repeated.
     */
    public static function reserve(string $channel, string $topic): ?string
    {
        $now = CarbonImmutable::now(self::timezone());
        $times = [
            (string) config('notifications.daily_at', '08:00'),
            (string) config('notifications.evening_at', '17:00'),
        ];
        sort($times);
        $dueTimes = array_values(array_filter($times, fn (string $time): bool => $time <= $now->format('H:i')));
        // An early import must not consume the upcoming morning notification.
        if ($dueTimes === []) {
            return null;
        }
        $slot = $dueTimes[count($dueTimes) - 1];
        $key = self::key($channel, $topic, $now->toDateString().'@'.$slot);

        return Cache::add($key, true, $now->addDays(2)) ? $key : null;
    }

    public static function release(string $key): void
    {
        Cache::forget($key);
    }

    private static function key(string $channel, string $topic, string $date): string
    {
        $identity = mb_strtolower(trim($channel).'|'.trim($topic), 'UTF-8');

        return sprintf('scheduled-notification:%s:%s', $date, hash('sha256', $identity));
    }

    private static function timezone(): string
    {
        return (string) config('notifications.timezone', 'Asia/Jakarta');
    }
}
