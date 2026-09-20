<?php

namespace App\Http\Controllers;

use App\Models\RecurringTagihanIpas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class RecurringTagihanIpasController extends Controller
{
    public function index(): View
    {
        // Daftar TP unik untuk dropdown filter
        $tpList = RecurringTagihanIpas::query()
            ->whereNotNull('tp')
            ->where('tp', '!=', '')
            ->distinct()
            ->orderBy('tp')
            ->pluck('tp');

        return view('infrastruktur.recurring-tagihan-ipas.index', compact('tpList'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = RecurringTagihanIpas::query()->latest('id');

        // Filter khusus TP
        if ($request->filled('tp') && $request->tp !== 'all') {
            $query->where('tp', $request->tp);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->toJson();
    }
}
