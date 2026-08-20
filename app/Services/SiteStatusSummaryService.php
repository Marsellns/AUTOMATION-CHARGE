<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteMonthlyMetric;
use Illuminate\Support\Facades\Cache;
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
     * Hasil agregasi di-cache per periode (data hanya berubah saat import).
     * Key diberi prefix versi yang dinaikkan lewat invalidateCache() setiap
     * selesai import, sehingga entri lama tidak pernah dipakai lagi dan
     * pergantian periode di UI terasa instan.
     */
    private const CACHE_VERSION_KEY = 'dashboard.cache_version';
    private const CACHE_TTL_SECONDS = 60 * 60 * 12;

    /**
     * Ringkasan distribusi status + total keuangan untuk satu periode.
     * Jika bulan/tahun null, pakai periode terbaru yang ada di data.
     *
     * Semua count & total dihitung dalam SATU query agregat (CASE WHEN)
     * di tabel site_monthly_metrics — bukan beberapa query terpisah.
     * Hasilnya di-cache; panggil invalidateCache() setelah import data baru.
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

        return Cache::remember(
            $this->key("summary.{$tahun}.{$bulan}"),
            self::CACHE_TTL_SECONDS,
            fn () => $this->computeSummary($bulan, $tahun)
        );
    }

    /**
     * Perhitungan agregat asli (tanpa cache): satu query CASE WHEN +
     * count total site.
     */
    private function computeSummary(int $bulan, int $tahun): array
    {
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
     * Daftar site per status (Profit/Loss/TidakAktif) untuk satu halaman
     * drawer, di-cache dengan skema versi yang sama seperti summary().
     * Payload $compute sudah berupa array siap-serialisasi (hasil paginate
     * ->through(...)), sehingga hit cache langsung dikembalikan tanpa
     * menyentuh database — klik segmen donut & pindah halaman jadi instan.
     */
    public function sitesByStatus(string $status, int $bulan, int $tahun, int $page, callable $compute): array
    {
        return Cache::remember(
            $this->key("sites.{$status}.{$tahun}.{$bulan}.p{$page}"),
            self::CACHE_TTL_SECONDS,
            $compute
        );
    }

    /**
     * Periode-periode yang benar-benar ada di tabel metrik (distinct
     * bulan+tahun), terurut dari terbaru ke terlama. Dipakai UI dashboard
     * untuk mengisi dropdown pilihan periode — dinamis, bukan hardcode.
     *
     * @return array<int, array{bulan: int, tahun: int}>
     */
    public function availablePeriods(): array
    {
        return Cache::remember(
            $this->key('periods'),
            self::CACHE_TTL_SECONDS,
            fn () => SiteMonthlyMetric::query()
                ->select('bulan', 'tahun')
                ->distinct()
                ->orderByDesc('tahun')
                ->orderByDesc('bulan')
                ->get()
                ->map(fn ($p) => ['bulan' => (int) $p->bulan, 'tahun' => (int) $p->tahun])
                ->all()
        );
    }

    /**
     * Periode (bulan, tahun) terbaru yang ada di tabel metrik,
     * dihitung dinamis — bukan hardcode.
     *
     * @return array{0: int, 1: int} [bulan, tahun]
     */
    public function latestPeriod(): array
    {
        $periods = $this->availablePeriods();

        if ($periods === []) {
            throw new RuntimeException('Tabel site_monthly_metrics masih kosong; jalankan import terlebih dahulu.');
        }

        return [$periods[0]['bulan'], $periods[0]['tahun']];
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
        return Cache::remember(
            $this->key('period_range'),
            self::CACHE_TTL_SECONDS,
            fn () => $this->computePeriodRange()
        );
    }

    private function computePeriodRange(): array
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

    /**
     * Naikkan versi key cache sehingga semua entri agregat lama tidak
     * dipakai lagi. Dipanggil setelah import data baru selesai.
     */
    public function invalidateCache(): void
    {
        Cache::put(
            self::CACHE_VERSION_KEY,
            (int) Cache::get(self::CACHE_VERSION_KEY, 1) + 1,
            self::CACHE_TTL_SECONDS
        );
    }

    /** Key cache ber-prefix versi agar invalidasi murah (tanpa tag). */
    private function key(string $suffix): string
    {
        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 1);

        return "dashboard.v{$version}.{$suffix}";
    }
}
