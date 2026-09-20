<?php

namespace App\Http\Controllers;

use App\Exports\PnlExport;
use App\Imports\SimawarPnLImport;
use App\Models\Site;
use App\Models\SiteMonthlyMetric;
use App\Models\SiteOwner;
use App\Services\SiteStatusSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

class PnlViewController extends Controller
{
    /**
     * Tampilan Halaman Utama Data Profit & Loss.
     */
    public function index(Request $request): View
    {
        $periods = $this->getAvailablePeriods();
        $selectedStatus = $request->query('status', '');
        $nops = $this->getAvailableNops();
        $selectedNop = trim((string) $request->query('nop', ''));
        if ($selectedNop !== '') {
            foreach ($nops as $n) {
                if (strcasecmp($n, $selectedNop) === 0) {
                    $selectedNop = $n;
                    break;
                }
            }
        }
        if (!in_array($selectedNop, $nops, true)) {
            $selectedNop = '';
        }

        // Deteksi apakah request "semua bulan" (bulan=all atau bulan kosong saat ada tahun)
        $bulanInput = $request->query('bulan', '');
        $selectedAllMonths = ($bulanInput === 'all');
        $selectedPeriod = $this->resolveSelectedPeriod($request, $periods, $selectedAllMonths);

        return view('pnl.index', compact('periods', 'selectedStatus', 'selectedPeriod', 'nops', 'selectedNop', 'selectedAllMonths'));
    }

    public function uploadPage(): View
    {
        return view('pnl.upload');
    }

    /**
     * Import file Excel PnL terbaru dan langsung memperbarui data dashboard.
     */
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'pnl_file' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
        ], [
            'pnl_file.required' => 'Silakan pilih file Excel PnL terlebih dahulu.',
            'pnl_file.mimes' => 'File harus berformat .xlsx atau .xls.',
            'pnl_file.max' => 'Ukuran file maksimal 50 MB.',
        ]);

        $import = new SimawarPnLImport();
        Excel::import($import, $validated['pnl_file']);

        app(SiteStatusSummaryService::class)->invalidateCache();

        $stats = $import->getStats();

        return redirect()
            ->route('pnl.index')
            ->with('success', sprintf(
                'Upload berhasil. %s baris site diproses dan %s metrik bulanan diperbarui.',
                number_format($stats['processed_rows']),
                number_format($stats['metrics_upserted'])
            ));
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $headers = ['Site ID', 'Site Name'];
        $months = [
            ['Jan', '25'], ['Feb', '25'], ['Mar', '25'], ['Apr', '25'], ['May', '25'], ['Jun', '25'],
            ['Jul', '25'], ['Aug', '25'], ['Sep', '25'], ['Oct', '25'], ['Nov', '25'], ['Dec', '25'],
            ['Jan', '26'], ['Feb', '26'], ['Mar', '26'], ['Apr', '26'], ['May', '26'], ['Jun', '26'],
        ];
        $details = [
            'Opex Freq', 'Opex Isr', 'Opex Trans', 'Opex Power', 'Opex Rm',
            'Total Direct Dep', 'Rev Voice', 'Rev Sms', 'Rev Broath', 'Rev Digi', 'Rev Tapout',
        ];

        foreach ($months as [$month, $year]) {
            $period = "{$month}-{$year}";
            $headers[] = "Rev {$period}";
            $headers[] = "Cost {$period}";
            foreach ($details as $detail) {
                $headers[] = "{$detail} {$period}";
            }
        }

        return Excel::download(new class($headers) implements \Maatwebsite\Excel\Concerns\FromArray {
            public function __construct(private readonly array $headers) {}

            public function array(): array
            {
                return [
                    ['Simawar PnL - Template'],
                    $this->headers,
                ];
            }
        }, 'template-simawar-pnl.xlsx');
    }

    /**
     * DataTables Server-Side AJAX Endpoint.
     */
    public function data(Request $request): JsonResponse
    {
        $periods = $this->getAvailablePeriods();
        $defaultPeriod = $periods[0] ?? ['bulan' => 6, 'tahun' => 2026];

        $bulanInput = $request->query('bulan', '');
        $allMonths = ($bulanInput === 'all' || $bulanInput === '');
        $bulan = (!$allMonths && is_numeric($bulanInput)) ? (int) $bulanInput : null;
        $tahun = $request->filled('tahun') ? (int) $request->tahun : $defaultPeriod['tahun'];
        $status = $request->filled('status') ? trim((string)$request->status) : null;
        $nop = trim((string) $request->query('nop', '')) ?: null;
        $owner = trim((string) $request->query('owner', '')) ?: null;

        $revCol = 'm.revenue';
        $costCol = 'm.cost';
        $pnlCol = 'm.profit_loss';

        if ($status === 'TidakAktif' || $status === 'Tidak Aktif') {
            $query = Site::query()
                ->inactiveIn($bulan ?? 0, $tahun)
                ->leftJoin('site_owners as so', function ($join) {
                    $join->on(
                        DB::raw('UPPER(TRIM(so.site_code))'),
                        '=',
                        DB::raw('UPPER(TRIM(sites.site_id))')
                    );
                })
                ->when($nop !== null, fn ($query) => $query->whereRaw('UPPER(TRIM(so.nop)) = ?', [strtoupper($nop)]))
                ->select(
                    'sites.id as id',
                    'sites.site_id',
                    'sites.site_name',
                    'so.nop as nop',
                    'so.alamat as alamat'
                )
                ->selectRaw('NULL as revenue, NULL as cost, NULL as profit_loss');

            return DataTables::of($query)
                ->addIndexColumn()
                ->filterColumn('nop', fn ($q, $keyword) => $q->where('so.nop', 'like', "%{$keyword}%"))
                ->filterColumn('site_id', fn ($q, $keyword) => $q->where('sites.site_id', 'like', "%{$keyword}%"))
                ->filterColumn('site_name', fn ($q, $keyword) => $q->where('sites.site_name', 'like', "%{$keyword}%"))
                // Kolom finansial untuk site tidak aktif selalu NULL. Override
                // pencarian bawaan agar DataTables tidak mencoba `sites.revenue`.
                ->filterColumn('revenue', fn ($q, $keyword) => $q->whereRaw('1 = 0'))
                ->filterColumn('cost', fn ($q, $keyword) => $q->whereRaw('1 = 0'))
                ->filterColumn('profit_loss', fn ($q, $keyword) => $q->whereRaw('1 = 0'))
                ->filterColumn('status_badge', fn ($q, $keyword) => $q->whereRaw("'Tidak Aktif' LIKE ?", ["%{$keyword}%"]))
                ->orderColumn('nop', fn ($q, $dir) => $q->orderBy('so.nop', $dir))
                ->orderColumn('site_id', fn ($q, $dir) => $q->orderBy('sites.site_id', $dir))
                ->orderColumn('site_name', fn ($q, $dir) => $q->orderBy('sites.site_name', $dir))
                ->editColumn('revenue', fn (Site $s) => '<span class="text-muted">-</span>')
                ->editColumn('cost', fn (Site $s) => '<span class="text-muted">-</span>')
                ->editColumn('profit_loss', fn (Site $s) => '<span class="text-muted">-</span>')
                ->addColumn('status_badge', fn () => '<span class="badge badge-inactive">Tidak Aktif</span>')
                ->rawColumns(['revenue', 'cost', 'profit_loss', 'status_badge'])
                ->toJson();
        }

        if ($status === 'Profit') {
            $revCol = 'site_monthly_metrics.revenue';
            $costCol = 'site_monthly_metrics.cost';
            $pnlCol = 'site_monthly_metrics.profit_loss';

            if ($bulan === null) {
                $metricsBySite = SiteMonthlyMetric::query()
                    ->where('tahun', $tahun)
                    ->where('is_anomaly', 0)
                    ->selectRaw('site_id, SUM(revenue) as revenue, SUM(cost) as cost, SUM(profit_loss) as profit_loss')
                    ->groupBy('site_id');

                $query = Site::query()
                    ->leftJoinSub($metricsBySite, 'm', 'm.site_id', '=', 'sites.id')
                    ->where('m.profit_loss', '>', 0)
                    ->leftJoin('site_owners as so', function ($join) {
                        $join->on(
                            DB::raw('UPPER(TRIM(so.site_code))'),
                            '=',
                            DB::raw('UPPER(TRIM(sites.site_id))')
                        );
                    })
                    ->select('sites.id as id', 'sites.site_id', 'sites.site_name', 'so.nop as nop', 'm.revenue', 'm.cost', 'm.profit_loss');
                $revCol = 'm.revenue';
                $costCol = 'm.cost';
                $pnlCol = 'm.profit_loss';
            } else {
                $query = SiteMonthlyMetric::query()
                    ->where('site_monthly_metrics.bulan', $bulan)
                    ->where('site_monthly_metrics.tahun', $tahun)
                    ->where('site_monthly_metrics.profit_loss', '>', 0)
                    ->join('sites', 'sites.id', '=', 'site_monthly_metrics.site_id')
                    ->leftJoin('site_owners as so', function ($join) {
                        $join->on(
                            DB::raw('UPPER(TRIM(so.site_code))'),
                            '=',
                            DB::raw('UPPER(TRIM(sites.site_id))')
                        );
                    })
                    ->select('sites.id as id', 'sites.site_id', 'sites.site_name', 'so.nop as nop', 'site_monthly_metrics.revenue', 'site_monthly_metrics.cost', 'site_monthly_metrics.profit_loss', 'site_monthly_metrics.bulan');
            }
        } elseif ($status === 'Loss') {
            $revCol = 'site_monthly_metrics.revenue';
            $costCol = 'site_monthly_metrics.cost';
            $pnlCol = 'site_monthly_metrics.profit_loss';

            if ($bulan === null) {
                $metricsBySite = SiteMonthlyMetric::query()
                    ->where('tahun', $tahun)
                    ->where('is_anomaly', 0)
                    ->selectRaw('site_id, SUM(revenue) as revenue, SUM(cost) as cost, SUM(profit_loss) as profit_loss')
                    ->groupBy('site_id');

                $query = Site::query()
                    ->leftJoinSub($metricsBySite, 'm', 'm.site_id', '=', 'sites.id')
                    ->where('m.profit_loss', '<=', 0)
                    ->leftJoin('site_owners as so', function ($join) {
                        $join->on(
                            DB::raw('UPPER(TRIM(so.site_code))'),
                            '=',
                            DB::raw('UPPER(TRIM(sites.site_id))')
                        );
                    })
                    ->select('sites.id as id', 'sites.site_id', 'sites.site_name', 'so.nop as nop', 'm.revenue', 'm.cost', 'm.profit_loss');
                $revCol = 'm.revenue';
                $costCol = 'm.cost';
                $pnlCol = 'm.profit_loss';
            } else {
                $query = SiteMonthlyMetric::query()
                    ->where('site_monthly_metrics.bulan', $bulan)
                    ->where('site_monthly_metrics.tahun', $tahun)
                    ->where('site_monthly_metrics.profit_loss', '<=', 0)
                    ->join('sites', 'sites.id', '=', 'site_monthly_metrics.site_id')
                    ->leftJoin('site_owners as so', function ($join) {
                        $join->on(
                            DB::raw('UPPER(TRIM(so.site_code))'),
                            '=',
                            DB::raw('UPPER(TRIM(sites.site_id))')
                        );
                    })
                    ->select('sites.id as id', 'sites.site_id', 'sites.site_name', 'so.nop as nop', 'site_monthly_metrics.revenue', 'site_monthly_metrics.cost', 'site_monthly_metrics.profit_loss', 'site_monthly_metrics.bulan');
            }
        } else {
            // "Semua Status": tampilkan seluruh site (master Dapot), gabungkan metrik bulan tsb jika ada
            $query = Site::query();
            if ($bulan === null) {
                $metricsBySite = SiteMonthlyMetric::query()
                    ->where('tahun', $tahun)
                    ->where('is_anomaly', 0)
                    ->selectRaw('site_id, SUM(revenue) as revenue, SUM(cost) as cost, SUM(profit_loss) as profit_loss')
                    ->groupBy('site_id');
                $query->leftJoinSub($metricsBySite, 'm', 'm.site_id', '=', 'sites.id');
            } else {
                $query->leftJoin('site_monthly_metrics as m', function ($join) use ($bulan, $tahun) {
                    $join->on('m.site_id', '=', 'sites.id')
                        ->where('m.bulan', $bulan)
                        ->where('m.tahun', $tahun);
                });
            }
            $query
                ->leftJoin('site_owners as so', function ($join) {
                    $join->on(
                        DB::raw('UPPER(TRIM(so.site_code))'),
                        '=',
                        DB::raw('UPPER(TRIM(sites.site_id))')
                    );
                })
                ->select(
                    'sites.id as id',
                    'sites.site_id',
                    'sites.site_name',
                    'so.nop as nop',
                    'm.revenue',
                    'm.cost',
                    'm.profit_loss'
                );
        }

        if ($nop !== null) {
            $query->whereRaw('UPPER(TRIM(so.nop)) = ?', [strtoupper($nop)]);
        }

        if ($owner !== null) {
            if (strcasecmp($owner, 'Belum teridentifikasi') === 0) {
                $query->where(function ($ownerQuery) {
                    $ownerQuery
                        ->whereNull('so.site_owner')
                        ->orWhereRaw("TRIM(so.site_owner) = ''");
                });
            } else {
                $query->whereRaw('UPPER(TRIM(so.site_owner)) = ?', [strtoupper($owner)]);
            }
        }

        $query->addSelect('so.alamat as alamat');

        return DataTables::of($query)
            ->addIndexColumn()
            ->filterColumn('nop', fn ($q, $keyword) => $q->where('so.nop', 'like', "%{$keyword}%"))
            ->filterColumn('site_id', fn ($q, $keyword) => $q->where('sites.site_id', 'like', "%{$keyword}%"))
            ->filterColumn('site_name', fn ($q, $keyword) => $q->where('sites.site_name', 'like', "%{$keyword}%"))
            // Hasil query memakai alias metrik berbeda untuk tiap status
            // (`m` atau `site_monthly_metrics`). Karena itu pencarian harus
            // di-override; pencarian bawaan Eloquent akan mencari
            // `sites.revenue`, `sites.cost`, dan `sites.profit_loss`.
            ->filterColumn('revenue', fn ($q, $keyword) => $q->whereRaw("CAST({$revCol} AS CHAR) LIKE ?", ["%{$keyword}%"]))
            ->filterColumn('cost', fn ($q, $keyword) => $q->whereRaw("CAST({$costCol} AS CHAR) LIKE ?", ["%{$keyword}%"]))
            ->filterColumn('profit_loss', fn ($q, $keyword) => $q->whereRaw("CAST({$pnlCol} AS CHAR) LIKE ?", ["%{$keyword}%"]))
            ->filterColumn('status_badge', fn ($q, $keyword) => $q->whereRaw(
                "CASE WHEN {$pnlCol} IS NULL THEN 'Tidak Aktif' WHEN {$pnlCol} > 0 THEN 'Profit' ELSE 'Loss' END LIKE ?",
                ["%{$keyword}%"]
            ))
            ->orderColumn('nop', fn ($q, $dir) => $q->orderBy('so.nop', $dir))
            ->orderColumn('site_id', fn ($q, $dir) => $q->orderBy('sites.site_id', $dir))
            ->orderColumn('site_name', fn ($q, $dir) => $q->orderBy('sites.site_name', $dir))
            ->orderColumn('revenue', fn ($q, $dir) => $q->orderBy($revCol, $dir))
            ->orderColumn('cost', fn ($q, $dir) => $q->orderBy($costCol, $dir))
            ->orderColumn('profit_loss', fn ($q, $dir) => $q->orderBy($pnlCol, $dir))
            ->editColumn('revenue', fn ($s) => $s->revenue !== null ? 'Rp ' . number_format($s->revenue, 0, ',', '.') : '<span class="text-muted">-</span>')
            ->editColumn('cost', fn ($s) => $s->cost !== null ? 'Rp ' . number_format($s->cost, 0, ',', '.') : '<span class="text-muted">-</span>')
            ->addColumn('status_badge', function ($s) {
                if ($s->profit_loss === null) {
                    return '<span class="badge badge-inactive">Tidak Aktif</span>';
                }
                if ($s->profit_loss > 0) {
                    return '<span class="badge badge-profit">Profit</span>';
                }
                return '<span class="badge badge-loss">Loss</span>';
            })
            ->editColumn('profit_loss', function ($s) {
                if ($s->profit_loss === null) return '<span class="text-muted">-</span>';
                $val = (float) $s->profit_loss;
                $cls = $val > 0 ? 'text-profit' : 'text-loss';
                return '<span class="' . $cls . '">Rp ' . number_format($val, 0, ',', '.') . '</span>';
            })
            ->rawColumns(['revenue', 'cost', 'profit_loss', 'status_badge'])
            ->toJson();
    }

    /**
     * Modal detail history bulanan per site.
     */
    public function siteHistory(string $id): JsonResponse
    {
        $site = is_numeric($id) ? Site::find($id) : Site::where('site_id', $id)->first();
        if (!$site) {
            return response()->json(['message' => 'Site tidak ditemukan.'], 404);
        }

        $metrics = SiteMonthlyMetric::query()
            ->where('site_id', $site->id)
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->get();

        $bulanNamaShort = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
        ];

        $history = [];
        $totalRevenue = 0;
        $totalCost = 0;
        $totalPnL = 0;

        foreach ($metrics as $m) {
            $rev = (float) $m->revenue;
            $cost = (float) $m->cost;
            $pnl = (float) $m->profit_loss;

            if (!$m->is_anomaly) {
                $totalRevenue += $rev;
                $totalCost += $cost;
                $totalPnL += $pnl;
            }

            $label = ($bulanNamaShort[(int)$m->bulan] ?? $m->bulan) . ' ' . $m->tahun;

            $history[] = [
                'bulan'       => (int) $m->bulan,
                'tahun'       => (int) $m->tahun,
                'bulan_label' => $label,
                'revenue'     => $rev,
                'cost'        => $cost,
                'cost_details' => [
                    'OpexFreq' => $m->opex_freq === null ? null : (float) $m->opex_freq,
                    'Opex ISR' => $m->opex_isr === null ? null : (float) $m->opex_isr,
                    'Opex Trans' => $m->opex_trans === null ? null : (float) $m->opex_trans,
                    'Opex Power' => $m->opex_power === null ? null : (float) $m->opex_power,
                    'Opex RM' => $m->opex_rm === null ? null : (float) $m->opex_rm,
                    'Total Direct Dep' => $m->total_direct_dep === null ? null : (float) $m->total_direct_dep,
                ],
                'revenue_details' => [
                    'RevVoice' => $m->rev_voice === null ? null : (float) $m->rev_voice,
                    'RevSMS' => $m->rev_sms === null ? null : (float) $m->rev_sms,
                    'RevBroath' => $m->rev_broath === null ? null : (float) $m->rev_broath,
                    'RevDigi' => $m->rev_digi === null ? null : (float) $m->rev_digi,
                    'RevTapout' => $m->rev_tapout === null ? null : (float) $m->rev_tapout,
                ],
                'profit_loss' => $pnl,
                'status'      => $pnl > 0 ? 'Profit' : 'Loss',
                'is_anomaly'  => (bool) $m->is_anomaly,
            ];
        }

        $nop = SiteOwner::query()
            ->whereRaw('UPPER(TRIM(site_code)) = ?', [strtoupper(trim($site->site_id))])
            ->value('nop');

        return response()->json([
            'site' => [
                'id'        => $site->id,
                'site_id'   => $site->site_id,
                'site_name' => $site->site_name,
                'nop'       => $nop ?: '-',
            ],
            'totals' => [
                'total_revenue' => $totalRevenue,
                'total_cost'    => $totalCost,
                'total_pnl'     => $totalPnL,
                'months_count'  => count($metrics),
            ],
            'history' => $history,
        ]);
    }

    /**
     * Download / Export Excel Data PnL.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $periods = $this->getAvailablePeriods();
        $defaultPeriod = $periods[0] ?? ['bulan' => 6, 'tahun' => 2026];

        $bulanInput = $request->query('bulan', '');
        $allMonths = ($bulanInput === 'all' || $bulanInput === '');
        $bulan = (!$allMonths && is_numeric($bulanInput)) ? (int) $bulanInput : null;
        $tahun = $request->filled('tahun') ? (int) $request->tahun : $defaultPeriod['tahun'];
        $status = $request->filled('status') ? trim((string)$request->status) : null;
        $nop = trim((string) $request->query('nop', '')) ?: null;

        $bulanLabel = $bulan !== null ? "_{$bulan}" : '_SemuaBulan';
        $filename = "PnL_Data_{$tahun}{$bulanLabel}" . ($nop ? '_' . str_replace(' ', '_', $nop) : '') . ($status ? "_{$status}" : "") . ".xlsx";

        return Excel::download(new PnlExport($bulan, $tahun, $status, $nop), $filename);
    }

    /**
     * Daftar periode yang tersedia di database, terurut terbaru terlebih dahulu.
     */
    private function getAvailablePeriods(): array
    {
        $bulanNama = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return SiteMonthlyMetric::query()
            ->select('bulan', 'tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->get()
            ->map(fn ($period) => [
                'bulan' => (int) $period->bulan,
                'tahun' => (int) $period->tahun,
                'label' => "{$bulanNama[(int) $period->bulan]} {$period->tahun}",
            ])
            ->all();
    }

    /** Daftar NOP unik dari master Site Owner/Dapot. */
    private function getAvailableNops(): array
    {
        return SiteOwner::query()
            ->whereNotNull('nop')
            ->whereRaw("TRIM(nop) <> ''")
            ->selectRaw('DISTINCT TRIM(nop) as nop')
            ->orderBy('nop')
            ->pluck('nop')
            ->all();
    }

    /** Pilihan periode dari dashboard hanya dipakai bila memang tersedia. */
    private function resolveSelectedPeriod(Request $request, array $periods, bool $allMonths = false): string
    {
        $bulan = $request->integer('bulan');
        $tahun = $request->integer('tahun');

        // Jika all months diminta, kembalikan 0-TAHUN agar JS bisa mendeteksi "Semua Bulan"
        if ($allMonths && $tahun > 0) {
            foreach ($periods as $period) {
                if ($period['tahun'] === $tahun) {
                    return "0-{$tahun}";
                }
            }
        }

        if ($bulan > 0 && $tahun > 0) {
            foreach ($periods as $period) {
                if ($period['bulan'] === $bulan && $period['tahun'] === $tahun) {
                    return "{$bulan}-{$tahun}";
                }
            }
        }

        if ($tahun > 0) {
            foreach ($periods as $period) {
                if ($period['tahun'] === $tahun) {
                    return "{$period['bulan']}-{$tahun}";
                }
            }
        }

        $default = $periods[0] ?? ['bulan' => 6, 'tahun' => 2026];

        return "{$default['bulan']}-{$default['tahun']}";
    }
}
