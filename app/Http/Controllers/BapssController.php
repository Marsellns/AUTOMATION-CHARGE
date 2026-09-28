<?php

namespace App\Http\Controllers;

use App\Exports\BapssExport;
use App\Models\Bapss;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
            ->addColumn('pdf_bapss_link', fn (Bapss $b) => $this->pdfLink($b, 'bapss'))
            ->addColumn('pdf_ba_dismantle_link', fn (Bapss $b) => $this->pdfLink($b, 'dismantle'))
            ->removeColumn('pdf_bapss')
            ->removeColumn('pdf_ba_dismantle')
            ->rawColumns(['aksi', 'pdf_bapss_link', 'pdf_ba_dismantle_link'])
            ->toJson();
    }

    public function file(Bapss $bapss, string $type): StreamedResponse
    {
        $path = $type === 'bapss' ? $bapss->pdf_bapss : $bapss->pdf_ba_dismantle;
        abort_unless($this->isSafePdfPath($path) && Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->response($path, basename($path), [
            'Content-Type' => 'application/pdf',
        ]);
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
            $data['pdf_bapss'] = $request->file('pdf_bapss_file')->store('bapss', 'private');
        }
        if ($request->hasFile('pdf_ba_dismantle_file')) {
            $data['pdf_ba_dismantle'] = $request->file('pdf_ba_dismantle_file')->store('bapss', 'private');
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

    private function pdfLink(Bapss $bapss, string $type): string
    {
        $path = $type === 'bapss' ? $bapss->pdf_bapss : $bapss->pdf_ba_dismantle;
        if (!$this->isSafePdfPath($path)) {
            return '-';
        }

        return '<a href="' . e(route('infrastruktur.bapss.file', [$bapss, $type])) . '" target="_blank" rel="noopener" class="btn btn-outline-brand btn-sm">PDF</a>';
    }

    private function isSafePdfPath(?string $path): bool
    {
        return is_string($path)
            && preg_match('#^bapss/[A-Za-z0-9][A-Za-z0-9._/-]*\.pdf$#i', $path) === 1
            && !str_contains($path, '..');
    }
}
