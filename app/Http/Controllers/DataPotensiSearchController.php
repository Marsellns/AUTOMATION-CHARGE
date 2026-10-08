<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Support\CityClassifier;
use Yajra\DataTables\Facades\DataTables;

class DataPotensiSearchController extends Controller
{
    public function index(): View
    {
        $owners = DB::table('recurring_ipas')
            ->whereNotNull('site_owner')
            ->whereRaw("TRIM(site_owner) <> ''")
            ->selectRaw('TRIM(site_owner) AS ant_site_owner')
            ->distinct()
            ->orderBy('ant_site_owner')
            ->pluck('ant_site_owner');

        $cities = CityClassifier::options(DB::table('site_owners'));
        $periods = DB::table('site_monthly_metrics')->select('tahun', 'bulan')
            ->distinct()->orderByDesc('tahun')->orderByDesc('bulan')->get();

        return view('data-potensi.search-all-resource.index', compact('owners', 'cities', 'periods'));
    }

    public function data(Request $request): JsonResponse
    {
        return DataTables::of($this->filteredQuery($request))
            ->addIndexColumn()
            ->toJson();
    }

    public function show(Request $request, string $siteId): JsonResponse
    {
        $row = $this->filteredQuery($request)
            ->whereRaw('UPPER(TRIM(site_id)) = ?', [strtoupper(trim($siteId))])
            ->first();

        if (!$row) {
            abort(404);
        }

        return response()->json(['data' => $row]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $validated = $request->validate(['periode' => ['nullable', 'date_format:Y-m']]);
        $period = $validated['periode'] ?? null;
        if ($period === null) {
            $latest = DB::table('site_monthly_metrics')->orderByDesc('tahun')->orderByDesc('bulan')->first(['tahun', 'bulan']);
            $period = $latest ? sprintf('%04d-%02d', $latest->tahun, $latest->bulan) : null;
        }
        [$year, $month] = $period ? array_map('intval', explode('-', $period)) : [0, 0];
        $search = trim((string) $request->input('search.value', $request->input('search', '')));
        $columns = [
            'site_id', 'site_name', 'site_class', 'city', 'nop', 'coverage_type',
            'pln_connection', 'capacity', 'id_pel', 'ant_site', 'ant_site_owner',
            'ant_rtp', 'ant_type', 'ant_tgl_update', 'ant_alamat', 'revenue',
            'cost', 'profit_value', 'profit_status', 'rev_site',
        ];

        // Search displays DAPOT/ANT resources, not IPAS invoice histories.
        // Query those sources directly to avoid multiplying every resource
        // by every invoice. Identical displayed resources appear once.
        $resources = DB::table('site_owners as so')
            ->leftJoin('recurring_ipas as ri', 'ri.site_code', '=', DB::raw('UPPER(TRIM(so.site_code))'))
            ->leftJoin('sites as s', 's.site_id', '=', DB::raw('UPPER(TRIM(so.site_code))'))
            ->leftJoin('site_monthly_metrics as m', function ($join) use ($year, $month): void {
                $join->on('m.site_id', '=', 's.id')->where('m.tahun', $year)->where('m.bulan', $month);
            })
            ->select([
                'so.site_code as site_id', 'so.site_name', 'so.site_class', 'so.city', 'so.nop',
                'so.coverage_type', 'so.pln_connection', 'so.capacity', 'so.id_pel',
                'ri.site_name as ant_site', 'ri.site_owner as ant_site_owner', 'ri.rtp as ant_rtp',
                'ri.tgl_update as ant_tgl_update', 'ri.alamat as ant_alamat',
            ])
            ->selectRaw('NULL AS ant_type')
            ->selectRaw('CASE WHEN m.id IS NOT NULL AND m.is_anomaly = 0 THEN s.site_id END AS rev_site')
            ->selectRaw('CASE WHEN m.is_anomaly = 0 THEN m.revenue END AS revenue')
            ->selectRaw('CASE WHEN m.is_anomaly = 0 THEN m.cost END AS cost')
            ->selectRaw('CASE WHEN m.is_anomaly = 0 THEN m.revenue - m.cost END AS profit_value')
            ->selectRaw("CASE WHEN m.id IS NULL THEN 'Tidak tersedia' WHEN m.is_anomaly <> 0 THEN 'Anomali' WHEN m.revenue - m.cost > 0 THEN 'Profit' ELSE 'Loss' END AS profit_status")
            ->selectRaw('? AS periode', [$period])
            ->distinct();

        return DB::query()->fromSub($resources, 'resources')
            ->select(array_merge($columns, ['periode']))
            ->when($request->filled('site_owner'), fn ($query) => $query->whereRaw(
                'UPPER(TRIM(ant_site_owner)) = ?',
                [strtoupper(trim((string) $request->input('site_owner')))]
            ))
            ->when($request->filled('city'), fn ($query) => $query->whereRaw(
                CityClassifier::expression('city') . ' = ?',
                [CityClassifier::normalize($request->input('city'))]
            ))
            ->when($search !== '', function ($query) use ($search, $columns) {
                $query->where(function ($searchQuery) use ($search, $columns) {
                    foreach ($columns as $column) {
                        $searchQuery->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->orderBy('site_id');
    }
}
