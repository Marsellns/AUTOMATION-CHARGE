<?php

namespace Tests\Feature;

use App\Models\PoVarcost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class ModuleDashboardTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    public function test_po_graphs_count_real_records_per_status_and_submodule(): void
    {
        foreach (['Approved', 'Approved', 'Draft'] as $index => $status) {
            DB::table('po_hq')->insert(['po_number' => 'PO-'.$index, 'vendor_name' => 'Vendor', 'expense_type' => 'Capex', 'status' => $status]);
        }
        PoVarcost::factory()->create(['gr_status' => 'Complete']);
        PoVarcost::factory()->create(['gr_status' => ' Complete ']);
        PoVarcost::factory()->create(['gr_status' => null]);
        DB::table('document_circulations')->insert([
            ['document_title' => 'A', 'file_name' => 'a.pdf', 'file_path' => 'private/a.pdf', 'status' => 'Pending'],
            ['document_title' => 'B', 'file_name' => 'b.pdf', 'file_path' => 'private/b.pdf', 'status' => 'Completed'],
        ]);

        $response = $this->actingAs($this->userWithRole('viewer'))->get(route('po-monitoring.dashboard'))
            ->assertOk()->assertSee('simaster.modules.po-monitoring.pages.po-dashboard-page', false)
            ->assertViewHas('stats', ['PO HQ' => 3, 'PO Varcost' => 3, 'Dokumen Presales' => 2]);
        $charts = $response->viewData('charts');
        $this->assertSame(['column', 'pie', 'bar', 'pie'], array_column($charts, 'type'));
        $this->assertSame([3, 3, 2], array_column($charts[0]['data'], 'y'));
        $this->assertEqualsCanonicalizing([['name' => 'Approved', 'y' => 2], ['name' => 'Draft', 'y' => 1]], $charts[1]['data']);
        $this->assertEqualsCanonicalizing([['name' => 'Complete', 'y' => 2], ['name' => 'Belum diisi', 'y' => 1]], $charts[2]['data']);
        $this->assertSame(2, array_sum(array_column($charts[3]['data'], 'y')));
    }

    public function test_feasibility_graphs_preserve_unfilled_categories_and_asset_counts(): void
    {
        foreach (['BEKASI', ' BEKASI ', null, ''] as $index => $nop) {
            DB::table('site_owners')->insert([
                'site_code' => 'SITE'.$index, 'nop' => $nop,
                'site_owner' => $index < 2 ? 'Telkomsel' : null,
                'coverage_type' => $index < 2 ? 'Urban' : null,
            ]);
        }
        foreach (['SST', 'SST', null] as $index => $type) {
            DB::table('data_asset_towers')->insert(['site_id' => 'TOWER'.$index, 'tower_type' => $type]);
        }
        $response = $this->actingAs($this->userWithRole('viewer'))->get(route('data-potensi.dashboard'))
            ->assertOk()->assertSee('simaster.modules.data-potensi.pages.feasibility-dashboard-page', false);
        $this->assertSame(['Total Site' => 4, 'Site Owner' => 1, 'Site Class' => 0, 'Coverage Type' => 1], $response->viewData('stats'));
        $charts = $response->viewData('charts');
        $this->assertEqualsCanonicalizing([['name' => 'BEKASI', 'y' => 2], ['name' => 'Belum diisi', 'y' => 2]], $charts['nop']);
        $this->assertEqualsCanonicalizing([['name' => 'SST', 'y' => 2], ['name' => 'Belum diisi', 'y' => 1]], $charts['tower']);
        foreach (array_intersect_key($response->viewData('classifications'), array_flip(['owners', 'coverage'])) as $points) {
            $this->assertSame(4, array_sum(array_column($points, 'y')));
            $this->assertContains(['name' => 'Belum diisi', 'y' => 2], $points);
        }
    }

    public function test_site_class_matrix_fills_missing_combinations_and_mla_preserves_unknown_status(): void
    {
        foreach ([
            ['BEKASI', 'Gold', 'MLA'], [' BEKASI ', ' gold ', 'MLA'],
            ['BOGOR', 'Silver', 'Non MLA'], ['BOGOR', 'Diamond', null],
            [null, null, null], ['BOGOR', 'Custom', 'MLA'],
        ] as $index => [$nop, $class, $mla]) {
            DB::table('site_owners')->insert([
                'site_code' => 'CLASS'.$index, 'nop' => $nop, 'site_class' => $class,
                'status_mla' => $mla, 'site_owner' => $index < 2 ? ' TBG ' : 'TBG',
                'coverage_type' => $index < 3 ? 'Urban' : null,
            ]);
        }
        $response = $this->actingAs($this->userWithRole('viewer'))->get(route('data-potensi.dashboard'))->assertOk();
        $this->assertSame(['Total Site' => 6, 'Site Owner' => 1, 'Site Class' => 4, 'Coverage Type' => 1], $response->viewData('stats'));
        $charts = $response->viewData('charts');
        $matrix = $charts['siteClass'];
        $this->assertSame(['BEKASI', 'Belum diisi', 'BOGOR'], $matrix['categories']);
        $series = collect($matrix['series'])->keyBy('name');
        $this->assertSame([2, 0, 0], $series['Gold']['data']);
        $this->assertSame([0, 0, 1], $series['Silver']['data']);
        $this->assertSame([0, 0, 1], $series['Diamond']['data']);
        $this->assertSame([0, 1, 0], $series['Belum diisi']['data']);
        $this->assertSame([0, 0, 1], $series['CUSTOM']['data']);
        $this->assertSame(6, $series->sum(fn ($series) => array_sum($series['data'])));
        $this->assertEqualsCanonicalizing([
            ['name' => 'MLA', 'y' => 3], ['name' => 'Non MLA', 'y' => 1], ['name' => 'Belum diisi', 'y' => 2],
        ], $charts['mla']);
        $this->assertSame([['name' => 'TBG', 'y' => 6]], $response->viewData('classifications')['owners']);
        $response->assertSee('Cari site owner')->assertSee('Cari coverage')
            ->assertSee('NOP per Site Class')->assertSee('Status MLA &amp; Non MLA', false)
            ->assertSee('feasibility-category-card', false);
    }

    public function test_empty_dashboards_and_authentication(): void
    {
        $this->get(route('po-monitoring.dashboard'))->assertRedirect(route('login'));
        $this->get(route('data-potensi.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('viewer'));
        foreach (['po-monitoring.dashboard', 'data-potensi.dashboard'] as $route) {
            $response = $this->get(route($route))->assertOk();
            $this->assertSame(0, array_sum($response->viewData('stats')));
            if ($route === 'po-monitoring.dashboard') {
                foreach ($response->viewData('charts') as $chart) {
                    $this->assertSame(0, array_sum(array_column($chart['data'], 'y')));
                }
            } else {
                $this->assertSame([], $response->viewData('charts')['siteClass']['series']);
                $this->assertSame([], $response->viewData('classifications')['owners']);
                $this->assertSame([], $response->viewData('classifications')['coverage']);
                $this->assertSame(0, array_sum(array_column($response->viewData('charts')['mla'], 'y')));
            }
        }
    }
}
