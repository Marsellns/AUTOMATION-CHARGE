<?php

namespace App\Http\Controllers;

use App\Exports\JaknetContractExport;
use App\Models\JaknetContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class JaknetContractController extends Controller
{
    public function index(): View
    {
        return view('infrastruktur.jaknet.index');
    }

    /**
     * Server-side DataTables.
     * - hide_unlock=1 → filter out rows where is_site_unlock = true.
     */
    public function data(Request $request): JsonResponse
    {
        $query = JaknetContract::query()->latest('id');

        if ($request->boolean('hide_unlock')) {
            $query->where(function ($q) {
                $q->where('is_site_unlock', false)->orWhereNull('is_site_unlock');
            });
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('tanggal_mulai', fn (JaknetContract $j) => $j->tanggal_mulai?->format('Y-m-d') ?? '-')
            ->editColumn('tanggal_berakhir', fn (JaknetContract $j) => $j->tanggal_berakhir?->format('Y-m-d') ?? '-')
            ->toJson();
    }

    public function exportExcel(Request $request)
    {
        $hideUnlock = $request->boolean('hide_unlock');
        $filename = 'jaknet_dapot' . ($hideUnlock ? '_no_unlock' : '_all') . '.xlsx';

        return (new JaknetContractExport($hideUnlock))->download($filename);
    }
}
