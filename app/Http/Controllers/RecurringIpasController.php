<?php

namespace App\Http\Controllers;

use App\Exports\RecurringIpasExport;
use App\Models\RecurringIpas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class RecurringIpasController extends Controller
{
    public function index(): View
    {
        return view('infrastruktur.recurring-ipas.index');
    }

    public function data(Request $request): JsonResponse
    {
        return DataTables::of(RecurringIpas::query()->latest('id'))
            ->addIndexColumn()
            ->toJson();
    }

    public function exportExcel(Request $request)
    {
        return (new RecurringIpasExport)->download('recurring_ant_ipas.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return (new RecurringIpasExport)->download('recurring_ant_ipas.csv', \Maatwebsite\Excel\Excel::CSV);
    }
}
