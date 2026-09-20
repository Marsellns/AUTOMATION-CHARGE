<?php

namespace App\Http\Controllers;

use App\Imports\Datasets\BapssImport;
use App\Imports\Datasets\CombatSiteImport;
use App\Imports\Datasets\DataSiteUnlockImport;
use App\Imports\Datasets\JaknetContractImport;
use App\Imports\Datasets\RecurringIpasImport;
use App\Imports\Datasets\RecurringTagihanIpasImport;
use App\Imports\Datasets\SewaLahanRenewalImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;

class InfrastructureUploadController extends Controller
{
    private const DATASETS = [
        'sewa-lahan' => ['label' => 'Sewa Lahan', 'table' => 'sewa_lahan_renewals', 'import' => SewaLahanRenewalImport::class],
        'combat' => ['label' => 'Combat', 'table' => 'combat_sites', 'import' => CombatSiteImport::class],
        'recurring-ipas' => ['label' => 'Recurring ANT & Ipas', 'table' => 'recurring_ipas', 'import' => RecurringIpasImport::class],
        'recurring-tagihan-ipas' => ['label' => 'Recurring Tagihan Ipas', 'table' => 'recurring_tagihan_ipas', 'import' => RecurringTagihanIpasImport::class],
        'jaknet' => ['label' => 'Jaknet & Dapot', 'table' => 'jaknet_contracts', 'import' => JaknetContractImport::class],
        'site-unlock' => ['label' => 'Data Site Unlock', 'table' => 'data_site_unlocks', 'import' => DataSiteUnlockImport::class],
        'bapss' => ['label' => 'BAPSS', 'table' => 'bapss', 'import' => BapssImport::class],
    ];

    public function create(string $dataset): View
    {
        abort_unless(isset(self::DATASETS[$dataset]), 404);
        return view('infrastruktur.upload', ['dataset' => $dataset, 'definition' => self::DATASETS[$dataset]]);
    }

    public function template(string $dataset)
    {
        abort_unless(isset(self::DATASETS[$dataset]), 404);

        $headers = match ($dataset) {
            'recurring-tagihan-ipas' => ['Site ID', 'Site Name', 'TP', 'Contract Type', 'Termin', 'Periode Ke', 'Termin Start', 'Termin End', 'Amount', 'Batch Name'],
            'site-unlock' => ['Site ID', 'Site Name', 'Class', 'City', 'Batch', 'Status', 'Final Status', 'Update By', 'Tanggal'],
            default => ['Site ID', 'Site Name'],
        };

        $rows = $dataset === 'recurring-tagihan-ipas'
            ? [$headers]
            : [['SIMASTER - Template Dataset'], $headers];

        return Excel::download(new class($rows) implements FromArray {
            public function __construct(private readonly array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        }, 'template-'.str_replace('-', '_', $dataset).'.xlsx');
    }

    public function export(string $dataset)
    {
        abort_unless(isset(self::DATASETS[$dataset]), 404);

        $definitions = [
            'recurring-tagihan-ipas' => [
                'headers' => ['Site ID', 'Site Name', 'TP', 'Contract Type', 'Termin', 'Periode Ke', 'Termin Start', 'Termin End', 'Amount', 'Batch Name'],
                'columns' => ['site_code', 'site_name', 'tp', 'contract_type', 'termin', 'periode_ke', 'termin_start', 'termin_end', 'amount', 'batch_name'],
            ],
            'site-unlock' => [
                'headers' => ['Site ID', 'Site Name', 'Class', 'City', 'Batch', 'Status', 'Final Status', 'Update By', 'Tanggal'],
                'columns' => ['site_code', 'site_name', 'site_class', 'city', 'batch', 'status', 'final_status', 'update_by', 'tanggal'],
            ],
        ];
        abort_unless(isset($definitions[$dataset]), 404);

        $definition = $definitions[$dataset];
        $rows = DB::table(self::DATASETS[$dataset]['table'])
            ->select($definition['columns'])
            ->get()
            ->map(fn ($row) => array_map(fn ($column) => $row->{$column}, $definition['columns']))
            ->all();

        array_unshift($rows, $definition['headers']);

        return Excel::download(new class($rows) implements FromArray {
            public function __construct(private readonly array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        }, $dataset.'-'.now()->format('Ymd_His').'.xlsx');
    }

    public function store(Request $request, string $dataset): RedirectResponse
    {
        abort_unless(isset(self::DATASETS[$dataset]), 404);
        $request->validate(['dataset_file' => 'required|file|mimes:xlsx,xls,csv|max:51200']);
        $definition = self::DATASETS[$dataset];
        $import = new $definition['import']();

        try {
            // Dataset files are snapshots. Replacing the snapshot avoids stale rows while
            // preserving the same idempotent behaviour as the CLI importer.
            DB::transaction(function () use ($definition, $import, $request): void {
                DB::table($definition['table'])->delete();
                Excel::import($import, $request->file('dataset_file'));
            });
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['dataset_file' => 'File gagal diproses: '.$e->getMessage()])->withInput();
        }

        $stats = $import->getStats();
        return redirect()->route('infrastruktur.'.$dataset.'.index')
            ->with('success', "Upload {$definition['label']} berhasil: {$stats['inserted']} baris disimpan, {$stats['skipped']} baris dilewati.");
    }
}
