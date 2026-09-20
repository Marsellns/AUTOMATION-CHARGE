<?php

namespace App\Console\Commands;

use App\Models\AnomaliTagihanInbuilding;
use App\Models\AnomaliTagihanPln;
use App\Models\SiteMonthlyMetric;
use App\Models\User;
use App\Notifications\SiteLossNotification;
use App\Services\ElectricityAnomalyNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendScheduledNotifications extends Command
{
    protected $signature = 'notifications:send-scheduled';

    protected $description = 'Kirim notifikasi website untuk anomali listrik dan site Loss';

    public function handle(ElectricityAnomalyNotificationService $anomalyNotificationService): int
    {
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
                : "Notifikasi anomali Centralized sudah dibuat atau email belum dapat dikirim.");
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
                : "Notifikasi anomali Inbuilding sudah dibuat atau email belum dapat dikirim.");
        }

        $latestPeriod = SiteMonthlyMetric::query()
            ->selectRaw('MAX(tahun * 100 + bulan) as period_key')
            ->value('period_key');

        if ($latestPeriod === null) {
            $this->line('Tidak ada periode site untuk diperiksa.');

            return self::SUCCESS;
        }

        $year = intdiv((int) $latestPeriod, 100);
        $month = (int) $latestPeriod % 100;
        $lossMetrics = SiteMonthlyMetric::query()
            ->where('tahun', $year)
            ->where('bulan', $month)
            ->where('profit_loss', '<=', 0)
            ->get(['site_id', 'profit_loss']);
        $lossCount = $lossMetrics->pluck('site_id')->unique()->count();

        if ($lossCount > 0) {
            $signature = $lossMetrics
                ->sortBy('site_id')
                ->map(fn (SiteMonthlyMetric $metric) => [$metric->site_id, (string) $metric->profit_loss])
                ->values()
                ->toJson();
            $notificationKey = 'site-loss-notification:'.hash('sha256', "{$year}-{$month}|{$signature}");

            if (! Cache::has($notificationKey)) {
                User::query()
                    ->where('account_status', 'approved')
                    ->get()
                    ->each(fn (User $user) => $user->notify(
                        new SiteLossNotification($lossCount, $month, $year)
                    ));
                Cache::put($notificationKey, true, now()->addDay());
                $this->info("Notifikasi site Loss dikirim: {$lossCount} site.");
            } else {
                $this->line('Notifikasi site Loss tidak dikirim ulang karena data belum berubah.');
            }
        }

        if ($centralizedCount === 0 && $inbuildingCount === 0 && $lossCount === 0) {
            $this->line('Tidak ada anomali listrik atau site Loss aktif.');
        }

        return self::SUCCESS;
    }
}
