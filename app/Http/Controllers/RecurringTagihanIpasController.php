<?php

namespace App\Http\Controllers;

use App\Models\RecurringTagihanIpas;
use App\Models\RecurringTagihanIpasTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class RecurringTagihanIpasController extends Controller
{
    public function index(): View
    {
        // Daftar TP unik untuk dropdown filter
        $tpList = RecurringTagihanIpasTerm::query()
            ->whereNotNull('tp')
            ->where('tp', '!=', '')
            ->distinct()
            ->orderBy('tp')
            ->pluck('tp');

        return view('infrastruktur.recurring-tagihan-ipas.index', compact('tpList'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = RecurringTagihanIpas::query()->orderBy('site_code');

        // Filter khusus TP
        if ($request->filled('tp') && $request->tp !== 'all') {
            $query->whereHas('terms', fn ($terms) => $terms->where('tp', $request->tp));
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->toJson();
    }

    public function show(RecurringTagihanIpas $site): JsonResponse
    {
        return response()->json(['data' => $site->terms()->orderBy('termin_start')->orderBy('id')->get()]);
    }
}
