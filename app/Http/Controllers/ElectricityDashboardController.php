<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ElectricityDashboardController extends Controller
{
    private const PAID_STATUS_SQL = "LOWER(TRIM(COALESCE(status, ''))) IN ('done', 'paid', 'lunas', 'terbayar')";

    private const MONTH_LABELS = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    public function index(): View
    {
        return view('electricity.dashboard.index');
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'bulan' => ['nullable', 'string', 'max:3'],
            'scope' => ['nullable', 'in:all,centralized,inbuilding'],
            'nop' => ['nullable', 'string', 'max:100'],
        ]);

        $scope = (string) ($validated['scope'] ?? 'all');
        $availablePeriods = $this->availablePeriods($scope);
        $availableYears = $availablePeriods
            ->pluck('tahun')
            ->map(static fn ($year): int => (int) $year)
            ->unique()
            ->sort()
            ->values();

        $defaultYear = $availableYears->contains(now()->year)
            ? now()->year
            : (int) ($availableYears->max() ?? now()->year);
        $year = (int) ($validated['tahun'] ?? $defaultYear);

        $monthInput = strtolower(trim((string) ($validated['bulan'] ?? 'all')));
        abort_unless(
            $monthInput === 'all' || (ctype_digit($monthInput) && (int) $monthInput >= 1 && (int) $monthInput <= 12),
            422,
            'Bulan tidak valid.'
        );
        $month = $monthInput === 'all' ? null : (int) $monthInput;

        $rawNops = DB::table('listrik_pln')
            ->whereNotNull('nop')
            ->whereRaw("TRIM(nop) <> ''")
            ->distinct()
            ->pluck('nop');
        $availableNops = $rawNops
            ->map(fn ($rawNop): ?string => $this->normalizeNop($rawNop))
            ->filter()
            ->unique(static fn (string $value): string => mb_strtoupper($value))
            ->sortBy(static fn (string $value): string => mb_strtoupper($value))
            ->values();

        $nopInput = trim((string) ($validated['nop'] ?? ''));
        $nop = $nopInput === '' || strcasecmp($nopInput, 'all') === 0
            ? null
            : $availableNops->first(
                static fn (string $availableNop): bool => strcasecmp($availableNop, $nopInput) === 0
            );
        abort_if($nopInput !== '' && strcasecmp($nopInput, 'all') !== 0 && $nop === null, 422, 'NOP tidak valid.');
        $nopValues = $nop === null
            ? collect()
            : $rawNops
                ->filter(fn ($rawNop): bool => strcasecmp((string) $this->normalizeNop($rawNop), $nop) === 0)
                ->values();

        // NOP hanya tersedia pada master Centralized. Menerapkan NOP ke gabungan
        // data akan membuat angka Inbuilding seolah-olah ikut terfilter.
        if ($nop !== null) {
            $scope = 'centralized';
            $availablePeriods = $this->availablePeriods($scope);
            $availableYears = $availablePeriods
                ->pluck('tahun')
                ->map(static fn ($availableYear): int => (int) $availableYear)
                ->unique()
                ->sort()
                ->values();
        }

        $includeCentralized = in_array($scope, ['all', 'centralized'], true);
        $includeInbuilding = in_array($scope, ['all', 'inbuilding'], true);

        $centralMaster = DB::table('listrik_pln')
            ->when($nop !== null, fn (Builder $query) => $query->whereIn('nop', $nopValues));
        $inbuildingMaster = DB::table('listrik_inbuilding');

        $centralSites = $includeCentralized
            ? (clone $centralMaster)->distinct()->count('site_id')
            : 0;
        $inbuildingSites = $includeInbuilding
            ? (clone $inbuildingMaster)->distinct()->count('site_id')
            : 0;
        $centralActiveSites = $includeCentralized
            ? (clone $centralMaster)
                ->whereRaw("LOWER(TRIM(COALESCE(status_aktif_site, ''))) IN ('aktif', 'active', 'on air')")
                ->distinct()
                ->count('site_id')
            : 0;
        $inbuildingActiveSites = $includeInbuilding
            ? (clone $inbuildingMaster)
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('aktif', 'active', 'on air')")
                ->distinct()
                ->count('site_id')
            : 0;
        $centralCapacity = $includeCentralized ? (float) (clone $centralMaster)->sum('daya_va') : 0.0;
        $inbuildingCapacity = $includeInbuilding ? (float) (clone $inbuildingMaster)->sum('daya') : 0.0;

        $centralPayments = DB::table('payment_pln')
            ->where('tahun', $year)
            ->when($month !== null, fn (Builder $query) => $query->where('bulan', $month))
            ->when($nop !== null, function (Builder $query) use ($nopValues): void {
                $query->whereIn('site_id', DB::table('listrik_pln')
                    ->select('site_id')
                    ->whereIn('nop', $nopValues));
            });
        $inbuildingPayments = DB::table('payment_ibc')
            ->where('tahun', $year)
            ->when($month !== null, fn (Builder $query) => $query->where('bulan', $month));

        $centralPaymentStats = $includeCentralized
            ? $this->paymentStats($centralPayments, 'harga')
            : $this->emptyPaymentStats();
        $inbuildingPaymentStats = $includeInbuilding
            ? $this->paymentStats($inbuildingPayments, 'jumlah_tagihan')
            : $this->emptyPaymentStats();

        $months = $month === null ? range(1, 12) : [$month];
        $centralTrend = $includeCentralized
            ? $this->paymentTrend($centralPayments, 'harga')
            : collect();
        $inbuildingTrend = $includeInbuilding
            ? $this->paymentTrend($inbuildingPayments, 'jumlah_tagihan')
            : collect();

        $centralAnomalies = DB::table('anomali_tagihan_pln')
            ->where('tahun', $year)
            ->when($month !== null, fn (Builder $query) => $query->where('bulan', $month))
            ->when($nop !== null, function (Builder $query) use ($nopValues): void {
                $query->whereIn('site_id', DB::table('listrik_pln')
                    ->select('site_id')
                    ->whereIn('nop', $nopValues));
            });
        $periodPrefix = sprintf('%04d-', $year);
        $inbuildingAnomalies = DB::table('anomali_tagihan_inbuilding')
            ->where('periode_saat_ini', 'like', $periodPrefix.'%')
            ->when($month !== null, fn (Builder $query) => $query->where(
                'periode_saat_ini',
                sprintf('%04d-%02d', $year, $month)
            ));

        $centralAnomalyCount = $includeCentralized ? (clone $centralAnomalies)->count() : 0;
        $inbuildingAnomalyCount = $includeInbuilding ? (clone $inbuildingAnomalies)->count() : 0;
        $centralAnomalyImpact = $includeCentralized
            ? (float) ((clone $centralAnomalies)->selectRaw('COALESCE(SUM(ABS(selisih)), 0) as total')->value('total') ?? 0)
            : 0.0;
        $inbuildingAnomalyImpact = $includeInbuilding
            ? (float) ((clone $inbuildingAnomalies)->selectRaw('COALESCE(SUM(ABS(selisih)), 0) as total')->value('total') ?? 0)
            : 0.0;

        $centralAnomalyTrend = $includeCentralized
            ? (clone $centralAnomalies)
                ->selectRaw('bulan, COUNT(*) as total')
                ->groupBy('bulan')
                ->pluck('total', 'bulan')
            : collect();
        $inbuildingAnomalyTrend = $includeInbuilding
            ? (clone $inbuildingAnomalies)
                ->pluck('periode_saat_ini')
                ->countBy(static fn (?string $period): int => (int) substr((string) $period, 5, 2))
            : collect();

        $latestAnomalies = $this->latestAnomalies(
            $includeCentralized ? $centralAnomalies : null,
            $includeInbuilding ? $inbuildingAnomalies : null
        );

        $boramCount = 0;
        if ($includeCentralized) {
            $boramQuery = DB::table('bongkar_rampung_mandiri');
            if ($nop !== null) {
                $boramQuery->whereIn('site_id', DB::table('listrik_pln')
                    ->select('site_id')
                    ->whereIn('nop', $nopValues));
            }
            $boramCount = $boramQuery->count();
        }

        $totalBilled = $centralPaymentStats['total_amount'] + $inbuildingPaymentStats['total_amount'];
        $totalPaid = $centralPaymentStats['paid_amount'] + $inbuildingPaymentStats['paid_amount'];
        $totalPending = $centralPaymentStats['pending_amount'] + $inbuildingPaymentStats['pending_amount'];
        $paymentRate = $totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100, 1) : 0.0;
        $totalSites = $centralSites + $inbuildingSites;
        $activeSites = $centralActiveSites + $inbuildingActiveSites;

        return response()->json([
            'filters' => [
                'tahun' => $year,
                'bulan' => $month,
                'scope' => $scope,
                'nop' => $nop,
                'years' => $availableYears->all(),
                'periods' => $availablePeriods->values()->all(),
                'nops' => $availableNops->values()->all(),
            ],
            'kpi' => [
                'total_sites' => $totalSites,
                'active_sites' => $activeSites,
                'inactive_sites' => max(0, $totalSites - $activeSites),
                'total_capacity_va' => $centralCapacity + $inbuildingCapacity,
                'total_billed' => round($totalBilled, 2),
                'paid_amount' => round($totalPaid, 2),
                'pending_amount' => round($totalPending, 2),
                'payment_rate' => $paymentRate,
                'payment_records' => $centralPaymentStats['total_records'] + $inbuildingPaymentStats['total_records'],
                'pending_records' => $centralPaymentStats['pending_records'] + $inbuildingPaymentStats['pending_records'],
                'anomalies' => $centralAnomalyCount + $inbuildingAnomalyCount,
                'anomaly_impact' => round($centralAnomalyImpact + $inbuildingAnomalyImpact, 2),
                'boram' => $boramCount,
            ],
            'billing_trend' => [
                'labels' => array_map(fn (int $value): string => self::MONTH_LABELS[$value].' '.$year, $months),
                'periods' => array_map(fn (int $value): array => ['tahun' => $year, 'bulan' => $value], $months),
                'centralized' => array_map(
                    fn (int $value): float => round((float) ($centralTrend->get($value)->total_amount ?? 0), 2),
                    $months
                ),
                'inbuilding' => array_map(
                    fn (int $value): float => round((float) ($inbuildingTrend->get($value)->total_amount ?? 0), 2),
                    $months
                ),
                'paid' => array_map(
                    fn (int $value): float => round(
                        (float) ($centralTrend->get($value)->paid_amount ?? 0)
                        + (float) ($inbuildingTrend->get($value)->paid_amount ?? 0),
                        2
                    ),
                    $months
                ),
                'pending' => array_map(
                    fn (int $value): float => round(
                        (float) ($centralTrend->get($value)->pending_amount ?? 0)
                        + (float) ($inbuildingTrend->get($value)->pending_amount ?? 0),
                        2
                    ),
                    $months
                ),
            ],
            'site_distribution' => array_values(array_filter([
                $includeCentralized ? [
                    'name' => 'Centralized',
                    'sites' => $centralSites,
                    'active_sites' => $centralActiveSites,
                    'capacity_va' => round($centralCapacity, 2),
                    'billed' => round($centralPaymentStats['total_amount'], 2),
                    'anomalies' => $centralAnomalyCount,
                ] : null,
                $includeInbuilding ? [
                    'name' => 'Inbuilding',
                    'sites' => $inbuildingSites,
                    'active_sites' => $inbuildingActiveSites,
                    'capacity_va' => round($inbuildingCapacity, 2),
                    'billed' => round($inbuildingPaymentStats['total_amount'], 2),
                    'anomalies' => $inbuildingAnomalyCount,
                ] : null,
            ])),
            'payment_status' => [
                ['name' => 'Terbayar', 'records' => $centralPaymentStats['paid_records'] + $inbuildingPaymentStats['paid_records'], 'amount' => round($totalPaid, 2)],
                ['name' => 'Belum Terbayar', 'records' => $centralPaymentStats['pending_records'] + $inbuildingPaymentStats['pending_records'], 'amount' => round($totalPending, 2)],
            ],
            'anomaly_trend' => [
                'labels' => array_map(fn (int $value): string => self::MONTH_LABELS[$value], $months),
                'centralized' => array_map(fn (int $value): int => (int) ($centralAnomalyTrend->get($value) ?? 0), $months),
                'inbuilding' => array_map(fn (int $value): int => (int) ($inbuildingAnomalyTrend->get($value) ?? 0), $months),
            ],
            'nop_distribution' => $includeCentralized ? $this->nopDistribution($centralMaster) : [],
            'latest_anomalies' => $latestAnomalies,
            'meta' => [
                'period_label' => $month === null
                    ? 'Januari–Desember '.$year
                    : self::MONTH_LABELS[$month].' '.$year,
                'scope_label' => match ($scope) {
                    'centralized' => 'Centralized'.($nop !== null ? ' · NOP '.$nop : ''),
                    'inbuilding' => 'Inbuilding',
                    default => 'Seluruh Electricity',
                },
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function availablePeriods(string $scope): Collection
    {
        $periods = collect();

        if (in_array($scope, ['all', 'centralized'], true)) {
            $periods = $periods->merge(
                DB::table('payment_pln')
                    ->whereNotNull('tahun')
                    ->whereNotNull('bulan')
                    ->select('tahun', 'bulan')
                    ->distinct()
                    ->get()
            );
        }

        if (in_array($scope, ['all', 'inbuilding'], true)) {
            $periods = $periods->merge(
                DB::table('payment_ibc')
                    ->whereNotNull('tahun')
                    ->whereNotNull('bulan')
                    ->select('tahun', 'bulan')
                    ->distinct()
                    ->get()
            );
        }

        return $periods
            ->map(static fn ($period): array => [
                'tahun' => (int) $period->tahun,
                'bulan' => (int) $period->bulan,
            ])
            ->unique(static fn (array $period): string => $period['tahun'].'-'.$period['bulan'])
            ->sortBy(static fn (array $period): array => [$period['tahun'], $period['bulan']])
            ->values();
    }

    private function paymentStats(Builder $query, string $amountColumn): array
    {
        $totalRecords = (clone $query)->count();
        $totalAmount = (float) (clone $query)->sum($amountColumn);
        $paidQuery = (clone $query)->whereRaw(self::PAID_STATUS_SQL);
        $paidRecords = (clone $paidQuery)->count();
        $paidAmount = (float) $paidQuery->sum($amountColumn);

        return [
            'total_records' => $totalRecords,
            'paid_records' => $paidRecords,
            'pending_records' => max(0, $totalRecords - $paidRecords),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'pending_amount' => max(0.0, $totalAmount - $paidAmount),
        ];
    }

    private function emptyPaymentStats(): array
    {
        return [
            'total_records' => 0,
            'paid_records' => 0,
            'pending_records' => 0,
            'total_amount' => 0.0,
            'paid_amount' => 0.0,
            'pending_amount' => 0.0,
        ];
    }

    private function paymentTrend(Builder $query, string $amountColumn): Collection
    {
        return (clone $query)
            ->selectRaw(
                "bulan, SUM({$amountColumn}) as total_amount, "
                ."SUM(CASE WHEN ".self::PAID_STATUS_SQL." THEN {$amountColumn} ELSE 0 END) as paid_amount, "
                ."SUM(CASE WHEN ".self::PAID_STATUS_SQL." THEN 0 ELSE {$amountColumn} END) as pending_amount"
            )
            ->groupBy('bulan')
            ->get()
            ->keyBy(static fn ($row): int => (int) $row->bulan);
    }

    private function latestAnomalies(?Builder $centralized, ?Builder $inbuilding): array
    {
        $items = collect();

        if ($centralized !== null) {
            $items = $items->merge((clone $centralized)
                ->orderByDesc('tahun')
                ->orderByDesc('bulan')
                ->orderByDesc('kenaikan_persen')
                ->limit(8)
                ->get()
                ->map(static fn ($row): array => [
                    'source' => 'Centralized',
                    'site_id' => (string) $row->site_id,
                    'site_name' => (string) ($row->site_name ?? ''),
                    'period' => sprintf('%04d-%02d', $row->tahun, $row->bulan),
                    'previous_bill' => (float) $row->tagihan_sebelumnya,
                    'current_bill' => (float) $row->tagihan_saat_ini,
                    'difference' => (float) $row->selisih,
                    'increase_percent' => (float) $row->kenaikan_persen,
                    'url' => route('electricity.centralized.anomali.index'),
                ]));
        }

        if ($inbuilding !== null) {
            $items = $items->merge((clone $inbuilding)
                ->orderByDesc('periode_saat_ini')
                ->orderByDesc('kenaikan_persen')
                ->limit(8)
                ->get()
                ->map(static fn ($row): array => [
                    'source' => 'Inbuilding',
                    'site_id' => (string) $row->site_id,
                    'site_name' => '',
                    'period' => (string) $row->periode_saat_ini,
                    'previous_bill' => (float) $row->tagihan_sebelumnya,
                    'current_bill' => (float) $row->tagihan_saat_ini,
                    'difference' => (float) $row->selisih,
                    'increase_percent' => (float) $row->kenaikan_persen,
                    'url' => route('electricity.inbuilding.anomali.index'),
                ]));
        }

        return $items
            ->sortByDesc(static fn (array $item): string => $item['period'].'-'.str_pad((string) round($item['increase_percent']), 8, '0', STR_PAD_LEFT))
            ->take(8)
            ->values()
            ->all();
    }

    private function nopDistribution(Builder $masterQuery): array
    {
        return (clone $masterQuery)
            ->select('nop', 'site_id', 'daya_va')
            ->get()
            ->groupBy(fn ($row): string => $this->normalizeNop($row->nop) ?? 'Tanpa NOP')
            ->map(static fn (Collection $rows, string $name): array => [
                'name' => $name,
                'sites' => $rows->pluck('site_id')->filter()->unique()->count(),
                'capacity_va' => (float) $rows->sum('daya_va'),
            ])
            ->sortByDesc('sites')
            ->take(10)
            ->values()
            ->all();
    }

    private function normalizeNop(mixed $value): ?string
    {
        $nop = trim((string) $value);
        $upper = mb_strtoupper($nop);

        if ($nop === '' || in_array($upper, ['/', '-', '#N/A', 'N/A', 'NULL', 'NONE'], true)) {
            return null;
        }

        if (str_starts_with($upper, 'NOP ')) {
            $nop = trim(substr($nop, 4));
        }

        return $nop === '' ? null : mb_strtoupper($nop);
    }
}
