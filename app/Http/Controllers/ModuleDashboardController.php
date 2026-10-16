<?php

namespace App\Http\Controllers;

use App\Support\DatasetCategoryFilters;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ModuleDashboardController extends Controller
{
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
            'charts' => [
                ['title' => 'Data per Submodul', 'type' => 'column', 'unit' => 'Baris data', 'route' => 'po-hq.index', 'data' => [
                    ['name' => 'PO HQ', 'y' => $hq], ['name' => 'PO Varcost', 'y' => $varcost], ['name' => 'Presales', 'y' => $presales],
                ]],
                ['title' => 'Status PO HQ', 'type' => 'pie', 'unit' => 'PO', 'route' => 'po-hq.index', 'data' => $this->distribution('po_hq', 'status')],
                ['title' => 'Status GR Varcost', 'type' => 'bar', 'unit' => 'PO', 'route' => 'po-varcost.index', 'data' => $this->distribution('po_varcost', 'gr_status')],
                ['title' => 'Status Dokumen Presales', 'type' => 'pie', 'unit' => 'Dokumen', 'route' => 'presales.index', 'data' => $this->distribution('document_circulations', 'status')],
            ],
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
