<?php

namespace App\Http\Controllers;

use App\Exports\InbuildingAllExport;
use App\Models\InbuildingAll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ElectricityInbuildingAllController extends Controller
{
    public function index(): View
    {
        $years = InbuildingAll::query()
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->toArray();

        if (empty($years)) {
            $years = [2026, 2025, 2024];
        }

        return view('electricity.inbuilding.inbuilding-all.index', compact('years'));
    }

    public function data(Request $request): JsonResponse
    {
        $tahun = $request->filled('tahun') ? (int) $request->tahun : 2025;
        $query = InbuildingAll::query()->where('tahun', $tahun);

        $fmt = fn($val) => (float)$val > 0 ? 'Rp ' . number_format((float)$val, 0, ',', '.') : '<span class="text-muted">-</span>';

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('status', function (InbuildingAll $row) {
                $isAktif = str_contains(strtolower($row->status ?? 'active'), 'active') || str_contains(strtolower($row->status ?? 'aktif'), 'aktif');
                return '<span class="badge ' . ($isAktif ? 'badge-profit' : 'badge-loss') . '">' . e(ucfirst($row->status ?? 'Active')) . '</span>';
            })
            ->editColumn('jan', fn (InbuildingAll $l) => $fmt($l->jan))
            ->editColumn('feb', fn (InbuildingAll $l) => $fmt($l->feb))
            ->editColumn('mar', fn (InbuildingAll $l) => $fmt($l->mar))
            ->editColumn('apr', fn (InbuildingAll $l) => $fmt($l->apr))
            ->editColumn('mei', fn (InbuildingAll $l) => $fmt($l->mei))
            ->editColumn('jun', fn (InbuildingAll $l) => $fmt($l->jun))
            ->editColumn('jul', fn (InbuildingAll $l) => $fmt($l->jul))
            ->editColumn('ags', fn (InbuildingAll $l) => $fmt($l->ags))
            ->editColumn('sep', fn (InbuildingAll $l) => $fmt($l->sep))
            ->editColumn('okt', fn (InbuildingAll $l) => $fmt($l->okt))
            ->editColumn('nov', fn (InbuildingAll $l) => $fmt($l->nov))
            ->editColumn('des', fn (InbuildingAll $l) => $fmt($l->des))
            ->rawColumns(['status', 'jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'ags', 'sep', 'okt', 'nov', 'des'])
            ->toJson();
    }

    public function exportExcel(Request $request)
    {
        $tahun = $request->filled('tahun') ? (int) $request->tahun : 2025;

        $filename = 'inbuilding_all_' . $tahun . '_' . now()->format('Ymd_His') . '.xlsx';
        return (new InbuildingAllExport($tahun))->download($filename);
    }
}
