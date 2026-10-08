<?php

namespace App\Console\Commands;

use App\Exports\SiteLossExport;
use App\Models\AnomaliTagihanInbuilding;
use App\Models\AnomaliTagihanPln;
use App\Models\SiteMonthlyMetric;
use App\Models\User;
use App\Notifications\SiteLossNotification;
use App\Services\ElectricityAnomalyNotificationService;
use App\Services\InfrastructureSiteAlertService;
use App\Support\DailyNotificationGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class SendScheduledNotifications extends Command
{
    protected $signature = 'notifications:send-scheduled';

    protected $description = 'Kirim notifikasi harian website dan email untuk anomali listrik, site Loss, dan site Infrastruktur';

    public function handle(
        ElectricityAnomalyNotificationService $anomalyNotificationService,
        InfrastructureSiteAlertService $infrastructureSiteAlertService,
    ): int {
        $centralizedCount = AnomaliTagihanPln::where('kenaikan_persen', '>', 50)->count();
        $inbuildingCount = AnomaliTagihanInbuilding::where('kenaikan_persen', '>', 50)->count();

        if ($centralizedCount > 0) {
            $centralizedAnomalies = AnomaliTagihanPln::query()
                ->where('kenaikan_persen', '>', 50)
                ->get()
                ->map(fn (AnomaliTagihanPln $anomaly): array => $anomaly->toArray())
                ->all();

            $sent = $anomalyNotificationService->send('Centralized PLN', $centralizedAnomalies);
            $this->info($sent
                ? "Notifikasi anomali Centralized dikirim: {$centralizedCount} data."
                : 'Notifikasi anomali Centralized sudah dibuat atau email belum dapat dikirim.');
        }

        if ($inbuildingCount > 0) {
            $inbuildingAnomalies = AnomaliTagihanInbuilding::query()
                ->where('kenaikan_persen', '>', 50)
                ->get()
                ->map(fn (AnomaliTagihanInbuilding $anomaly): array => $anomaly->toArray())
                ->all();

            $sent = $anomalyNotificationService->send('Inbuilding', $inbuildingAnomalies);
            $this->info($sent
                ? "Notifikasi anomali Inbuilding dikirim: {$inbuildingCount} data."
                : 'Notifikasi anomali Inbuilding sudah dibuat atau email belum dapat dikirim.');
        }

        $latestPeriod = SiteMonthlyMetric::query()
            ->selectRaw('MAX(tahun * 100 + bulan) as period_key')
            ->value('period_key');

        $lossCount = 0;
        if ($latestPeriod === null) {
            $this->line('Tidak ada periode site untuk diperiksa.');
        } else {
            $year = intdiv((int) $latestPeriod, 100);
            $month = (int) $latestPeriod % 100;
            $lossMetrics = SiteMonthlyMetric::query()
                ->where('tahun', $year)
                ->where('bulan', $month)
                ->where('is_anomaly', 0)
                ->where('profit_loss', '<=', 0)
                ->get(['site_id', 'profit_loss']);
            $lossCount = $lossMetrics->pluck('site_id')->unique()->count();

            $recipients = User::query()
                ->where('account_status', 'approved')
                ->get();

            if ($lossCount > 0 && $recipients->isNotEmpty()) {
                $claim = DailyNotificationGate::reserve('website', 'site-loss');
                if ($claim === null) {
                    $this->line('Notifikasi site Loss tidak dikirim ulang pada slot jadwal yang sama.');
                } else {
                    try {
                        DB::transaction(fn () => $recipients
                            ->each(fn (User $user) => $user->notify(
                                new SiteLossNotification($lossCount, $month, $year)
                            )));
                        $this->info("Notifikasi site Loss dikirim: {$lossCount} site.");
                    } catch (\Throwable $exception) {
                        DailyNotificationGate::release($claim);
                        Log::error('Notifikasi website site Loss gagal dikirim.', [
                            'site_count' => $lossCount,
                            'month' => $month,
                            'year' => $year,
                            'exception' => $exception,
                        ]);
                        $this->error('Notifikasi site Loss gagal dikirim dan akan dicoba lagi.');
                    }
                }
            } elseif ($lossCount > 0) {
                $this->line('Notifikasi site Loss tidak dikirim karena belum ada pengguna yang disetujui.');
            }

            if ($lossCount > 0) {
                $this->sendSiteLossEmail($lossCount, $month, $year);
            }
        }

        $infrastructureResult = $infrastructureSiteAlertService->send();
        $infrastructureCount = array_sum($infrastructureResult['warning_counts']);
        if ($infrastructureCount > 0) {
            $websiteCategories = count($infrastructureResult['website_categories']);
            $emailCategories = count($infrastructureResult['email_categories']);
            $this->info("Peringatan Infrastruktur aktif: {$infrastructureCount} data kategori; {$websiteCategories} notifikasi lonceng dan {$emailCategories} email Excel dibuat.");
        }

        if ($centralizedCount === 0 && $inbuildingCount === 0 && $lossCount === 0 && $infrastructureCount === 0) {
            $this->line('Tidak ada anomali listrik, site Loss, atau peringatan site Infrastruktur aktif.');
        }

        return self::SUCCESS;
    }

    private function sendSiteLossEmail(int $count, int $month, int $year): void
    {
        $recipient = config('mail.site_loss_alert_to');
        if (!is_string($recipient) || trim($recipient) === '') {
            Log::warning('Email site Loss tidak dikirim karena penerima belum dikonfigurasi.');
            return;
        }
        $claim = DailyNotificationGate::reserve('email', 'site-loss');
        if ($claim === null) {
            return;
        }
        try {
            $attachment = Excel::raw(new SiteLossExport($month, $year), ExcelFormat::XLSX);
            $period = sprintf('%02d/%d', $month, $year);
            $dataUrl = route('notifications.site-loss', ['bulan' => $month, 'tahun' => $year]);
            Mail::raw(
                "Masih terdapat {$count} site dengan status Loss pada periode {$period}.\nRincian tersedia pada lampiran Excel.\n\nData terkait: {$dataUrl}",
                function ($message) use ($recipient, $period, $attachment, $month, $year): void {
                    $message->to(trim($recipient))
                        ->subject('Peringatan site Loss - '.$period)
                        ->attachData($attachment, sprintf('site_loss_%d_%02d.xlsx', $year, $month), [
                            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]);
                }
            );
        } catch (\Throwable $exception) {
            DailyNotificationGate::release($claim);
            Log::error('Email site Loss gagal dikirim.', ['exception' => $exception]);
            $this->error('Email site Loss gagal dikirim dan dapat dicoba lagi.');
        }
    }
}
