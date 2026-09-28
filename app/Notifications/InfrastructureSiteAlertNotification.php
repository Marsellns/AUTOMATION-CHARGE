<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InfrastructureSiteAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, int>  $statusCounts
     */
    public function __construct(
        private readonly string $category,
        private readonly string $label,
        private readonly int $warningCount,
        private readonly int $totalCount,
        private readonly array $statusCounts,
        private readonly string $url,
        private readonly string $notificationDate,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Peringatan '.$this->label,
            'message' => sprintf(
                '%d dari %d site memerlukan perhatian masa sewa: %d sudah berakhir, %d berakhir ≤90 hari, %d berakhir 91–180 hari, dan %d belum memiliki tanggal akhir.',
                $this->warningCount,
                $this->totalCount,
                $this->statusCounts['expired'] ?? 0,
                $this->statusCounts['within_90'] ?? 0,
                $this->statusCounts['within_180'] ?? 0,
                $this->statusCounts['unknown'] ?? 0,
            ),
            'category' => $this->category,
            'site_count' => $this->warningCount,
            'total_site_count' => $this->totalCount,
            'status_counts' => $this->statusCounts,
            'notification_date' => $this->notificationDate,
            'url' => $this->url,
        ];
    }
}
