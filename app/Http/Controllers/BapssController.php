<?php

namespace App\Http\Controllers;

use App\Exports\BapssExport;
use App\Models\Bapss;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel;
use Yajra\DataTables\Facades\DataTables;

class BapssController extends Controller
{
    public function index(): View
    {
        return view('infrastruktur.bapss.index');
    }

    public function show(Bapss $bapss): View
    {
        return view('infrastruktur.bapss.show', compact('bapss'));
    }

    public function data(Request $request): JsonResponse
    {
        return DataTables::of(Bapss::query()->latest('id'))
            ->addIndexColumn()
            ->addColumn('aksi', fn (Bapss $b) => view('infrastruktur.bapss.partials.aksi', ['bapss' => $b])->render())
            ->editColumn('tgl_bapss', fn (Bapss $b) => $b->tgl_bapss?->format('Y-m-d') ?? '-')
            ->editColumn('tgl_dismantle', fn (Bapss $b) => $b->tgl_dismantle?->format('Y-m-d') ?? '-')
            ->editColumn('tgl_update', fn (Bapss $b) => $b->tgl_update?->format('Y-m-d H:i') ?? '-')
            ->addColumn('pdf_bapss_link', function (Bapss $b) {
                if (empty($b->pdf_bapss) || $b->pdf_bapss === '-') return '-';
                return '<a href="' . asset('storage/' . $b->pdf_bapss) . '" target="_blank" class="btn btn-outline-brand btn-sm">PDF</a>';
            })
            ->addColumn('pdf_ba_dismantle_link', function (Bapss $b) {
                if (empty($b->pdf_ba_dismantle) || $b->pdf_ba_dismantle === '-') return '-';
                return '<a href="' . asset('storage/' . $b->pdf_ba_dismantle) . '" target="_blank" class="btn btn-outline-brand btn-sm">PDF</a>';
            })
            ->rawColumns(['aksi', 'pdf_bapss_link', 'pdf_ba_dismantle_link'])
            ->toJson();
    }

    public function edit(Bapss $bapss): View
    {
        return view('infrastruktur.bapss.edit', compact('bapss'));
    }

    public function update(Request $request, Bapss $bapss)
    {
        $validated = $request->validate([
            'site_code'   => 'required|string|max:30',
            'site_name'   => 'nullable|string|max:255',
            'tgl_bapss'   => 'nullable|date',
            'tgl_dismantle' => 'nullable|date',
            'remark'      => 'nullable|string|max:500',
            'pdf_bapss_file'       => 'nullable|file|mimes:pdf|max:10240',
            'pdf_ba_dismantle_file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $data = collect($validated)->except(['pdf_bapss_file', 'pdf_ba_dismantle_file'])->toArray();
        $data['update_by'] = auth()->user()->name;
        $data['tgl_update'] = now();

        if ($request->hasFile('pdf_bapss_file')) {
            $data['pdf_bapss'] = $request->file('pdf_bapss_file')->store('bapss', 'public');
        }
        if ($request->hasFile('pdf_ba_dismantle_file')) {
            $data['pdf_ba_dismantle'] = $request->file('pdf_ba_dismantle_file')->store('bapss', 'public');
        }

        $bapss->update($data);

        return redirect()->route('infrastruktur.bapss.index')->with('success', "BAPSS {$bapss->site_code} berhasil diperbarui.");
    }

    public function destroy(Bapss $bapss)
    {
        $code = $bapss->site_code;
        $bapss->delete();

        return redirect()->route('infrastruktur.bapss.index')->with('success', "BAPSS {$code} berhasil dihapus.");
    }

    public function exportExcel()
    {
        return (new BapssExport)->download('bapss_data.xlsx');
    }

    public function exportCsv()
    {
        return (new BapssExport)->download('bapss_data.csv', Excel::CSV);
    }
}
