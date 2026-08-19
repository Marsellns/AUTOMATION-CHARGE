<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePoHqRequest;
use App\Http\Requests\UpdatePoHqRequest;
use App\Models\PoHq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PoHqController extends Controller
{
    /**
     * Halaman utama modul PO HQ: tabel DataTables server-side + modal detail.
     */
    public function index(): View
    {
        return view('po-hq.index');
    }

    /**
     * Sumber data DataTables (server-side processing WAJIB — pola untuk
     * semua modul tabel besar). Kolom Aksi hanya dikirim untuk role admin.
     */
    public function data(): JsonResponse
    {
        $isAdmin = auth()->user()->hasRole('admin');

        $dataTable = DataTables::of(PoHq::query()->latest('id'));

        if ($isAdmin) {
            $dataTable->addColumn(
                'aksi',
                fn (PoHq $po) => view('po-hq.partials.aksi', ['po' => $po])->render()
            );
        }

        return $dataTable
            ->addIndexColumn()
            ->editColumn('description', fn (PoHq $po) => Str::limit($po->description, 80))
            ->rawColumns($isAdmin ? ['aksi'] : [])
            ->toJson();
    }

    /**
     * Detail satu PO sebagai JSON — dipanggil via AJAX saat baris tabel
     * diklik, untuk mengisi modal drilldown.
     */
    public function show(PoHq $po_hq): JsonResponse
    {
        return response()->json(['data' => $po_hq]);
    }

    public function create(): View
    {
        return view('po-hq.create', ['po' => new PoHq()]);
    }

    public function store(StorePoHqRequest $request): RedirectResponse
    {
        // Create lewat Eloquent -> otomatis tercatat di activity_log
        // (event 'created', causer = user login).
        $po = PoHq::create($request->validated());

        return redirect()
            ->route('po-hq.index')
            ->with('success', "PO {$po->po_number} berhasil dibuat.");
    }

    public function edit(PoHq $po_hq): View
    {
        return view('po-hq.edit', ['po' => $po_hq]);
    }

    public function update(UpdatePoHqRequest $request, PoHq $po_hq): RedirectResponse
    {
        // Update tercatat di activity_log dengan nilai lama & baru
        // (kolom attribute_changes).
        $po_hq->update($request->validated());

        return redirect()
            ->route('po-hq.index')
            ->with('success', "PO {$po_hq->po_number} berhasil diperbarui.");
    }

    public function destroy(PoHq $po_hq): RedirectResponse
    {
        $poNumber = $po_hq->po_number;

        // Delete juga tercatat di activity_log (event 'deleted').
        $po_hq->delete();

        return redirect()
            ->route('po-hq.index')
            ->with('success', "PO {$poNumber} berhasil dihapus.");
    }
}
