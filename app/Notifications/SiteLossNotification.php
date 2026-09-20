<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SiteLossNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $siteCount,
        private readonly int $month,
        private readonly int $year,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Peringatan site Loss',
            'message' => sprintf(
                'Masih terdapat %d site dengan status Loss pada periode %02d/%d.',
                $this->siteCount,
                $this->month,
                $this->year
            ),
            'site_count' => $this->siteCount,
            'month' => $this->month,
            'year' => $this->year,
            'url' => route('notifications.site-loss', [
                'bulan' => $this->month,
                'tahun' => $this->year,
            ]),
            'download_url' => route('notifications.site-loss.export', [
                'bulan' => $this->month,
                'tahun' => $this->year,
            ]),
        ];
    }
}
