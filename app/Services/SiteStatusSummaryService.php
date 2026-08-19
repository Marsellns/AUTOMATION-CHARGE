<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteMonthlyMetric;
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
     * Ringkasan distribusi status + total keuangan untuk satu periode.
     * Jika bulan/tahun null, pakai periode terbaru yang ada di data.
     *
     * Semua count & total dihitung dalam SATU query agregat (CASE WHEN)
     * di tabel site_monthly_metrics — bukan beberapa query terpisah.
     *
     * Kebijakan anomali (is_anomaly = true):
     * - total_revenue/cost/profit_loss MENGECUALIKAN baris anomali
     * - jumlah site Profit/Loss/Active/Inactive TETAP mengikutkan baris
     *   anomali, karena statusnya belum tentu salah — hanya nilainya
     *   yang mencurigakan.
     *
     * @return array{
     *     bulan: int, tahun: int, total_sites: int,
     *     profit: int, loss: int, active: int, inactive: int,
     *     total_revenue: float, total_cost: float, total_profit_loss: float,
     *     excluded_anomaly_rows: int
     * }
     */
    public function summary(?int $bulan = null, ?int $tahun = null): array
    {
        if ($bulan === null || $tahun === null) {
            [$bulan, $tahun] = $this->latestPeriod();
        }

        $totalSites = Site::count();

        // Satu query agregat: COUNT(DISTINCT CASE ...) untuk status site
        // (termasuk baris anomali), SUM(CASE WHEN is_anomaly = 0 ...)
        // untuk total keuangan (tanpa baris anomali).
        $agg = SiteMonthlyMetric::query()
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->selectRaw(implode(', ', [
                'COUNT(DISTINCT CASE WHEN profit_loss > 0 THEN site_id END) as profit',
                'COUNT(DISTINCT CASE WHEN profit_loss <= 0 THEN site_id END) as loss',
                'COALESCE(SUM(CASE WHEN is_anomaly = 0 THEN revenue END), 0) as total_revenue',
                'COALESCE(SUM(CASE WHEN is_anomaly = 0 THEN cost END), 0) as total_cost',
                'COALESCE(SUM(CASE WHEN is_anomaly = 0 THEN profit_loss END), 0) as total_profit_loss',
                'COALESCE(SUM(is_anomaly), 0) as excluded_anomaly_rows',
            ]))
            ->first();

        $profit = (int) $agg->profit;
        $loss = (int) $agg->loss;
        $active = $profit + $loss;

        return [
            'bulan'     => $bulan,
            'tahun'     => $tahun,
            'total_sites' => $totalSites,
            'profit'    => $profit,
            'loss'      => $loss,
            'active'    => $active,
            'inactive'  => $totalSites - $active,
            'total_revenue' => (float) $agg->total_revenue,
            'total_cost' => (float) $agg->total_cost,
            'total_profit_loss' => (float) $agg->total_profit_loss,
            'excluded_anomaly_rows' => (int) $agg->excluded_anomaly_rows,
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
     * Semua periode (bulan, tahun) dari yang paling lama sampai paling baru
     * yang ada di tabel metrik. Dipakai untuk menentukan bulan-bulan tanpa
     * data (Tidak Aktif) pada detail site.
     *
     * @return array<int, array{bulan: int, tahun: int}>
     */
    public function periodRange(): array
    {
        $min = SiteMonthlyMetric::query()
            ->select('bulan', 'tahun')
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->first();

        $max = SiteMonthlyMetric::query()
            ->select('bulan', 'tahun')
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->first();

        if ($min === null || $max === null) {
            return [];
        }

        $periods = [];
        $bulan = (int) $min->bulan;
        $tahun = (int) $min->tahun;
        $endBulan = (int) $max->bulan;
        $endTahun = (int) $max->tahun;

        while ($tahun < $endTahun || ($tahun === $endTahun && $bulan <= $endBulan)) {
            $periods[] = ['bulan' => $bulan, 'tahun' => $tahun];
            $bulan++;
            if ($bulan > 12) {
                $bulan = 1;
                $tahun++;
            }
        }

        return $periods;
    }
}
