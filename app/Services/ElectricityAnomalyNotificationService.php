<?php

namespace App\Services;

use App\Exports\AnomaliTagihanInbuildingExport;
use App\Exports\AnomaliTagihanPlnExport;
use App\Models\User;
use App\Notifications\ElectricityAnomalyNotification;
use App\Support\DailyNotificationGate;
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

        $isCentralized = str_starts_with(strtolower($source), 'centralized');
        $dataUrl = route($isCentralized
            ? 'electricity.centralized.anomali.index'
            : 'electricity.inbuilding.anomali.index');
        $recipient = config('mail.electricity_alert_to');
        $topic = 'electricity-'.mb_strtolower(trim($source), 'UTF-8');
        $delivered = false;
        $websiteClaim = DailyNotificationGate::reserve('website', $topic);

        if ($websiteClaim !== null) {
            try {
                $this->notifyWebsite(
                    $source,
                    count($anomalies),
                    $dataUrl,
                    $isCentralized
                        ? route('electricity.centralized.anomali.export-excel')
                        : route('electricity.inbuilding.anomali.export-excel')
                );
                $delivered = true;
            } catch (\Throwable $exception) {
                DailyNotificationGate::release($websiteClaim);
                Log::error('Notifikasi website anomali listrik gagal dikirim.', [
                    'source' => $source,
                    'anomaly_count' => count($anomalies),
                    'exception' => $exception,
                ]);
            }
        } else {
            Log::info('Notifikasi website anomali listrik tidak dikirim ulang pada slot jadwal yang sama.', [
                'source' => $source,
                'anomaly_count' => count($anomalies),
            ]);
        }

        if (! is_string($recipient) || trim($recipient) === '') {
            Log::warning('Email anomali listrik tidak dikirim karena penerima belum dikonfigurasi.', [
                'source' => $source,
                'anomaly_count' => count($anomalies),
            ]);

            return $delivered;
        }

        $emailClaim = DailyNotificationGate::reserve('email', $topic);
        if ($emailClaim === null) {
            Log::info('Email anomali listrik tidak dikirim ulang pada slot jadwal yang sama.', [
                'source' => $source,
                'anomaly_count' => count($anomalies),
            ]);

            return $delivered;
        }

        $export = $isCentralized
            ? new AnomaliTagihanPlnExport
            : new AnomaliTagihanInbuildingExport;
        $filename = 'anomali_tagihan_'.str($source)->slug('_').'_'.now()->format('Ymd_His').'.xlsx';
        try {
            $attachment = Excel::raw($export, ExcelFormat::XLSX);
            Mail::raw(implode("\n", [
                'Ditemukan kenaikan tagihan listrik di atas 50%.',
                '',
                "Sumber data: {$source}",
                'Cakupan: seluruh periode yang tersedia pada data anomali.',
                'Jumlah anomali: '.count($anomalies),
                '',
                'Rincian lengkap tersedia pada lampiran Excel.',
                'Data terkait: '.$dataUrl,
            ]), function ($message) use ($recipient, $source, $attachment, $filename): void {
                $message->to(trim($recipient))
                    ->subject("Peringatan kenaikan tagihan listrik >50% ({$source})")
                    ->attachData($attachment, $filename, [
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
            });
        } catch (\Throwable $exception) {
            DailyNotificationGate::release($emailClaim);
            Log::error('Email anomali listrik gagal dikirim.', [
                'source' => $source,
                'recipient' => $recipient,
                'anomaly_count' => count($anomalies),
                'exception' => $exception,
            ]);

            return $delivered;
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
