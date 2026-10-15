<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ElectricityAnomalyNotification;
use App\Notifications\InfrastructureSiteAlertNotification;
use App\Notifications\SiteLossNotification;
use App\Support\DailyNotificationGate;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/** Give newly approved users the current scheduled operational alerts. */
class BackfillOperationalNotifications
{
    private const TYPES = [
        ElectricityAnomalyNotification::class,
        InfrastructureSiteAlertNotification::class,
        SiteLossNotification::class,
    ];

    public function forApprovedUser(User $user): int
    {
        $slot = DailyNotificationGate::currentSlotStart();
        if ($user->account_status !== 'approved' || $slot === null) {
            return 0;
        }

        $slotStart = $slot->setTimezone((string) config('app.timezone', 'UTC'))->toDateTimeString();
        $existing = $user->notifications()
            ->whereIn('type', self::TYPES)
            ->where('created_at', '>=', $slotStart)
            ->get()
            ->mapWithKeys(fn (DatabaseNotification $notification): array => [$this->fingerprint($notification) => true])
            ->all();

        $sent = 0;
        $sources = DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', '!=', $user->getKey())
            ->whereIn('type', self::TYPES)
            ->where('created_at', '>=', $slotStart)
            ->latest()
            ->get();

        foreach ($sources as $source) {
            $fingerprint = $this->fingerprint($source);
            if (isset($existing[$fingerprint])) {
                continue;
            }

            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $source->type,
                'data' => $source->data,
            ]);
            $existing[$fingerprint] = true;
            $sent++;
        }

        return $sent;
    }

    private function fingerprint(DatabaseNotification $notification): string
    {
        return hash('sha256', $notification->type."\0".json_encode($notification->data, JSON_THROW_ON_ERROR));
    }
}
