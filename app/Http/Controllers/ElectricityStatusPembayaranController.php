<?php

namespace App\Http\Controllers;

use App\Exports\StatusPembayaranExport;
use App\Models\ListrikPln;
use App\Models\StatusPembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ElectricityStatusPembayaranController extends Controller
{
    private const BULAN_NAMA = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Tampilan sub-tabel Status Pembayaran untuk pelanggan tertentu.
     */
    public function index(ListrikPln $listrikPln): View
    {
        return view('electricity.centralized.status-pembayaran.index', compact('listrikPln'));
    }

    /**
     * Sumber data DataTables server-side untuk sub-tabel Status Pembayaran.
     */
    public function data(Request $request, ListrikPln $listrikPln): JsonResponse
    {
        $isAdmin = auth()->user()->hasRole('admin');

        $query = StatusPembayaran::query()
            ->where('listrik_pln_id', $listrikPln->id)
            ->orderByDesc('tahun')
            ->orderByDesc('bulan');

        $dataTable = DataTables::of($query);

        if ($isAdmin) {
            $dataTable->addColumn(
                'aksi',
                fn (StatusPembayaran $sp) => view('electricity.centralized.status-pembayaran.partials.aksi', [
                    'sp' => $sp,
                    'listrikPln' => $listrikPln,
                ])->render()
            );
        }

        return $dataTable
            ->addIndexColumn()
            ->editColumn('bulan', fn (StatusPembayaran $sp) => self::BULAN_NAMA[(int)$sp->bulan] ?? (string)$sp->bulan)
            ->editColumn('harga', fn (StatusPembayaran $sp) => 'Rp ' . number_format($sp->harga, 0, ',', '.'))
            ->editColumn('tanggal', fn (StatusPembayaran $sp) => $sp->tanggal?->format('d-m-Y') ?? '-')
            ->rawColumns($isAdmin ? ['aksi'] : [])
            ->toJson();
    }

    /**
     * Export status pembayaran ke Excel.
     */
    public function exportExcel(ListrikPln $listrikPln)
    {
        $filename = 'status_pembayaran_' . $listrikPln->id_pelanggan . '_' . now()->format('Ymd_His') . '.xlsx';
        return (new StatusPembayaranExport($listrikPln->id))->download($filename);
    }

    /**
     * Simpan status pembayaran baru.
     */
    public function store(Request $request, ListrikPln $listrikPln): RedirectResponse
    {
        $validated = $request->validate([
            'bulan'   => ['required', 'integer', 'between:1,12'],
            'tahun'   => ['required', 'integer', 'min:2000', 'max:2100'],
            'harga'   => ['required', 'numeric', 'min:0'],
            'remark'  => ['nullable', 'string', 'max:500'],
            'tanggal' => ['nullable', 'date'],
        ]);

        StatusPembayaran::updateOrCreate(
            [
                'listrik_pln_id' => $listrikPln->id,
                'bulan'          => $validated['bulan'],
                'tahun'          => $validated['tahun'],
            ],
            [
                'id_pelanggan'   => $listrikPln->id_pelanggan,
                'harga'          => $validated['harga'],
                'remark'         => $validated['remark'] ?? null,
                'update_by'      => auth()->user()->name,
                'tanggal'        => $validated['tanggal'] ?? now(),
            ]
        );

        return redirect()
            ->route('electricity.centralized.listrik-pln.status-pembayaran.index', $listrikPln)
            ->with('success', 'Status Pembayaran berhasil disimpan.');
    }

    /**
     * Update data status pembayaran.
     */
    public function update(Request $request, StatusPembayaran $statusPembayaran): RedirectResponse
    {
        $validated = $request->validate([
            'harga'   => ['required', 'numeric', 'min:0'],
            'remark'  => ['nullable', 'string', 'max:500'],
            'tanggal' => ['nullable', 'date'],
        ]);

        $statusPembayaran->update([
            'harga'     => $validated['harga'],
            'remark'    => $validated['remark'] ?? null,
            'update_by' => auth()->user()->name,
            'tanggal'   => $validated['tanggal'] ?? now(),
        ]);

        return redirect()
            ->route('electricity.centralized.listrik-pln.status-pembayaran.index', $statusPembayaran->listrik_pln_id)
            ->with('success', 'Status Pembayaran berhasil diperbarui.');
    }

    /**
     * Hapus status pembayaran.
     */
    public function destroy(StatusPembayaran $statusPembayaran): RedirectResponse
    {
        $listrikPlnId = $statusPembayaran->listrik_pln_id;
        $statusPembayaran->delete();

        return redirect()
            ->route('electricity.centralized.listrik-pln.status-pembayaran.index', $listrikPlnId)
            ->with('success', 'Data Status Pembayaran berhasil dihapus.');
    }
}
