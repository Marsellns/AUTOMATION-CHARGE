<?php

namespace App\Http\Controllers;

use App\Models\InputUploadTagihanIbc;
use App\Models\ListrikInbuilding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ElectricityInbuildingInputTagihanController extends Controller
{
    public function index(): View
    {
        return $this->formView(false);
    }

    public function create(): View
    {
        return $this->formView(true);
    }

    public function edit(InputUploadTagihanIbc $inputUploadTagihanIbc): View
    {
        return $this->formView(true, $inputUploadTagihanIbc);
    }

    private function formView(bool $showForm, ?InputUploadTagihanIbc $editRecord = null): View
    {
        $types = InputUploadTagihanIbc::TYPES;

        // Generate periode yang masuk akal: 2024 s/d 2026 (Jan - Des)
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $periods = [];
        for ($year = 2026; $year >= 2024; $year--) {
            for ($month = 12; $month >= 1; $month--) {
                $code = sprintf('%04d-%02d', $year, $month);
                $label = $namaBulan[$month] . ' ' . $year;
                $periods[] = [
                    'code' => $code,
                    'label' => $label,
                ];
            }
        }

        // List site IDs from Listrik Inbuilding master
        $sites = ListrikInbuilding::query()->orderBy('site_id')->pluck('site_name', 'site_id')->toArray();

        return view('electricity.inbuilding.input-tagihan-ibc.index', compact('types', 'periods', 'sites', 'showForm', 'editRecord'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = InputUploadTagihanIbc::query()->latest('id');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('dpp_ppn_b', fn ($row) => 'Rp ' . number_format((float)$row->dpp_ppn_b, 0, ',', '.'))
            ->editColumn('total_tagihan_f', fn ($row) => 'Rp ' . number_format((float)$row->total_tagihan_f, 0, ',', '.'))
            ->editColumn('total_payment_k', fn ($row) => '<strong class="text-primary">Rp ' . number_format((float)$row->total_payment_k, 0, ',', '.') . '</strong>')
            ->editColumn('tgl_bayar_to_bm', fn ($row) => $row->tgl_bayar_to_bm ? $row->tgl_bayar_to_bm->format('d-m-Y') : '-')
            ->addColumn('aksi', function ($row) {
                return '
                <div class="d-flex align-items-center gap-1 justify-content-end">
                    <a href="' . route('electricity.inbuilding.input-tagihan-ibc.edit', $row) . '" class="btn btn-outline-primary btn-sm" title="Edit Data">
                        Edit
                    </a>
                    <button type="button" class="btn btn-outline-danger btn-sm btn-delete-tagihan" data-id="' . $row->id . '" data-site="' . e($row->site_id) . '" title="Hapus Data">
                        Hapus
                    </button>
                </div>';
            })
            ->rawColumns(['total_payment_k', 'aksi'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->validationRules());

        $b = (float) ($validated['dpp_ppn_b'] ?? 0);
        $c = (float) ($validated['ppj_pju_c'] ?? 0);
        $d = (float) ($validated['materai_d'] ?? 0);
        $e = (float) ($validated['others_e'] ?? 0);

        // Kalkulasi Total Tagihan (F) = B + C + D + E
        $totalTagihanF = $b + $c + $d + $e;

        $g = (float) ($validated['gross_up_g'] ?? 0);
        $h = (float) ($validated['ppn_h'] ?? 0);
        $i = (float) ($validated['dpp_wht_i'] ?? 0);
        $j = (float) ($validated['wht_j'] ?? 0);

        // Kalkulasi Total Payment (K) = F + G + H - J (atau F + G + H - I - J jika ada potongan tambahan)
        // Standard formula: Total Tagihan + Gross Up + PPN - WHT
        $totalPaymentK = ($totalTagihanF + $g + $h) - $j;
        if ($i > 0 && $j == 0) {
            // fallback jika WHT belum dihitung tetapi DPP WHT dimasukkan
            $totalPaymentK = ($totalTagihanF + $g + $h) - $i;
        }

        $validated['total_tagihan_f'] = $totalTagihanF;
        $validated['total_payment_k'] = max(0, $totalPaymentK);

        $record = InputUploadTagihanIbc::create($validated);

        return response()->json([
            'success' => true,
            'message' => "Data Tagihan IBC untuk Site {$record->site_id} (Periode: {$record->periode}) berhasil disimpan.",
            'data'    => $record,
        ]);
    }

    public function update(Request $request, InputUploadTagihanIbc $inputUploadTagihanIbc): JsonResponse
    {
        $validated = $request->validate($this->validationRules());
        $this->calculateTotals($validated);
        $inputUploadTagihanIbc->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Data Tagihan IBC untuk Site {$inputUploadTagihanIbc->site_id} berhasil diperbarui.",
        ]);
    }

    private function validationRules(): array
    {
        return [
            'type' => ['required', 'string', 'max:50'], 'site_id' => ['required', 'string', 'max:50'],
            'no_faktur_pajak' => ['nullable', 'string', 'max:100'], 'tgl_faktur_pajak' => ['nullable', 'date'],
            'no_bast' => ['nullable', 'string', 'max:100'], 'tgl_bast' => ['nullable', 'date'],
            'meter_awal' => ['nullable', 'numeric'], 'meter_akhir' => ['nullable', 'numeric'],
            'periode' => ['required', 'string', 'max:50'], 'no_invoice_bm' => ['nullable', 'string', 'max:100'],
            'tgl_bayar_to_bm' => ['nullable', 'date'], 'no_invoice_rpj' => ['nullable', 'string', 'max:100'],
            'dpp_ppn_b' => ['nullable', 'numeric', 'min:0'], 'ppj_pju_c' => ['nullable', 'numeric', 'min:0'],
            'materai_d' => ['nullable', 'numeric', 'min:0'], 'others_e' => ['nullable', 'numeric', 'min:0'],
            'gross_up_g' => ['nullable', 'numeric', 'min:0'], 'ppn_h' => ['nullable', 'numeric', 'min:0'],
            'dpp_wht_i' => ['nullable', 'numeric', 'min:0'], 'wht_j' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function calculateTotals(array &$validated): void
    {
        $b = (float) ($validated['dpp_ppn_b'] ?? 0);
        $c = (float) ($validated['ppj_pju_c'] ?? 0);
        $d = (float) ($validated['materai_d'] ?? 0);
        $e = (float) ($validated['others_e'] ?? 0);
        $g = (float) ($validated['gross_up_g'] ?? 0);
        $h = (float) ($validated['ppn_h'] ?? 0);
        $i = (float) ($validated['dpp_wht_i'] ?? 0);
        $j = (float) ($validated['wht_j'] ?? 0);
        $totalPayment = ($b + $c + $d + $e + $g + $h) - $j;
        if ($i > 0 && $j == 0) {
            $totalPayment = ($b + $c + $d + $e + $g + $h) - $i;
        }
        $validated['total_tagihan_f'] = $b + $c + $d + $e;
        $validated['total_payment_k'] = max(0, $totalPayment);
    }

    public function show(InputUploadTagihanIbc $inputUploadTagihanIbc): JsonResponse
    {
        return response()->json([
            'id'               => $inputUploadTagihanIbc->id,
            'type'             => $inputUploadTagihanIbc->type,
            'site_id'          => $inputUploadTagihanIbc->site_id,
            'no_faktur_pajak'  => $inputUploadTagihanIbc->no_faktur_pajak ?? '-',
            'tgl_faktur_pajak' => $inputUploadTagihanIbc->tgl_faktur_pajak ? $inputUploadTagihanIbc->tgl_faktur_pajak->format('d-m-Y') : '-',
            'no_bast'          => $inputUploadTagihanIbc->no_bast ?? '-',
            'tgl_bast'         => $inputUploadTagihanIbc->tgl_bast ? $inputUploadTagihanIbc->tgl_bast->format('d-m-Y') : '-',
            'meter_awal'       => $inputUploadTagihanIbc->meter_awal ? number_format((float)$inputUploadTagihanIbc->meter_awal, 2) : '-',
            'meter_akhir'      => $inputUploadTagihanIbc->meter_akhir ? number_format((float)$inputUploadTagihanIbc->meter_akhir, 2) : '-',
            'periode'          => $inputUploadTagihanIbc->periode,
            'no_invoice_bm'    => $inputUploadTagihanIbc->no_invoice_bm ?? '-',
            'tgl_bayar_to_bm'  => $inputUploadTagihanIbc->tgl_bayar_to_bm ? $inputUploadTagihanIbc->tgl_bayar_to_bm->format('d-m-Y') : '-',
            'no_invoice_rpj'   => $inputUploadTagihanIbc->no_invoice_rpj ?? '-',
            'dpp_ppn_b'        => 'Rp ' . number_format((float)$inputUploadTagihanIbc->dpp_ppn_b, 0, ',', '.'),
            'ppj_pju_c'        => 'Rp ' . number_format((float)$inputUploadTagihanIbc->ppj_pju_c, 0, ',', '.'),
            'materai_d'        => 'Rp ' . number_format((float)$inputUploadTagihanIbc->materai_d, 0, ',', '.'),
            'others_e'         => 'Rp ' . number_format((float)$inputUploadTagihanIbc->others_e, 0, ',', '.'),
            'total_tagihan_f'  => 'Rp ' . number_format((float)$inputUploadTagihanIbc->total_tagihan_f, 0, ',', '.'),
            'gross_up_g'       => 'Rp ' . number_format((float)$inputUploadTagihanIbc->gross_up_g, 0, ',', '.'),
            'ppn_h'            => 'Rp ' . number_format((float)$inputUploadTagihanIbc->ppn_h, 0, ',', '.'),
            'dpp_wht_i'        => 'Rp ' . number_format((float)$inputUploadTagihanIbc->dpp_wht_i, 0, ',', '.'),
            'wht_j'            => 'Rp ' . number_format((float)$inputUploadTagihanIbc->wht_j, 0, ',', '.'),
            'total_payment_k'  => 'Rp ' . number_format((float)$inputUploadTagihanIbc->total_payment_k, 0, ',', '.'),
            'created_at'       => $inputUploadTagihanIbc->created_at ? $inputUploadTagihanIbc->created_at->format('d-m-Y H:i') : '-',
        ]);
    }

    public function destroy(InputUploadTagihanIbc $inputUploadTagihanIbc): JsonResponse
    {
        $siteId = $inputUploadTagihanIbc->site_id;
        $inputUploadTagihanIbc->delete();

        return response()->json([
            'success' => true,
            'message' => "Data Tagihan IBC untuk site {$siteId} berhasil dihapus.",
        ]);
    }
}
