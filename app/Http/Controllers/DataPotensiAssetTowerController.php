<?php

namespace App\Http\Controllers;

use App\Exports\DataAssetTowerExport;
use App\Models\DataAssetTower;
use App\Models\SiteOwner;
use App\Support\CityClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class DataPotensiAssetTowerController extends Controller
{
    public function index(): View
    {
        $nops = SiteOwner::query()
            ->whereNotNull('nop')
            ->whereRaw('TRIM(nop) <> ""')
            ->selectRaw('TRIM(nop) AS nop')
            ->distinct()
            ->orderBy('nop')
            ->pluck('nop');
        $cities = CityClassifier::options(SiteOwner::query());

        return view('data-potensi.asset-tower.index', compact('nops', 'cities'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = $this->filteredQuery($request)
            ->select([
                'id', 'site_id', 'site_name', 'site_company', 'site_type',
                'grouping', 'brand', 'part_name', 'owner',
            ]);

        return DataTables::of($query)
            ->addIndexColumn()
            ->toJson();
    }

    public function show(DataAssetTower $assetTower): JsonResponse
    {
        return response()->json([
            'data' => [
                'site_id' => $assetTower->site_id,
                'site_name' => $assetTower->site_name,
                'ownership_status' => $assetTower->ownership_status,
                'note' => $assetTower->note,
                'tower_height' => $assetTower->tower_height,
                'building_height' => $assetTower->building_height,
                'tower_type' => $assetTower->tower_type,
                'update_by' => $assetTower->update_by,
                'tanggal_update' => $assetTower->tanggal_update?->format('Y-m-d'),
            ],
        ]);
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(
            new DataAssetTowerExport($this->filteredQuery($request)),
            'data-asset-tower-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->input('search.value', $request->input('search', '')));

        return DataAssetTower::query()
            ->when($request->filled('nop'), fn ($query) => $query->whereExists(
                fn ($subquery) => $subquery
                    ->selectRaw('1')
                    ->from('site_owners')
                    ->whereRaw('UPPER(TRIM(site_owners.site_code)) = UPPER(TRIM(data_asset_towers.site_id))')
                    ->whereRaw('UPPER(TRIM(site_owners.nop)) = ?', [strtoupper(trim((string) $request->input('nop')))])
            ))
            ->when($request->filled('city'), fn ($query) => $query->whereExists(
                fn ($subquery) => $subquery
                    ->selectRaw('1')
                    ->from('site_owners')
                    ->whereRaw('UPPER(TRIM(site_owners.site_code)) = UPPER(TRIM(data_asset_towers.site_id))')
                    ->whereRaw(CityClassifier::expression('site_owners.city') . ' = ?', [CityClassifier::normalize($request->input('city'))])
            ))
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('site_id', 'like', "%{$search}%")
                    ->orWhere('site_name', 'like', "%{$search}%");
            }))
            ->orderBy('id');
    }

}
