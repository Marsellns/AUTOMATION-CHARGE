<?php

namespace App\Http\Controllers;

use App\Models\DataSiteUnlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class DataSiteUnlockController extends Controller
{
    public function index(): View
    {
        return view('infrastruktur.site-unlock.index');
    }

    public function data(Request $request): JsonResponse
    {
        return DataTables::of(DataSiteUnlock::query()->latest('id'))
            ->addIndexColumn()
            ->editColumn('tanggal', fn (DataSiteUnlock $d) => $d->tanggal?->format('Y-m-d') ?? '-')
            ->toJson();
    }
}
