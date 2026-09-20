<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Support\CityClassifier;
use Yajra\DataTables\Facades\DataTables;

class DataPotensiSearchController extends Controller
{
    public function index(): View
    {
        $owners = DB::table('search_all_resources')
            ->whereNotNull('ant_site_owner')
            ->whereRaw("TRIM(ant_site_owner) <> ''")
            ->selectRaw('TRIM(ant_site_owner) AS ant_site_owner')
            ->distinct()
            ->orderBy('ant_site_owner')
            ->pluck('ant_site_owner');

        $cities = CityClassifier::options(DB::table('search_all_resources'));

        return view('data-potensi.search-all-resource.index', compact('owners', 'cities'));
    }

    public function data(Request $request): JsonResponse
    {
        return DataTables::of($this->filteredQuery($request))
            ->addIndexColumn()
            ->toJson();
    }

    public function show(Request $request, string $siteId): JsonResponse
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
        $columns = [
            'site_id', 'site_name', 'site_class', 'city', 'nop', 'coverage_type',
            'pln_connection', 'capacity', 'id_pel', 'ant_site', 'ant_site_owner',
            'ant_rtp', 'ant_type', 'ant_tgl_update', 'ant_alamat', 'revenue',
            'cost', 'profit_value', 'profit_status', 'rev_site',
        ];

        return DB::table('search_all_resources')
            ->select($columns)
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
