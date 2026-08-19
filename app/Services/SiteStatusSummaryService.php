<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteMonthlyMetric;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Agregasi status site untuk dashboard: jumlah site Profit, Loss,
 * dan Tidak Aktif pada satu periode (bulan + tahun).
 *
 * Status dihitung PER BULAN PER SITE dari kolom profit_loss:
 *   profit_loss >  0  -> Profit
 *   profit_loss <= 0  -> Loss
 * Site tanpa baris metrik di periode tersebut -> Tidak Aktif.
 *
 * Desain query untuk skala ~17rb site / ~294rb baris metrik:
 * count dihitung langsung di tabel site_monthly_metrics
 * (COUNT DISTINCT site_id lewat unique index smm_site_period_unique),
 * bukan whereHas per site. Karena unique constraint menjamin maksimal
 * satu baris per site per periode, profit + loss = jumlah site aktif.
 */
class SiteStatusSummaryService
{
    /**
     * Ringkasan distribusi status untuk satu periode.
     * Jika bulan/tahun null, pakai periode terbaru yang ada di data.
     *
     * @return array{
     *     bulan: int, tahun: int, total_sites: int,
     *     profit: int, loss: int, active: int, inactive: int
     * }
     */
    public function summary(?int $bulan = null, ?int $tahun = null): array
    {
        if ($bulan === null || $tahun === null) {
            [$bulan, $tahun] = $this->latestPeriod();
        }

        $totalSites = Site::count();

        $profit = $this->metricsInPeriod($bulan, $tahun)
            ->where('profit_loss', '>', 0)
            ->distinct()
            ->count('site_id');

        $loss = $this->metricsInPeriod($bulan, $tahun)
            ->where('profit_loss', '<=', 0)
            ->distinct()
            ->count('site_id');

        $active = $profit + $loss;

        return [
            'bulan'     => $bulan,
            'tahun'     => $tahun,
            'total_sites' => $totalSites,
            'profit'    => $profit,
            'loss'      => $loss,
            'active'    => $active,
            'inactive'  => $totalSites - $active,
        ];
    }

    /**
     * Periode (bulan, tahun) terbaru yang ada di tabel metrik,
     * dihitung dinamis — bukan hardcode.
     *
     * @return array{0: int, 1: int} [bulan, tahun]
     */
    public function latestPeriod(): array
    {
        $latest = SiteMonthlyMetric::query()
            ->select('bulan', 'tahun')
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->first();

        if ($latest === null) {
            throw new RuntimeException('Tabel site_monthly_metrics masih kosong; jalankan import terlebih dahulu.');
        }

        return [(int) $latest->bulan, (int) $latest->tahun];
    }

    /**
     * Base query metrik untuk satu periode (pakai index smm_period_index).
     */
    private function metricsInPeriod(int $bulan, int $tahun): Builder
    {
        return SiteMonthlyMetric::query()
            ->where('bulan', $bulan)
            ->where('tahun', $tahun);
    }
}
