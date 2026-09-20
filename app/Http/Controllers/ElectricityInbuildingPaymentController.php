<?php

namespace App\Http\Controllers;

use App\Exports\PaymentIbcExport;
use App\Models\PaymentIbc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ElectricityInbuildingPaymentController extends Controller
{
    public function index(): View
    {
        $years = PaymentIbc::query()
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->toArray();

        if (empty($years)) {
            $years = [2026, 2025, 2024];
        }

        return view('electricity.inbuilding.payment.index', compact('years'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = PaymentIbc::query()->select('payment_ibc.*');

        if ($request->filled('bulan') && is_numeric($request->bulan)) {
            $query->where('bulan', (int) $request->bulan);
        }

        if ($request->filled('tahun') && is_numeric($request->tahun)) {
            $query->where('tahun', (int) $request->tahun);
        }

        if ($request->filled('status') && in_array($request->status, ['Done', 'Pending'])) {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('daya', fn (PaymentIbc $p) => $p->daya ? number_format($p->daya, 0, ',', '.') . ' VA' : '-')
            ->editColumn('jumlah_tagihan', fn (PaymentIbc $p) => 'Rp ' . number_format((float)$p->jumlah_tagihan, 0, ',', '.'))
            ->editColumn('tanggal_update_status', fn (PaymentIbc $p) => $p->tanggal_update_status ? $p->tanggal_update_status->format('d-m-Y') : '-')
            ->editColumn('status', function (PaymentIbc $p) {
                $isDone = strtolower($p->status) === 'done';
                $badgeClass = $isDone ? 'badge-profit' : 'badge-anomaly';
                return '<span class="badge ' . $badgeClass . '">' . e($p->status) . '</span>';
            })
            ->rawColumns(['status'])
            ->toJson();
    }

    public function exportExcel(Request $request)
    {
        $bulan = $request->filled('bulan') ? (int) $request->bulan : null;
        $tahun = $request->filled('tahun') ? (int) $request->tahun : null;
        $status = $request->filled('status') ? (string) $request->status : null;

        $filename = 'payment_ibc_' . ($status ? strtolower($status) . '_' : '') . now()->format('Ymd_His') . '.xlsx';

        return (new PaymentIbcExport($bulan, $tahun, $status))->download($filename);
    }
}
