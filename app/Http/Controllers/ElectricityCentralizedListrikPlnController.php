<?php

namespace App\Http\Controllers;

use App\Exports\ListrikPlnExport;
use App\Http\Requests\UpdateListrikPlnRequest;
use App\Models\BongkarRampungPln;
use App\Models\ListrikPln;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ElectricityCentralizedListrikPlnController extends Controller
{
    public function index(Request $request): View
    {
        $years = ListrikPln::query()
            ->whereNotNull('tanggal')
            ->selectRaw('YEAR(tanggal) as tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->map(fn ($tahun) => (int) $tahun)
            ->all();

        // Gunakan daftar NOP dari data PLN agar filter selalu mencakup data
        // terbaru, tanpa terpengaruh variasi kapitalisasi atau prefix "NOP".
        $nops = ListrikPln::query()
            ->whereNotNull('nop')
            ->whereRaw("TRIM(nop) <> ''")
            ->selectRaw("DISTINCT TRIM(REPLACE(REPLACE(UPPER(nop), 'NOP ', ''), 'NOP-', '')) as nop")
            ->orderBy('nop')
            ->pluck('nop')
            ->all();

        // Terima parameter filter dari Dashboard atau URL langsung
        $selectedTahun = $request->integer('tahun') ?: null;
        $selectedBulan = $request->integer('bulan') ?: null;
        $rawNop = trim((string) $request->query('nop', ''));
        $selectedNop = $this->normalizeNop($rawNop) ?? '';

        return view('electricity.centralized.listrik-pln.index', compact('years', 'nops', 'selectedTahun', 'selectedBulan', 'selectedNop'));
    }

    public function data(Request $request): JsonResponse
    {
        $isAdmin = auth()->user()->hasRole('admin');

        $query = $this->filteredQuery($request)->latest('id');

        $dataTable = DataTables::of($query);

        if ($isAdmin) {
            $dataTable->addColumn(
                'aksi',
                fn (ListrikPln $item) => view('electricity.centralized.listrik-pln.partials.aksi', ['item' => $item])->render()
            );
        }

        return $dataTable
            ->addIndexColumn()
            ->addColumn('alamat_full', fn (ListrikPln $item) => $item->alamat ?? '-')
            ->editColumn('alamat', fn (ListrikPln $item) => Str::limit($item->alamat ?? '-', 60))
            ->editColumn('daya_va', fn (ListrikPln $item) => $item->daya_va ? number_format($item->daya_va) : '-')
            ->editColumn('tanggal', fn (ListrikPln $item) => $item->tanggal?->format('d-m-Y') ?? '-')
            ->rawColumns($isAdmin ? ['aksi'] : [])
            ->toJson();
    }

    public function edit(ListrikPln $listrikPln): View
    {
        return view('electricity.centralized.listrik-pln.edit', compact('listrikPln'));
    }

    public function update(UpdateListrikPlnRequest $request, ListrikPln $listrikPln): RedirectResponse
    {
        $data = $request->validated();
        $data['update_by'] = auth()->user()->name;
        $data['tanggal']   = now();

        $listrikPln->update($data);

        return redirect()
            ->route('electricity.centralized.listrik-pln.index')
            ->with('success', "Data Listrik PLN {$listrikPln->id_pelanggan} berhasil diperbarui.");
    }

    public function exportExcel(Request $request)
    {
        $tahun = $request->integer('tahun') ?: null;
        $bulan = $request->integer('bulan') ?: null;
        $nop = $this->selectedNop($request);

        $filename = 'listrik-pln'
            . ($tahun ? "_{$tahun}" : '')
            . ($bulan ? "_{$bulan}" : '')
            . ($nop ? '_' . str_replace(' ', '_', $nop) : '')
            . '_' . now()->format('Ymd_His') . '.xlsx';

        return (new ListrikPlnExport($tahun, $bulan, $nop))->download($filename);
    }

    /** Terapkan filter periode pembaruan dan NOP pada daftar Listrik PLN. */
    private function filteredQuery(Request $request)
    {
        $tahun = $request->integer('tahun');
        $bulan = $request->integer('bulan');
        $nop = $this->selectedNop($request);

        return ListrikPln::query()
            ->when($tahun >= 2000 && $tahun <= 2100, fn ($query) => $query->whereYear('tanggal', $tahun))
            ->when($bulan >= 1 && $bulan <= 12, fn ($query) => $query->whereMonth('tanggal', $bulan))
            ->when($nop !== null, fn ($query) => $query->whereRaw(
                "REPLACE(REPLACE(UPPER(TRIM(nop)), 'NOP ', ''), 'NOP-', '') = ?",
                [strtoupper($nop)]
            ));
    }

    /** Normalisasi NOP dari dashboard maupun data impor PLN. */
    private function selectedNop(Request $request): ?string
    {
        return $this->normalizeNop($request->query('nop'));
    }

    private function normalizeNop(mixed $value): ?string
    {
        $nop = strtoupper(trim((string) $value));
        $nop = preg_replace('/^NOP[\s-]+/', '', $nop) ?? $nop;

        return $nop !== '' ? $nop : null;
    }

    // ========== BONGKAR RAMPUNG (BORAM) ==========

    public function showBongkarRampungCreate(ListrikPln $listrikPln): View
    {
        return view('electricity.centralized.listrik-pln.bongkar-rampung.create', compact('listrikPln'));
    }

    public function storeBongkarRampung(Request $request, ListrikPln $listrikPln): RedirectResponse
    {
        $request->validate([
            'no_surat_boram'     => ['nullable', 'string', 'max:100'],
            'tanggal_surat_boram' => ['nullable', 'date'],
            'mitra_boram'        => ['nullable', 'string', 'max:255'],
        ]);

        BongkarRampungPln::create([
            'listrik_pln_id'      => $listrikPln->id,
            'id_pelanggan'        => $listrikPln->id_pelanggan,
            'no_surat_boram'      => $request->no_surat_boram,
            'tanggal_surat_boram' => $request->tanggal_surat_boram,
            'mitra_boram'         => $request->mitra_boram,
        ]);

        return redirect()
            ->route('electricity.centralized.listrik-pln.index')
            ->with('success', "Data Bongkar Rampung untuk ID Pelanggan {$listrikPln->id_pelanggan} berhasil disimpan.");
    }

    // ========== GRAFIK TAGIHAN ==========

    public function grafikTagihan(Request $request, ListrikPln $listrikPln): View
    {
        $dbYears = \App\Models\StatusPembayaran::query()
            ->where('listrik_pln_id', $listrikPln->id)
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->toArray();

        $defaultYears = [(int) date('Y'), (int) date('Y') - 1, (int) date('Y') - 2];
        $years = array_values(array_unique(array_merge($dbYears, $defaultYears)));
        rsort($years);

        $selectedYear = (int) $request->input('tahun', $years[0] ?? date('Y'));

        return view('electricity.centralized.listrik-pln.grafik', compact('listrikPln', 'years', 'selectedYear'));
    }

    public function grafikData(Request $request, ListrikPln $listrikPln): JsonResponse
    {
        $year = (int) $request->input('tahun', date('Y'));

        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $statusRows = \App\Models\StatusPembayaran::query()
            ->where('listrik_pln_id', $listrikPln->id)
            ->where('tahun', $year)
            ->get()
            ->keyBy('bulan');

        $monthlyData = [];
        $totalNominal = 0;
        $maxNominal = 0;
        $minNominal = PHP_INT_MAX;
        $activeMonthsCount = 0;

        for ($b = 1; $b <= 12; $b++) {
            $row = $statusRows->get($b);
            $nominal = $row ? (float) $row->harga : 0;
            $remark = $row ? ($row->remark ?? 'Lunas') : 'Belum Ada Data';
            $tanggal = $row && $row->tanggal ? $row->tanggal->format('d-m-Y') : '-';
            $updateBy = $row ? ($row->update_by ?? '-') : '-';

            if ($nominal > 0) {
                $totalNominal += $nominal;
                $activeMonthsCount++;
                if ($nominal > $maxNominal) $maxNominal = $nominal;
                if ($nominal < $minNominal) $minNominal = $nominal;
            }

            $monthlyData[] = [
                'bulan'           => $b,
                'nama_bulan'      => $namaBulan[$b],
                'short_bulan'     => substr($namaBulan[$b], 0, 3),
                'nominal'         => $nominal,
                'nominal_format'  => 'Rp ' . number_format($nominal, 0, ',', '.'),
                'remark'          => $remark,
                'tanggal'         => $tanggal,
                'update_by'       => $updateBy,
                'id_pelanggan'    => $listrikPln->id_pelanggan,
                'site_id'         => $listrikPln->site_id,
                'site_name'       => $listrikPln->site_name,
                'daya_va'         => $listrikPln->daya_va ? number_format($listrikPln->daya_va) . ' VA' : '-',
                'gol_tarif'       => $listrikPln->gol_tarif ?? '-',
                'unit_pln'        => $listrikPln->unit_layanan_pln ?? '-',
            ];
        }

        if ($minNominal === PHP_INT_MAX) {
            $minNominal = 0;
        }

        $avgNominal = $activeMonthsCount > 0 ? ($totalNominal / $activeMonthsCount) : 0;

        return response()->json([
            'year'    => $year,
            'summary' => [
                'total'           => 'Rp ' . number_format($totalNominal, 0, ',', '.'),
                'rata_rata'       => 'Rp ' . number_format($avgNominal, 0, ',', '.'),
                'tertinggi'       => 'Rp ' . number_format($maxNominal, 0, ',', '.'),
                'terendah'        => 'Rp ' . number_format($minNominal, 0, ',', '.'),
                'bulan_terisi'    => $activeMonthsCount . ' / 12 Bulan',
                'raw_total'       => $totalNominal,
            ],
            'labels'  => array_column($monthlyData, 'short_bulan'),
            'values'  => array_column($monthlyData, 'nominal'),
            'details' => $monthlyData,
        ]);
    }
}
