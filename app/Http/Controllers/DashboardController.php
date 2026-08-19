<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\SiteStatusSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SiteStatusSummaryService $summaryService,
    ) {
    }

    /**
     * GET /api/dashboard/periods
     *
     * Daftar periode (bulan+tahun) yang tersedia di data, terurut dari
     * terbaru. Dipakai dropdown pemilih periode di UI dashboard.
     */
    public function periods(): JsonResponse
    {
        return response()->json([
            'data' => $this->summaryService->availablePeriods(),
        ]);
    }

    /**
     * GET /api/dashboard/pnl-summary?bulan=&tahun=
     *
     * Agregat satu periode: jumlah site Profit/Loss/Tidak Aktif +
     * total revenue/cost/profit_loss. Periode default = periode terbaru
     * di data (dinamis). Dihitung dalam satu query agregat CASE WHEN.
     */
    public function pnlSummary(Request $request): JsonResponse
    {
        [$bulan, $tahun] = $this->resolvePeriod($request);

        return response()->json([
            'data' => $this->summaryService->summary($bulan, $tahun),
        ]);
    }

    /**
     * GET /api/dashboard/pnl-summary/{status}?bulan=&tahun=
     *
     * Daftar site berstatus Profit / Loss / TidakAktif pada periode tsb,
     * paginated 25/halaman, lengkap dengan nama site, region, dan nilai
     * revenue/cost/profit_loss bulan tersebut (null untuk TidakAktif).
     */
    public function pnlSummaryByStatus(Request $request, string $status): JsonResponse
    {
        $status = $this->normalizeStatus($status);
        [$bulan, $tahun] = $this->resolvePeriod($request);

        if ($status === 'TidakAktif') {
            $sites = Site::query()
                ->inactiveIn($bulan, $tahun)
                ->with('region')
                ->orderBy('sites.site_id')
                ->paginate(25)
                ->through(fn (Site $site) => $this->formatSiteRow($site, null));
        } else {
            // Profit/Loss: satu query JOIN sites <-> metrik periode tsb.
            // Unique constraint menjamin maksimal 1 baris metrik per site
            // per periode, jadi tidak ada duplikasi hasil join.
            $operator = $status === 'Profit' ? '>' : '<=';

            $sites = Site::query()
                ->join('site_monthly_metrics as m', function ($join) use ($bulan, $tahun) {
                    $join->on('m.site_id', '=', 'sites.id')
                        ->where('m.bulan', $bulan)
                        ->where('m.tahun', $tahun);
                })
                ->where('m.profit_loss', $operator, 0)
                ->select(
                    'sites.id',
                    'sites.site_id',
                    'sites.site_name',
                    'sites.region_id',
                    'm.revenue',
                    'm.cost',
                    'm.profit_loss'
                )
                ->with('region')
                // Profit: terbaik dulu; Loss: kerugian terbesar dulu
                ->orderBy('m.profit_loss', $status === 'Profit' ? 'desc' : 'asc')
                ->orderBy('sites.site_id')
                ->paginate(25)
                ->through(fn (Site $site) => $this->formatSiteRow($site, $site));
        }

        return response()->json([
            'data' => [
                'status' => $status,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'sites' => $sites,
            ],
        ]);
    }

    /**
     * GET /api/dashboard/site/{site_id}/detail
     *
     * Detail lengkap satu site: identitas, histori semua bulan yang ada
     * (revenue, cost, profit_loss, status per bulan), dan daftar bulan
     * tanpa data (Tidak Aktif) dalam rentang periode seluruh dataset.
     */
    public function siteDetail(string $siteId): JsonResponse
    {
        $site = Site::where('site_id', $siteId)->with('region')->first();

        abort_if($site === null, 404, "Site dengan ID '{$siteId}' tidak ditemukan.");

        // Semua metrik site di-load sekali, urut kronologis (tanpa N+1).
        $metrics = $site->monthlyMetrics()
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->get();

        $history = $metrics->map(fn ($m) => [
            'bulan' => $m->bulan,
            'tahun' => $m->tahun,
            'periode' => $m->periode_label,
            'revenue' => (float) $m->revenue,
            'cost' => (float) $m->cost,
            'profit_loss' => (float) $m->profit_loss,
            'status' => $m->status,
            // Untuk badge peringatan di UI drilldown bila data bulan ini mencurigakan
            'is_anomaly' => (bool) $m->is_anomaly,
        ])->values();

        // Bulan-bulan tanpa data = rentang periode dataset minus bulan dimiliki.
        $ownedKeys = $metrics->map(fn ($m) => $m->tahun * 100 + $m->bulan)->all();
        $missingMonths = collect($this->summaryService->periodRange())
            ->reject(fn (array $p) => in_array($p['tahun'] * 100 + $p['bulan'], $ownedKeys, true))
            ->map(fn (array $p) => [
                'bulan' => $p['bulan'],
                'tahun' => $p['tahun'],
                'periode' => $this->periodLabel($p['bulan'], $p['tahun']),
            ])
            ->values();

        return response()->json([
            'data' => [
                'site_id' => $site->site_id,
                'site_name' => $site->site_name,
                'region' => $site->region === null ? null : [
                    'kode' => $site->region->kode,
                    'nama' => $site->region->nama,
                ],
                'total_revenue' => (float) $metrics->sum('revenue'),
                'total_cost' => (float) $metrics->sum('cost'),
                'total_profit_loss' => (float) $metrics->sum('profit_loss'),
                'months_with_data' => $metrics->count(),
                'history' => $history,
                'missing_months' => $missingMonths,
                'missing_months_count' => $missingMonths->count(),
            ],
        ]);
    }

    /**
     * Ambil (bulan, tahun) dari query string; jika tidak lengkap,
     * gunakan periode terbaru yang ada di data.
     *
     * @return array{0: int, 1: int}
     */
    private function resolvePeriod(Request $request): array
    {
        $validated = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        if (isset($validated['bulan'], $validated['tahun'])) {
            return [(int) $validated['bulan'], (int) $validated['tahun']];
        }

        return $this->summaryService->latestPeriod();
    }

    /**
     * Normalisasi parameter status (case-insensitive, toleran separator).
     * Valid: Profit, Loss, TidakAktif.
     */
    private function normalizeStatus(string $status): string
    {
        $key = strtolower(preg_replace('/[^a-z]/i', '', $status));

        return match ($key) {
            'profit' => 'Profit',
            'loss' => 'Loss',
            'tidakaktif' => 'TidakAktif',
            default => abort(
                422,
                "Status tidak valid: '{$status}'. Gunakan Profit, Loss, atau TidakAktif."
            ),
        };
    }

    /**
     * Bentuk satu baris site untuk list endpoint 2.
     * $metric null -> site Tidak Aktif (nilai keuangan null).
     */
    private function formatSiteRow(Site $site, ?Site $metric): array
    {
        return [
            'site_id' => $site->site_id,
            'site_name' => $site->site_name,
            'region' => $site->region === null ? null : [
                'kode' => $site->region->kode,
                'nama' => $site->region->nama,
            ],
            'revenue' => $metric === null ? null : (float) $metric->revenue,
            'cost' => $metric === null ? null : (float) $metric->cost,
            'profit_loss' => $metric === null ? null : (float) $metric->profit_loss,
        ];
    }

    /**
     * Label periode, misal "Jan 2025" (konsisten dengan accessor model).
     */
    private function periodLabel(int $bulan, int $tahun): string
    {
        return Carbon::create($tahun, $bulan, 1)->format('M Y');
    }
}
