<?php

namespace App\Http\Controllers;

use App\Exports\SiteOwnerExport;
use App\Models\SiteOwner;
use App\Support\CityClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class DataPotensiSiteOwnerController extends Controller
{
    public function index(): View
    {
        $owners = SiteOwner::query()
            ->whereNotNull('site_owner')
            ->whereRaw("TRIM(site_owner) <> ''")
            ->selectRaw('TRIM(site_owner) AS site_owner')
            ->distinct()
            ->orderBy('site_owner')
            ->pluck('site_owner');

        $cities = CityClassifier::options(SiteOwner::query());

        return view('data-potensi.site-owner.index', compact('owners', 'cities'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = $this->filteredQuery($request)
            ->select(['id', 'site_code', 'site_name', 'site_class', 'site_owner']);

        return DataTables::of($query)
            ->filter(function ($query) use ($request) {
                $search = trim((string) $request->input('search.value', ''));
                if ($search !== '') {
                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery
                            ->where('site_code', 'like', "%{$search}%")
                            ->orWhere('site_name', 'like', "%{$search}%");
                    });
                }
            }, true)
            ->addIndexColumn()
            ->toJson();
    }

    public function show(SiteOwner $siteOwner): JsonResponse
    {
        $data = $siteOwner->only([
            'site_code', 'site_name', 'site_class', 'alamat', 'city', 'nop',
            'coverage_type', 'status_mla', 'pln_connection', 'capacity',
            'id_pel', 'tower_height', 'site_owner', 'tgl_update',
        ]);
        $data['tgl_update'] = $siteOwner->tgl_update?->format('Y-m-d');

        return response()->json([
            'data' => $data,
        ]);
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(
            new SiteOwnerExport($this->filteredQuery($request)),
            'site-owner-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->input('search.value', $request->input('search', '')));
        $siteOwner = trim((string) $request->input('site_owner', ''));
        $nop = trim((string) $request->input('nop', ''));

        return SiteOwner::query()
            ->when($siteOwner !== '', function ($query) use ($siteOwner) {
                if (strcasecmp($siteOwner, 'Belum teridentifikasi') === 0) {
                    return $query->where(function ($ownerQuery) {
                        $ownerQuery
                            ->whereNull('site_owner')
                            ->orWhereRaw("TRIM(site_owner) = ''");
                    });
                }

                return $query->whereRaw(
                    'UPPER(TRIM(site_owner)) = ?',
                    [strtoupper($siteOwner)]
                );
            })
            ->when($nop !== '' && strcasecmp($nop, 'all') !== 0, fn ($q) => $q->whereRaw(
                'UPPER(TRIM(nop)) = ?',
                [strtoupper($nop)]
            ))
            ->when($request->filled('city'), fn ($q) => $q->whereRaw(
                CityClassifier::expression('city').' = ?',
                [CityClassifier::normalize($request->input('city'))]
            ))
            ->when($search !== '', fn ($q) => $q->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('site_code', 'like', "%{$search}%")
                    ->orWhere('site_name', 'like', "%{$search}%");
            }))
            ->latest('id');
    }
}
