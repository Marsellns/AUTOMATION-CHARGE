<?php

namespace App\Http\Controllers;

use App\Exports\ListrikInbuildingExport;
use App\Models\ListrikInbuilding;
use App\Models\PaymentIbc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ElectricityInbuildingListrikController extends Controller
{
    public function index(): View
    {
        return view('electricity.inbuilding.listrik-inbuilding.index');
    }

    public function data(Request $request): JsonResponse
    {
        $isAdmin = auth()->user()->hasRole('admin');

        $query = ListrikInbuilding::query()->latest('id');

        $dataTable = DataTables::of($query);

        if ($isAdmin) {
            $dataTable->addColumn(
                'aksi',
                fn (ListrikInbuilding $item) => view('electricity.inbuilding.listrik-inbuilding.partials.aksi', ['item' => $item])->render()
            );
        }

        return $dataTable
            ->addIndexColumn()
            ->addColumn('alamat_full', fn (ListrikInbuilding $item) => $item->alamat ?? '-')
            ->editColumn('alamat', fn (ListrikInbuilding $item) => Str::limit($item->alamat ?? '-', 45))
            ->editColumn('daya', fn (ListrikInbuilding $item) => $item->daya ? number_format($item->daya, 0, ',', '.') . ' VA' : '-')
            ->editColumn('harga_per_kwh', fn (ListrikInbuilding $item) => $item->harga_per_kwh ? 'Rp ' . number_format($item->harga_per_kwh, 0, ',', '.') : '-')
            ->editColumn('status', function (ListrikInbuilding $item) {
                $status = strtolower($item->status ?? 'active');
                $isAktif = str_contains($status, 'active') || str_contains($status, 'aktif');
                $badgeClass = $isAktif ? 'badge-profit' : 'badge-loss';
                return '<span class="badge ' . $badgeClass . '">' . e(ucfirst($item->status ?? 'Active')) . '</span>';
            })
            ->editColumn('tanggal', fn (ListrikInbuilding $item) => $item->tanggal ? $item->tanggal->format('d-m-Y') : '-')
            ->rawColumns($isAdmin ? ['aksi', 'status'] : ['status'])
            ->toJson();
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_id'       => ['required', 'string', 'max:50'],
            'site_name'     => ['nullable', 'string', 'max:255'],
            'status'        => ['nullable', 'string', 'max:50'],
            'nama_bm'       => ['nullable', 'string', 'max:255'],
            'no_npwp'       => ['nullable', 'string', 'max:100'],
            'alamat'        => ['nullable', 'string'],
            'telkomsel_tp'  => ['nullable', 'string', 'max:50'],
            'daya'          => ['nullable', 'numeric'],
            'harga_per_kwh' => ['nullable', 'numeric'],
        ]);

        $validated['update_by'] = auth()->user()->name;
        $validated['tanggal']   = now();

        ListrikInbuilding::create($validated);

        return redirect()
            ->route('electricity.inbuilding.listrik-inbuilding.index')
            ->with('success', "Data Listrik Inbuilding {$validated['site_id']} berhasil ditambahkan.");
    }

    public function show(ListrikInbuilding $listrikInbuilding): JsonResponse
    {
        return response()->json([
            'id'            => $listrikInbuilding->id,
            'site_id'       => $listrikInbuilding->site_id,
            'site_name'     => $listrikInbuilding->site_name ?? '-',
            'status'        => $listrikInbuilding->status ?? 'Active',
            'nama_bm'       => $listrikInbuilding->nama_bm ?? '-',
            'no_npwp'       => $listrikInbuilding->no_npwp ?? '-',
            'alamat'        => $listrikInbuilding->alamat ?? '-',
            'telkomsel_tp'  => $listrikInbuilding->telkomsel_tp ?? '-',
            'daya'          => $listrikInbuilding->daya ? number_format($listrikInbuilding->daya, 0, ',', '.') . ' VA' : '-',
            'raw_daya'      => $listrikInbuilding->daya,
            'harga_per_kwh' => $listrikInbuilding->harga_per_kwh ? 'Rp ' . number_format($listrikInbuilding->harga_per_kwh, 0, ',', '.') : '-',
            'raw_harga'     => $listrikInbuilding->harga_per_kwh,
            'update_by'     => $listrikInbuilding->update_by ?? '-',
            'tanggal'       => $listrikInbuilding->tanggal ? $listrikInbuilding->tanggal->format('d-m-Y') : '-',
            'raw_tanggal'   => $listrikInbuilding->tanggal ? $listrikInbuilding->tanggal->format('Y-m-d') : null,
        ]);
    }

    public function update(Request $request, ListrikInbuilding $listrikInbuilding): RedirectResponse
    {
        $validated = $request->validate([
            'site_id'       => ['required', 'string', 'max:50'],
            'site_name'     => ['nullable', 'string', 'max:255'],
            'status'        => ['nullable', 'string', 'max:50'],
            'nama_bm'       => ['nullable', 'string', 'max:255'],
            'no_npwp'       => ['nullable', 'string', 'max:100'],
            'alamat'        => ['nullable', 'string'],
            'telkomsel_tp'  => ['nullable', 'string', 'max:50'],
            'daya'          => ['nullable', 'numeric'],
            'harga_per_kwh' => ['nullable', 'numeric'],
        ]);

        $validated['update_by'] = auth()->user()->name;
        $validated['tanggal']   = now();

        $listrikInbuilding->update($validated);

        return redirect()
            ->route('electricity.inbuilding.listrik-inbuilding.index')
            ->with('success', "Data Listrik Inbuilding {$listrikInbuilding->site_id} berhasil diperbarui.");
    }

    public function destroy(ListrikInbuilding $listrikInbuilding): RedirectResponse
    {
        $siteId = $listrikInbuilding->site_id;
        $listrikInbuilding->delete();

        return redirect()
            ->route('electricity.inbuilding.listrik-inbuilding.index')
            ->with('success', "Data Listrik Inbuilding {$siteId} berhasil dihapus.");
    }

    /**
     * API Grafik Tagihan Bulanan (Jan - Des) per Tahun untuk Listrik Inbuilding.
     */
    public function chartData(Request $request, ListrikInbuilding $listrikInbuilding): JsonResponse
    {
        $year = (int) $request->input('tahun', 2025);

        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $paymentRows = PaymentIbc::query()
            ->where('site_id', $listrikInbuilding->site_id)
            ->where('tahun', $year)
            ->get()
            ->keyBy('bulan');

        $monthlyData = [];
        $totalNominal = 0;
        $maxNominal = 0;
        $minNominal = PHP_INT_MAX;
        $activeMonthsCount = 0;

        for ($b = 1; $b <= 12; $b++) {
            $row = $paymentRows->get($b);
            $nominal = $row ? (float) $row->jumlah_tagihan : 0;
            $status = $row ? ($row->status ?? 'Done') : 'Belum Ada Data';
            $invoice = $row ? ($row->invoice ?? '-') : '-';
            $tanggal = $row && $row->tanggal_update_status ? $row->tanggal_update_status->format('d-m-Y') : '-';
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
                'status'          => $status,
                'invoice'         => $invoice,
                'tanggal'         => $tanggal,
                'update_by'       => $updateBy,
                'site_id'         => $listrikInbuilding->site_id,
                'site_name'       => $listrikInbuilding->site_name,
                'nama_bm'         => $listrikInbuilding->nama_bm ?? '-',
                'daya'            => $listrikInbuilding->daya ? number_format($listrikInbuilding->daya, 0, ',', '.') . ' VA' : '-',
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

    public function exportExcel(Request $request)
    {
        return (new ListrikInbuildingExport())->download('listrik-inbuilding_' . now()->format('Ymd_His') . '.xlsx');
    }
}
