<?php

namespace App\Http\Controllers;

use App\Exports\ListrikAllExport;
use App\Imports\Electricity\CentralizedListrikAllImport;
use App\Models\ListrikAll;
use App\Models\PaymentPlnMasterMonthly;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Yajra\DataTables\Facades\DataTables;

class ElectricityCentralizedListrikAllController extends Controller
{
    public function showUpload(): View
    {
        return view('electricity.centralized.upload-excel', [
            'type' => 'listrik-all',
            'title' => 'Listrik All',
            'fileField' => 'listrik_all_file',
            'uploadRoute' => route('electricity.centralized.listrik-all.upload'),
            'templateRoute' => route('electricity.centralized.listrik-all.template'),
            'headers' => self::listrikAllHeaders(),
            'months' => [],
        ]);
    }

    public function template()
    {
        $headers = self::listrikAllHeaders();
        return Excel::download(new class($headers) implements FromArray {
            public function __construct(private readonly array $headers) {}
            public function array(): array { return [['DATA MASTER PLN EASTERN JABOTABEK'], [], $this->headers]; }
        }, 'template-listrik-all-centralized.xlsx');
    }

    private static function listrikAllHeaders(): array
    {
        return ['NO', 'ID Pelanggan', 'Site ID', 'Site Name', 'Nama Pelanggan', 'Daya Existing', 'Gol Tarif', 'PLN UID', 'PLN UP3', 'PLN ULP', 'Alamat', 'Bill type', 'TP (OWNER)', 'NOP', 'AMR Date', 'Phasa', 'KWH Type (AMR/Non AMR)2', 'SITE ID LAMA', 'BA MUTASI N', 'REMARKS', 'Flagging Januari 2023', 'Flagging Februari 2023', 'Flagging Maret 2023', 'Flagging April 2023', 'Flagging Mei 2023', 'Flagging Juni 2023', 'Flagging Juli 2023', 'Flagging Aug 2023', 'Flagging Sept 2023', 'Flagging Oct 2023', 'Flagging Nov 2023', 'Flagging Dec 2023', 'Flagging Jan 2024', 'Flagging Feb 2024', 'Flagging Mar 2024', 'Flagging Apr 2024', 'Flagging Mei 2024', 'Flagging Juni 2024', 'Flagging Juli 2024', 'Flagging Aug 2024', 'Flagging Sep 2024', 'Flagging Oct 2024', 'Flagging Nov 2024', 'Flagging Dec 2024', 'Flagging Jan 2025', 'Flagging Feb 2025', 'Flagging Mar 2025', 'flagging Apr 2025', 'Flagging May 2025', 'Flagging Jun 2025', 'Flagging Jul 2025', 'Flagging Agst 2025', 'Flagging Sept 2025', 'Flagging Oct 2025', 'Inquiry Nov 2025', 'Flagging Nov 2025', 'Inquiry Des 2025', 'Flagging Des 2025', 'Inquiry Jan 2026', 'Flagging Jan 2026', 'Inquiry Feb 2026', 'Flagging Feb 2026', 'Inquiry Mar 2026', 'Flagging Mar 2026', 'Inquiry Apr 2026', 'Flagging Apr 2026', 'Inquiry Mei 2026', 'Flagging Mei 2026', 'Inquiry Juni 2026', 'Flagging Juni 2026', 'Inquiry Juli 2026', 'Flagging Juli 2026', 'Inquiry Agustus 2026', 'Flagging Agustus 2026', 'Inquiry September 20262'];
    }
    public function index(): View
    {
        $years = ListrikAll::query()
            ->distinct()
            ->pluck('tahun')
            ->map(fn ($year) => (int) $year)
            ->merge(
                PaymentPlnMasterMonthly::query()
                    ->distinct()
                    ->pluck('tahun')
                    ->map(fn ($year) => (int) $year)
            )
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        if (empty($years)) {
            $years = [(int) now()->year];
        }

        return view('electricity.centralized.listrik-all.index', [
            'years' => $years,
            'selectedYear' => (int) $years[0],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $tahun = $request->filled('tahun')
            ? (int) $request->tahun
            : (int) max(
                (int) ListrikAll::query()->max('tahun'),
                (int) PaymentPlnMasterMonthly::query()->max('tahun')
            );

        if ($tahun === 0) {
            $tahun = (int) now()->year;
        }

        $hasPaymentMaster = PaymentPlnMasterMonthly::query()
            ->where('tahun', $tahun)
            ->exists();
        $hasListrikAll = ListrikAll::query()->where('tahun', $tahun)->exists();

        if ($hasPaymentMaster && $hasListrikAll) {
            $payment = DB::table('payment_pln_master_monthly')
                ->where('tahun', $tahun)
                ->select([
                    'site_id',
                    DB::raw("GROUP_CONCAT(DISTINCT id_pelanggan ORDER BY id_pelanggan SEPARATOR ', ') as payment_ids"),
                    DB::raw('MAX(site_name) as payment_site_name'),
                    DB::raw('MAX(status_aktif_site) as status_aktif_site'),
                    DB::raw('SUM(CASE WHEN bulan = 1 THEN COALESCE(amount, 0) ELSE 0 END) as payment_jan'),
                    DB::raw('SUM(CASE WHEN bulan = 2 THEN COALESCE(amount, 0) ELSE 0 END) as payment_feb'),
                    DB::raw('SUM(CASE WHEN bulan = 3 THEN COALESCE(amount, 0) ELSE 0 END) as payment_mar'),
                    DB::raw('SUM(CASE WHEN bulan = 4 THEN COALESCE(amount, 0) ELSE 0 END) as payment_apr'),
                    DB::raw('SUM(CASE WHEN bulan = 5 THEN COALESCE(amount, 0) ELSE 0 END) as payment_mei'),
                    DB::raw('SUM(CASE WHEN bulan = 6 THEN COALESCE(amount, 0) ELSE 0 END) as payment_jun'),
                    DB::raw('SUM(CASE WHEN bulan = 7 THEN COALESCE(amount, 0) ELSE 0 END) as payment_jul'),
                    DB::raw('SUM(CASE WHEN bulan = 8 THEN COALESCE(amount, 0) ELSE 0 END) as payment_ags'),
                    DB::raw('SUM(CASE WHEN bulan = 9 THEN COALESCE(amount, 0) ELSE 0 END) as payment_sep'),
                    DB::raw('SUM(CASE WHEN bulan = 10 THEN COALESCE(amount, 0) ELSE 0 END) as payment_okt'),
                    DB::raw('SUM(CASE WHEN bulan = 11 THEN COALESCE(amount, 0) ELSE 0 END) as payment_nov'),
                    DB::raw('SUM(CASE WHEN bulan = 12 THEN COALESCE(amount, 0) ELSE 0 END) as payment_des'),
                    DB::raw('COUNT(CASE WHEN bulan = 1 THEN 1 END) as jan_rows'),
                    DB::raw('COUNT(CASE WHEN bulan = 2 THEN 1 END) as feb_rows'),
                    DB::raw('COUNT(CASE WHEN bulan = 3 THEN 1 END) as mar_rows'),
                    DB::raw('COUNT(CASE WHEN bulan = 4 THEN 1 END) as apr_rows'),
                    DB::raw('COUNT(CASE WHEN bulan = 5 THEN 1 END) as mei_rows'),
                    DB::raw('COUNT(CASE WHEN bulan = 6 THEN 1 END) as jun_rows'),
                    DB::raw('COUNT(CASE WHEN bulan = 7 THEN 1 END) as jul_rows'),
                    DB::raw('COUNT(CASE WHEN bulan = 8 THEN 1 END) as ags_rows'),
                    DB::raw('COUNT(CASE WHEN bulan = 9 THEN 1 END) as sep_rows'),
                    DB::raw('COUNT(CASE WHEN bulan = 10 THEN 1 END) as okt_rows'),
                    DB::raw('COUNT(CASE WHEN bulan = 11 THEN 1 END) as nov_rows'),
                    DB::raw('COUNT(CASE WHEN bulan = 12 THEN 1 END) as des_rows'),
                ])
                ->groupBy('site_id');

            $query = DB::table('listrik_all as la')
                ->leftJoinSub($payment, 'p', 'p.site_id', '=', 'la.site_id')
                ->where('la.tahun', $tahun)
                ->select([
                    'la.site_id', 'la.gol_tarif', 'la.unit_pln',
                    DB::raw('COALESCE(p.payment_ids, la.id_pelanggan) as id_pelanggan'),
                    DB::raw('COALESCE(p.payment_site_name, la.site_name) as site_name'),
                    DB::raw('COALESCE(CASE WHEN p.jan_rows > 0 THEN p.payment_jan ELSE la.jan END, 0) as jan'),
                    DB::raw('COALESCE(CASE WHEN p.feb_rows > 0 THEN p.payment_feb ELSE la.feb END, 0) as feb'),
                    DB::raw('COALESCE(CASE WHEN p.mar_rows > 0 THEN p.payment_mar ELSE la.mar END, 0) as mar'),
                    DB::raw('COALESCE(CASE WHEN p.apr_rows > 0 THEN p.payment_apr ELSE la.apr END, 0) as apr'),
                    DB::raw('COALESCE(CASE WHEN p.mei_rows > 0 THEN p.payment_mei ELSE la.mei END, 0) as mei'),
                    DB::raw('COALESCE(CASE WHEN p.jun_rows > 0 THEN p.payment_jun ELSE la.jun END, 0) as jun'),
                    DB::raw('COALESCE(CASE WHEN p.jul_rows > 0 THEN p.payment_jul ELSE la.jul END, 0) as jul'),
                    DB::raw('COALESCE(CASE WHEN p.ags_rows > 0 THEN p.payment_ags ELSE la.ags END, 0) as ags'),
                    DB::raw('COALESCE(CASE WHEN p.sep_rows > 0 THEN p.payment_sep ELSE la.sep END, 0) as sep'),
                    DB::raw('COALESCE(CASE WHEN p.okt_rows > 0 THEN p.payment_okt ELSE la.okt END, 0) as okt'),
                    DB::raw('COALESCE(CASE WHEN p.nov_rows > 0 THEN p.payment_nov ELSE la.nov END, 0) as nov'),
                    DB::raw('COALESCE(CASE WHEN p.des_rows > 0 THEN p.payment_des ELSE la.des END, 0) as des'),
                ])
                ->orderBy('la.site_id');
        } elseif ($hasPaymentMaster) {
            $query = DB::table('payment_pln_master_monthly')
                ->where('tahun', $tahun)
                ->select([
                    'site_id',
                    DB::raw("GROUP_CONCAT(DISTINCT id_pelanggan ORDER BY id_pelanggan SEPARATOR ', ') as id_pelanggan"),
                    DB::raw('MAX(site_name) as site_name'),
                    DB::raw('MAX(status_aktif_site) as status_aktif_site'),
                    DB::raw('NULL as gol_tarif'),
                    DB::raw('NULL as unit_pln'),
                    DB::raw('SUM(CASE WHEN bulan = 1 THEN COALESCE(amount, 0) ELSE 0 END) as jan'),
                    DB::raw('SUM(CASE WHEN bulan = 2 THEN COALESCE(amount, 0) ELSE 0 END) as feb'),
                    DB::raw('SUM(CASE WHEN bulan = 3 THEN COALESCE(amount, 0) ELSE 0 END) as mar'),
                    DB::raw('SUM(CASE WHEN bulan = 4 THEN COALESCE(amount, 0) ELSE 0 END) as apr'),
                    DB::raw('SUM(CASE WHEN bulan = 5 THEN COALESCE(amount, 0) ELSE 0 END) as mei'),
                    DB::raw('SUM(CASE WHEN bulan = 6 THEN COALESCE(amount, 0) ELSE 0 END) as jun'),
                    DB::raw('SUM(CASE WHEN bulan = 7 THEN COALESCE(amount, 0) ELSE 0 END) as jul'),
                    DB::raw('SUM(CASE WHEN bulan = 8 THEN COALESCE(amount, 0) ELSE 0 END) as ags'),
                    DB::raw('SUM(CASE WHEN bulan = 9 THEN COALESCE(amount, 0) ELSE 0 END) as sep'),
                    DB::raw('SUM(CASE WHEN bulan = 10 THEN COALESCE(amount, 0) ELSE 0 END) as okt'),
                    DB::raw('SUM(CASE WHEN bulan = 11 THEN COALESCE(amount, 0) ELSE 0 END) as nov'),
                    DB::raw('SUM(CASE WHEN bulan = 12 THEN COALESCE(amount, 0) ELSE 0 END) as des'),
                ])
                ->groupBy('site_id')
                ->orderBy('site_id');
        } else {
            $query = ListrikAll::query()->where('tahun', $tahun)->orderBy('id');
        }

        $fmt = fn ($val) => $val > 0 ? 'Rp '.number_format($val, 0, ',', '.') : '-';

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('jan', fn ($l) => $fmt($l->jan))
            ->editColumn('feb', fn ($l) => $fmt($l->feb))
            ->editColumn('mar', fn ($l) => $fmt($l->mar))
            ->editColumn('apr', fn ($l) => $fmt($l->apr))
            ->editColumn('mei', fn ($l) => $fmt($l->mei))
            ->editColumn('jun', fn ($l) => $fmt($l->jun))
            ->editColumn('jul', fn ($l) => $fmt($l->jul))
            ->editColumn('ags', fn ($l) => $fmt($l->ags))
            ->editColumn('sep', fn ($l) => $fmt($l->sep))
            ->editColumn('okt', fn ($l) => $fmt($l->okt))
            ->editColumn('nov', fn ($l) => $fmt($l->nov))
            ->editColumn('des', fn ($l) => $fmt($l->des))
            ->toJson();
    }

    public function exportExcel(Request $request)
    {
        $tahun = $request->filled('tahun')
            ? (int) $request->tahun
            : (int) max(
                (int) ListrikAll::query()->max('tahun'),
                (int) PaymentPlnMasterMonthly::query()->max('tahun')
            );

        if ($tahun === 0) {
            $tahun = (int) now()->year;
        }

        $filename = 'listrik_all_'.$tahun.'_'.now()->format('Ymd_His').'.xlsx';

        return (new ListrikAllExport($tahun))->download($filename);
    }

    public function upload(Request $request)
    {
        $request->validate(['listrik_all_file' => ['required', 'file', 'mimes:xls,xlsx', 'max:51200']]);

        try {
            DB::transaction(function () use ($request): void {
                DB::table('listrik_all')->delete();
                Excel::import(new CentralizedListrikAllImport(), $request->file('listrik_all_file'));
            });
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['listrik_all_file' => 'File Listrik All gagal diproses: '.$e->getMessage()]);
        }

        return back()->with('success', 'Upload Listrik All berhasil dan data website telah diperbarui.');
    }
}
