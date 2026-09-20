<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ElectricityAnomalyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $source,
        private readonly int $anomalyCount,
        private readonly string $url,
        private readonly string $downloadUrl,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Peringatan anomali tagihan listrik',
            'message' => sprintf(
                'Ditemukan %d anomali kenaikan tagihan di atas 50%% pada sumber %s.',
                $this->anomalyCount,
                $this->source
            ),
            'source' => $this->source,
            'anomaly_count' => $this->anomalyCount,
            'url' => $this->url,
            'download_url' => $this->downloadUrl,
        ];
    }
}
