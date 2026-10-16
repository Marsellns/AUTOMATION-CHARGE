<?php

namespace App\Http\Controllers;

use App\Support\DatasetCategoryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ModuleDashboardController extends Controller
{
    private const PO_DATASETS = [
        'hq' => [
            'title' => 'PO HQ', 'table' => 'po_hq', 'status' => 'status', 'route' => 'po-hq.index', 'tone' => 'blue',
            'columns' => ['po_number' => 'Nomor PO', 'vendor_name' => 'Vendor', 'description' => 'Deskripsi', 'expense_type' => 'Expense Type', 'status' => 'Status', 'agreement_number' => 'Nomor Agreement', 'location' => 'Lokasi', 'remark' => 'Catatan', 'update_by' => 'Diperbarui oleh', 'source_updated_at' => 'Tanggal Pembaruan'],
        ],
        'varcost' => [
            'title' => 'PO Varcost', 'table' => 'po_varcost', 'status' => 'gr_status', 'route' => 'po-varcost.index', 'tone' => 'mint',
            'columns' => ['po_number' => 'Nomor PO', 'description' => 'Deskripsi', 'expense_type' => 'Expense Type', 'gr_status' => 'Status GR', 'po_year' => 'Tahun PO', 'delivery_date' => 'Tanggal Delivery', 'update_by' => 'Diperbarui oleh', 'update_at' => 'Tanggal Pembaruan'],
        ],
        'presales' => [
            'title' => 'Dokumen Presales', 'table' => 'document_circulations', 'status' => 'status', 'route' => 'presales.index', 'tone' => 'violet',
            'columns' => ['document_title' => 'Judul Dokumen', 'document_number' => 'Nomor Dokumen', 'status' => 'Status', 'current_step' => 'Tahap Approval', 'uploaded_by_name' => 'Diunggah oleh', 'created_at' => 'Tanggal Unggah', 'rejected_reason' => 'Alasan Penolakan'],
        ],
    ];

    public function purchaseOrders(): View
    {
        $hq = DB::table('po_hq')->count();
        $varcost = DB::table('po_varcost')->count();
        $presales = DB::table('document_circulations')->count();

        return view('modules.dashboard', [
            'title' => 'PO Monitoring',
            'description' => 'Pantau data purchase order, status GR, dan proses dokumen Presales.',
            'image' => 'purchase-order',
            'stats' => ['PO HQ' => $hq, 'PO Varcost' => $varcost, 'Dokumen Presales' => $presales],
            'datasets' => collect(self::PO_DATASETS)->map(fn ($dataset) => [
                'title' => $dataset['title'], 'url' => route($dataset['route']),
                'columns' => $dataset['columns'], 'tone' => $dataset['tone'],
            ])->all(),
            'charts' => [
                ['title' => 'Data per Submodul', 'type' => 'column', 'unit' => 'Baris data', 'dataset' => 'hq', 'data' => [
                    ['name' => 'PO HQ', 'y' => $hq, 'dataset' => 'hq', 'color' => '#3b82f6'],
                    ['name' => 'PO Varcost', 'y' => $varcost, 'dataset' => 'varcost', 'color' => '#0d9488'],
                    ['name' => 'Presales', 'y' => $presales, 'dataset' => 'presales', 'color' => '#8b5cf6'],
                ]],
                ['title' => 'Status PO HQ', 'type' => 'pie', 'unit' => 'PO', 'dataset' => 'hq', 'data' => $this->distribution('po_hq', 'status')],
                ['title' => 'Status GR Varcost', 'type' => 'bar', 'unit' => 'PO', 'dataset' => 'varcost', 'data' => $this->distribution('po_varcost', 'gr_status')],
                ['title' => 'Status Dokumen Presales', 'type' => 'pie', 'unit' => 'Dokumen', 'dataset' => 'presales', 'data' => $this->distribution('document_circulations', 'status')],
            ],
        ]);
    }

    public function purchaseOrderDetails(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'dataset' => ['required', 'in:hq,varcost,presales'],
            'category' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'array'],
            'search.value' => ['nullable', 'string', 'max:255'],
            'draw' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $dataset = self::PO_DATASETS[$filters['dataset']];
        $query = DB::table($dataset['table']);
        $total = (clone $query)->count();
        $category = trim($filters['category'] ?? '');
        if ($category !== '') {
            // Match the same trimmed/empty categories used by the chart totals.
            $query->whereRaw(DatasetCategoryFilters::label($dataset['status']).' = ?', [$category]);
        }
        $search = trim($filters['search']['value'] ?? '');
        if ($search !== '') {
            $query->where(function ($query) use ($dataset, $search) {
                foreach (array_keys($dataset['columns']) as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        return response()->json([
            'draw' => (int) ($filters['draw'] ?? 0),
            'recordsTotal' => $total,
            'recordsFiltered' => (clone $query)->count(),
            'data' => $query->select(['id', ...array_keys($dataset['columns'])])
                ->orderByDesc('id')->offset((int) ($filters['start'] ?? 0))
                ->limit((int) ($filters['length'] ?? 10))->get(),
        ]);
    }

    public function feasibility(): View
    {
        // Use the Site Owner snapshot directly: the combined Data Site view can
        // contain multiple resource rows for one site and would inflate totals.
        $summary = DB::table('site_owners')->selectRaw("COUNT(*) AS total,
            COUNT(DISTINCT NULLIF(TRIM(site_owner), '')) AS owners,
            COUNT(DISTINCT NULLIF(TRIM(site_class), '')) AS classes,
            COUNT(DISTINCT NULLIF(TRIM(coverage_type), '')) AS coverage")->first();
        $owners = $this->distribution('site_owners', 'site_owner');
        $coverage = $this->distribution('site_owners', 'coverage_type');
        $towers = $this->distribution('data_asset_towers', 'tower_type');
        $mla = ['MLA' => 0, 'Non MLA' => 0];
        foreach ($this->distribution('site_owners', 'status_mla') as $status) {
            $mla[$status['name']] = $status['y'];
        }

        return view('data-potensi.dashboard', [
            'stats' => [
                'Total Site' => (int) $summary->total,
                'Site Owner' => (int) $summary->owners,
                'Site Class' => (int) $summary->classes,
                'Coverage Type' => (int) $summary->coverage,
            ],
            'classifications' => ['owners' => $owners, 'coverage' => $coverage, 'classes' => $this->distribution('site_owners', 'site_class')],
            'charts' => [
                'siteClass' => $this->siteClassesByNop(),
                'mla' => array_map(fn ($name, $total) => ['name' => $name, 'y' => $total], array_keys($mla), array_values($mla)),
                'nop' => $this->distribution('site_owners', 'nop'),
                'tower' => $towers,
            ],
        ]);
    }

    private function siteClassesByNop(): array
    {
        $nop = "COALESCE(NULLIF(TRIM(nop), ''), 'Belum diisi')";
        $class = "COALESCE(NULLIF(UPPER(TRIM(site_class)), ''), 'Belum diisi')";
        // Distinct aliases avoid MariaDB's ONLY_FULL_GROUP_BY expression bug
        // and prevent GROUP BY from resolving to the untrimmed source columns.
        $rows = DB::table('site_owners')->selectRaw("{$nop} AS nop_label, {$class} AS class_label, COUNT(*) AS total")
            ->groupBy('nop_label', 'class_label')->orderBy('nop_label')->orderBy('class_label')->get();
        $categories = $rows->pluck('nop_label')->unique()->values()->all();
        $classStyles = [
            'GOLD' => ['Gold', '#eab308'], 'SILVER' => ['Silver', '#94a3b8'],
            'BRONZE' => ['Bronze', '#16a34a'], 'PLATINUM' => ['Platinum', '#334155'],
            'DIAMOND' => ['Diamond', '#ef4444'],
        ];
        $classes = $rows->pluck('class_label')->unique()->all();
        $classes = array_values(array_unique([...array_intersect(array_keys($classStyles), $classes), ...$classes]));
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->class_label][$row->nop_label] = (int) $row->total;
        }

        return [
            'categories' => $categories,
            'series' => array_map(fn ($class) => array_filter([
                'name' => $classStyles[$class][0] ?? $class,
                'color' => $classStyles[$class][1] ?? null,
                'data' => array_map(fn ($nop) => $counts[$class][$nop] ?? 0, $categories),
            ], fn ($value) => $value !== null), $classes),
        ];
    }

    /** Table and column names are fixed by the module definitions above. */
    private function distribution(string $table, string $column): array
    {
        $label = DatasetCategoryFilters::label($column);

        return DB::table($table)->selectRaw("{$label} as name, COUNT(*) as total")
            ->groupBy('name')->orderByDesc('total')->orderBy('name')->get()
            ->map(fn ($row) => ['name' => $row->name, 'y' => (int) $row->total])->all();
    }
}
