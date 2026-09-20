<?php

namespace App\Http\Controllers;

use App\Exports\DataSiteAllResourceExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Support\CityClassifier;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class DataPotensiSiteController extends Controller
{
    public function index(): View
    {
        $owners = DB::table('data_site_all_resources')
            ->whereNotNull('ant_site_owner')
            ->whereRaw("TRIM(ant_site_owner) <> ''")
            ->selectRaw('TRIM(ant_site_owner) AS ant_site_owner')
            ->distinct()
            ->orderBy('ant_site_owner')
            ->pluck('ant_site_owner');

        $cities = CityClassifier::options(DB::table('data_site_all_resources'));
        $nops = DB::table('data_site_all_resources')
            ->whereNotNull('nop')
            ->whereRaw("TRIM(nop) <> ''")
            ->selectRaw('TRIM(nop) AS nop')
            ->distinct()
            ->orderBy('nop')
            ->pluck('nop');

        return view('data-potensi.data-site.index', compact('owners', 'cities', 'nops'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = $this->filteredQuery($request);

        return DataTables::of($query)
            ->addIndexColumn()
            ->toJson();
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(
            new DataSiteAllResourceExport($this->filteredQuery($request)),
            'data-site-all-resource-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function show(string $siteId): JsonResponse
    {
        $row = $this->filteredQuery(new Request)
            ->whereRaw('UPPER(TRIM(site_id)) = ?', [strtoupper(trim($siteId))])
            ->first();

        if (!$row) {
            abort(404);
        }

        return response()->json(['data' => $row]);
    }

    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->input('search.value', $request->input('search', '')));
        $searchableColumns = [
            'site_id', 'site_name', 'site_class', 'city', 'nop', 'coverage_type',
            'pln_connection', 'capacity', 'id_pel', 'tgl_update', 'ant_site',
            'ant_site_owner', 'ant_rtp', 'ant_type', 'ant_alamat', 'ant_tgl_update',
            'ipas_contract', 'ipas_contract_type',
        ];

        return DB::table('data_site_all_resources')
            ->select($searchableColumns)
            ->when($request->filled('site_owner'), fn ($query) => $query->whereRaw(
                'UPPER(TRIM(ant_site_owner)) = ?',
                [strtoupper(trim((string) $request->input('site_owner')))]
            ))
            ->when($request->filled('city'), fn ($query) => $query->whereRaw(
                CityClassifier::expression('city') . ' = ?',
                [CityClassifier::normalize($request->input('city'))]
            ))
            ->when($request->filled('nop'), fn ($query) => $query->whereRaw(
                'UPPER(TRIM(nop)) = ?',
                [strtoupper(trim((string) $request->input('nop')))]
            ))
            ->when($search !== '', function ($query) use ($search, $searchableColumns) {
                $query->where(function ($searchQuery) use ($search, $searchableColumns) {
                    foreach ($searchableColumns as $column) {
                        $searchQuery->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->orderBy('site_id');
    }
}
