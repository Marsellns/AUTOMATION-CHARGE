<?php

namespace App\Services;

use App\Exports\AnomaliTagihanInbuildingExport;
use App\Exports\AnomaliTagihanPlnExport;
use App\Models\User;
use App\Notifications\ElectricityAnomalyNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ElectricityAnomalyNotificationService
{
    public function send(string $source, array $anomalies): bool
    {
        $anomalies = array_values(array_filter(
            $anomalies,
            fn (array $anomaly): bool => (float) ($anomaly['kenaikan_persen'] ?? 0) > 50
        ));

        if ($anomalies === []) {
            return false;
        }

        usort($anomalies, static function (array $left, array $right): int {
            return strcmp(
                serialize([$left['id'] ?? null, $left['site_id'] ?? null, $left['tahun'] ?? null, $left['bulan'] ?? null]),
                serialize([$right['id'] ?? null, $right['site_id'] ?? null, $right['tahun'] ?? null, $right['bulan'] ?? null])
            );
        });

        $recipient = config('mail.electricity_alert_to');
        $signature = hash('sha256', $source.'|'.json_encode($anomalies, JSON_THROW_ON_ERROR));
        $sentKey = 'electricity-alert:'.$signature;

        if (Cache::has($sentKey)) {
            Log::info('Notifikasi anomali listrik tidak dikirim ulang karena datanya sama.', [
                'source' => $source,
                'anomaly_count' => count($anomalies),
            ]);

            return false;
        }

        $this->notifyWebsite(
            $source,
            count($anomalies),
            str_starts_with(strtolower($source), 'centralized')
                ? route('electricity.centralized.anomali.index')
                : route('electricity.inbuilding.anomali.index'),
            str_starts_with(strtolower($source), 'centralized')
                ? route('electricity.centralized.anomali.export-excel')
                : route('electricity.inbuilding.anomali.export-excel')
        );
        Cache::put($sentKey, true, now()->addMinutes(16));

        if (! is_string($recipient) || trim($recipient) === '') {
            Log::warning('Email anomali listrik tidak dikirim karena penerima belum dikonfigurasi.', [
                'source' => $source,
                'anomaly_count' => count($anomalies),
            ]);

            return false;
        }

        $export = str_starts_with(strtolower($source), 'centralized')
            ? new AnomaliTagihanPlnExport
            : new AnomaliTagihanInbuildingExport;
        $filename = 'anomali_tagihan_'.str($source)->slug('_').'_'.now()->format('Ymd_His').'.xlsx';
        $attachment = Excel::raw($export, ExcelFormat::XLSX);

        try {
            Mail::raw(implode("\n", [
                'Ditemukan kenaikan tagihan listrik di atas 50%.',
                '',
                "Sumber data: {$source}",
                'Jumlah anomali: '.count($anomalies),
                '',
                'Rincian lengkap tersedia pada lampiran Excel.',
            ]), function ($message) use ($recipient, $source, $attachment, $filename): void {
                $message->to($recipient)
                    ->subject("Peringatan kenaikan tagihan listrik >50% ({$source})")
                    ->attachData($attachment, $filename, [
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
            });
        } catch (\Throwable $exception) {
            Log::error('Email anomali listrik gagal dikirim.', [
                'source' => $source,
                'recipient' => $recipient,
                'anomaly_count' => count($anomalies),
                'exception' => $exception,
            ]);

            return false;
        }

        return true;
    }

    public function notifyWebsite(string $source, int $anomalyCount, string $url, string $downloadUrl): void
    {
        User::query()
            ->where('account_status', 'approved')
            ->get()
            ->each(fn (User $user) => $user->notify(
                new ElectricityAnomalyNotification($source, $anomalyCount, $url, $downloadUrl)
            ));
    }
}
