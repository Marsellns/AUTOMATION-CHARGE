<?php

namespace App\Http\Controllers;

use App\Exports\AnomaliTagihanInbuildingExport;
use App\Models\AnomaliTagihanInbuilding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ElectricityInbuildingAnomaliController extends Controller
{
    public function index(): View
    {
        $years = [2026, 2025, 2024];

        return view('electricity.inbuilding.anomali.index', compact('years'));
    }

    public function data(Request $request): JsonResponse
    {
        // THRESHOLD ANOMALI: Kenaikan (%) > 50%
        $query = AnomaliTagihanInbuilding::query()
            ->where('kenaikan_persen', '>', 50);

        if ($request->filled('tahun') && is_numeric($request->tahun) && $request->filled('bulan') && is_numeric($request->bulan)) {
            $code = sprintf('%04d-%02d', (int)$request->tahun, (int)$request->bulan);
            $query->where('periode_saat_ini', $code);
        } elseif ($request->filled('tahun') && is_numeric($request->tahun)) {
            $query->where('periode_saat_ini', 'like', (int)$request->tahun . '-%');
        } elseif ($request->filled('bulan') && is_numeric($request->bulan)) {
            $mCode = sprintf('-%02d', (int)$request->bulan);
            $query->where('periode_saat_ini', 'like', '%' . $mCode);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('tagihan_sebelumnya', fn (AnomaliTagihanInbuilding $a) => 'Rp ' . number_format((float)$a->tagihan_sebelumnya, 0, ',', '.'))
            ->editColumn('tagihan_saat_ini', fn (AnomaliTagihanInbuilding $a) => 'Rp ' . number_format((float)$a->tagihan_saat_ini, 0, ',', '.'))
            ->editColumn('selisih', fn (AnomaliTagihanInbuilding $a) => 'Rp ' . number_format((float)$a->selisih, 0, ',', '.'))
            ->editColumn('kenaikan_persen', fn (AnomaliTagihanInbuilding $a) => '<span class="badge badge-loss">+' . number_format((float)$a->kenaikan_persen, 1) . '%</span>')
            ->rawColumns(['kenaikan_persen'])
            ->toJson();
    }

    public function exportExcel(Request $request)
    {
        $bulan = $request->filled('bulan') ? (int) $request->bulan : null;
        $tahun = $request->filled('tahun') ? (int) $request->tahun : null;

        $filename = 'anomali_tagihan_inbuilding_' . now()->format('Ymd_His') . '.xlsx';
        return (new AnomaliTagihanInbuildingExport($bulan, $tahun))->download($filename);
    }
}
