<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PnlAnalyticsService
{
    public const UNMAPPED = '__unmapped__';

    public function analytics(int $year, ?int $month, ?string $nop, ?string $city): array
    {
        // Aggregate metrics before joining the master so each site contributes once.
        $metrics = DB::table('site_monthly_metrics')
            ->where('tahun', $year)
            ->when($month !== null, fn ($query) => $query->where('bulan', $month))
            ->when($month === null, fn ($query) => $query->where('is_anomaly', 0))
            ->select('site_id')
            ->selectRaw('SUM(revenue) as revenue, SUM(cost) as cost, SUM(profit_loss) as profit_loss, COUNT(*) as metrics_count')
            ->groupBy('site_id');

        $sites = $this->locatedSites($nop, $city)
            ->leftJoinSub($metrics, 'm', 'm.site_id', '=', 's.id')
            ->select('s.id', 's.site_id', 's.site_name', 'so.nop', 'so.city', 'm.revenue', 'm.cost', 'm.profit_loss', 'm.metrics_count')
            ->orderBy('s.site_id')->get();

        $monthly = $this->locatedSites($nop, $city)
            ->join('site_monthly_metrics as m', 'm.site_id', '=', 's.id')
            ->where('m.tahun', $year)
            ->when($month !== null, fn ($query) => $query->where('m.bulan', $month))
            ->when($month === null, fn ($query) => $query->where('m.is_anomaly', 0))
            ->select('m.bulan')
            ->selectRaw('SUM(m.revenue) as revenue, SUM(m.cost) as cost, SUM(m.profit_loss) as profit_loss')
            ->groupBy('m.bulan')->orderBy('m.bulan')->get()
            ->map(fn ($row) => [
                'month' => (int) $row->bulan,
                ...$this->financials((float) $row->revenue, (float) $row->cost, (float) $row->profit_loss),
            ])->values();

        $active = $sites->filter(fn ($site) => (int) $site->metrics_count > 0);
        $profit = $active->filter(fn ($site) => (float) $site->profit_loss > 0)->count();
        $loss = $active->count() - $profit;

        return [
            'status' => [
                ['name' => 'Profit', 'key' => 'Profit', 'y' => $profit],
                ['name' => 'Loss / Impas', 'key' => 'Loss', 'y' => $loss],
                ['name' => 'Tidak Aktif', 'key' => 'TidakAktif', 'y' => $sites->count() - $active->count()],
            ],
            'nop' => $this->groups($sites, 'nop'),
            'cities' => $this->groups($sites, 'city'),
            'monthly' => $monthly,
            'analysis' => $active->sortByDesc(fn ($site) => abs((float) $site->profit_loss))->take(10)
                ->map(fn ($site) => [
                    'id' => $site->id, 'site_id' => $site->site_id, 'site_name' => $site->site_name,
                    'nop' => $site->nop, 'city' => $site->city,
                    ...$this->financials((float) $site->revenue, (float) $site->cost, (float) $site->profit_loss),
                ])->values(),
            'sites_count' => $sites->count(),
            'active_count' => $active->count(),
        ];
    }

    private function locatedSites(?string $nop, ?string $city): Builder
    {
        // Master imports may contain repeated site codes; deduplicate before summing P&L.
        $owners = DB::table('site_owners')
            ->selectRaw("UPPER(TRIM(site_code)) as site_code, MIN(NULLIF(UPPER(TRIM(nop)), '')) as nop, MIN(NULLIF(UPPER(TRIM(city)), '')) as city")
            ->groupByRaw('UPPER(TRIM(site_code))');

        $query = DB::table('sites as s')->leftJoinSub($owners, 'so', function ($join) {
            $join->on('so.site_code', '=', DB::raw('UPPER(TRIM(s.site_id))'));
        });
        foreach (['nop' => $nop, 'city' => $city] as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $value === self::UNMAPPED
                ? $query->whereNull('so.'.$field)
                : $query->where('so.'.$field, strtoupper(trim($value)));
        }

        return $query;
    }

    private function groups(Collection $sites, string $field): Collection
    {
        return $sites->groupBy(fn ($site) => $site->{$field} ?: self::UNMAPPED)
            ->map(function (Collection $group, string $key) {
                $active = $group->filter(fn ($site) => (int) $site->metrics_count > 0);
                $profit = $active->filter(fn ($site) => (float) $site->profit_loss > 0);
                $loss = $active->filter(fn ($site) => (float) $site->profit_loss <= 0);

                return [
                    'name' => $key === self::UNMAPPED ? 'Belum dipetakan' : $key,
                    'key' => $key, 'sites' => $group->count(), 'active' => $active->count(),
                    'profit_sites' => $profit->count(), 'loss_sites' => $loss->count(),
                    'inactive_sites' => $group->count() - $active->count(),
                    'profit' => (float) $profit->sum('profit_loss'), 'loss' => (float) $loss->sum('profit_loss'),
                    ...$this->financials((float) $active->sum('revenue'), (float) $active->sum('cost'), (float) $active->sum('profit_loss')),
                ];
            })->sortByDesc('sites')->values();
    }

    private function financials(float $revenue, float $cost, float $pnl): array
    {
        return [
            'revenue' => $revenue, 'cost' => $cost, 'profit_loss' => $pnl,
            'margin' => $revenue != 0 ? round($pnl / $revenue * 100, 2) : null,
        ];
    }

    /** Additional drill-down filters for the existing table and Excel query. */
    public static function applyDrilldownFilters(EloquentBuilder $query, ?string $city, ?string $site): void
    {
        if ($city !== null && $city !== '') {
            $city === self::UNMAPPED
                ? $query->whereRaw("COALESCE(TRIM(so.city), '') = ''")
                : $query->whereRaw('UPPER(TRIM(so.city)) = ?', [strtoupper(trim($city))]);
        }
        if ($site !== null && $site !== '') {
            $query->whereRaw('UPPER(TRIM(sites.site_id)) = ?', [strtoupper(trim($site))]);
        }
    }
}
