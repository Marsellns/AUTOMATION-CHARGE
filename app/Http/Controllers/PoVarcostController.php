<?php

namespace App\Http\Controllers;

use App\Exports\PoVarcostExport;
use App\Http\Requests\PoVarcostRequest;
use App\Models\PoVarcost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PoVarcostController extends Controller
{
    public function index(): View
    {
        return view('po-varcost.index', [
            'grStatuses' => PoVarcost::query()
                ->whereNotNull('gr_status')
                ->where('gr_status', '<>', '')
                ->distinct()
                ->orderBy('gr_status')
                ->pluck('gr_status'),
            'poYears' => PoVarcost::query()
                ->whereNotNull('po_year')
                ->distinct()
                ->orderByDesc('po_year')
                ->pluck('po_year'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = $this->filteredQuery($request);

        $dataTable = DataTables::of($query);
        if (auth()->user()->hasRole('admin')) {
            $dataTable->addColumn('aksi', fn (PoVarcost $po) => '<div class="d-flex gap-1">'
                . '<button class="btn btn-warning btn-sm edit-po" data-id="' . $po->id . '">Edit</button>'
                . '<button class="btn btn-danger btn-sm delete-po" data-id="' . $po->id . '">Delete</button>'
                . '</div>');
        }

        return $dataTable
            ->filter(function ($query) use ($request) {
                $search = trim((string) $request->input('search.value', ''));
                if ($search !== '') {
                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery
                            ->where('po_number', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            }, true)
            ->addIndexColumn()
            ->editColumn('description', fn (PoVarcost $po) => Str::limit($po->description, 100))
            ->editColumn('delivery_date', fn (PoVarcost $po) => $po->delivery_date?->format('d/m/Y') ?? '-')
            ->editColumn('update_at', fn (PoVarcost $po) => $po->update_at?->format('d/m/Y H:i') ?? '-')
            ->rawColumns(auth()->user()->hasRole('admin') ? ['aksi'] : [])
            ->toJson();
    }

    public function store(PoVarcostRequest $request): JsonResponse
    {
        $po = PoVarcost::create([
            ...$request->validated(),
            'update_by' => Auth::user()->name,
            'update_at' => now(),
        ]);

        return response()->json(['message' => 'Data PO Varcost berhasil ditambahkan.', 'data' => $po]);
    }

    public function update(PoVarcostRequest $request, PoVarcost $po_varcost): JsonResponse
    {
        $po_varcost->update([
            ...$request->validated(),
            'update_by' => Auth::user()->name,
            'update_at' => now(),
        ]);

        return response()->json(['message' => 'Data PO Varcost berhasil diperbarui.', 'data' => $po_varcost->fresh()]);
    }

    public function destroy(PoVarcost $po_varcost): JsonResponse
    {
        $po_varcost->delete();

        return response()->json(['message' => 'Data PO Varcost berhasil dihapus.']);
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(
            new PoVarcostExport($this->filteredQuery($request)),
            'po-varcost-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    private function filteredQuery(Request $request): Builder
    {
        return PoVarcost::query()
            ->when($request->filled('expense_type'), fn ($q) => $q->where('expense_type', $request->input('expense_type')))
            ->when($request->filled('gr_status'), fn ($q) => $q->where('gr_status', $request->input('gr_status')))
            ->when($request->filled('po_year'), fn ($q) => $q->where('po_year', (int) $request->input('po_year')))
            ->when(trim((string) $request->input('search.value', $request->input('search', ''))) !== '', function ($q) use ($request) {
                $search = trim((string) $request->input('search.value', $request->input('search', '')));
                $q->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('po_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest('id');
    }
}
