<?php

namespace Tests\Feature;

use App\Exports\DataAssetTowerExport;
use App\Exports\SiteOwnerExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class FeasibilityDrilldownTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    public function test_chart_filters_and_excel_select_the_same_sites_including_empty_categories(): void
    {
        $this->freezeTime();
        $this->actingAs($this->userWithRole('viewer'));
        $ids = [];
        foreach ([
            ['Alpha', 'BEKASI', 'Gold', 'MITRATEL', 'Makro', 'MLA'],
            ['Beta', ' BEKASI ', ' gold ', 'TBG', 'Urban', 'Non MLA'],
            ['Gamma', 'BOGOR', 'Silver', null, null, null],
            ['Delta', null, 'Gold', ' ', 'Makro', 'MLA'],
        ] as $index => [$name, $nop, $class, $owner, $coverage, $mla]) {
            $ids[] = DB::table('site_owners')->insertGetId([
                'site_code' => 'SITE'.$index, 'site_name' => $name, 'nop' => $nop,
                'site_class' => $class, 'site_owner' => $owner, 'coverage_type' => $coverage, 'status_mla' => $mla,
            ]);
        }
        foreach ([
            [['nop' => 'BEKASI', 'site_class' => 'Gold'], [$ids[0], $ids[1]]],
            [['site_owner' => 'mitratel'], [$ids[0]]],
            [['coverage_type' => 'Makro'], [$ids[0], $ids[3]]],
            [['status_mla' => 'Non MLA'], [$ids[1]]],
            [['status_mla' => 'Belum diisi'], [$ids[2]]],
            [['site_owner' => 'Belum diisi'], [$ids[2], $ids[3]]],
            [['nop' => 'Belum diisi'], [$ids[3]]],
            [['site_class' => 'Gold', 'coverage_type' => 'Makro', 'status_mla' => 'MLA'], [$ids[0], $ids[3]]],
            [['nop' => 'BEKASI', 'site_class' => 'Gold', 'search' => 'Beta'], [$ids[1]]],
        ] as [$filters, $expected]) {
            $tableFilters = $filters;
            if (isset($filters['search'])) {
                $tableFilters['search'] = ['value' => $filters['search'], 'regex' => false];
            }
            $response = $this->getJson(route('data-potensi.site-owner.data', $tableFilters + ['draw' => 1, 'start' => 0, 'length' => 10]))
                ->assertOk()->assertJsonPath('recordsFiltered', count($expected));
            $this->assertEqualsCanonicalizing($expected, array_column($response->json('data'), 'id'));
            Excel::fake();
            $this->get(route('data-potensi.site-owner.export-excel', $filters))->assertOk();
            Excel::assertDownloaded('site-owner-'.now()->format('Ymd-His').'.xlsx', function (SiteOwnerExport $export) use ($expected) {
                $this->assertEqualsCanonicalizing($expected, $export->query()->pluck('id')->all());

                return true;
            });
        }
        $this->getJson(route('data-potensi.site-owner.data', ['site_class' => ['Gold']]))->assertUnprocessable();
    }

    public function test_tower_type_click_and_export_keep_only_the_selected_assets(): void
    {
        $this->freezeTime();
        $this->actingAs($this->userWithRole('viewer'));
        foreach (['SST 4Leg', ' SST 4Leg ', 'SST 3Leg', null] as $index => $type) {
            DB::table('data_asset_towers')->insert(['site_id' => 'TOWER'.$index, 'tower_type' => $type]);
        }
        foreach ([['sst 4leg', ['TOWER0', 'TOWER1']], ['Belum diisi', ['TOWER3']]] as [$type, $expected]) {
            $filters = ['tower_type' => $type];
            $response = $this->getJson(route('data-potensi.asset-tower.data', $filters))->assertOk();
            $this->assertEqualsCanonicalizing($expected, array_column($response->json('data'), 'site_id'));
            $this->assertArrayHasKey('tower_type', $response->json('data.0'));
            Excel::fake();
            $this->get(route('data-potensi.asset-tower.export-excel', $filters))->assertOk();
            Excel::assertDownloaded('data-asset-tower-'.now()->format('Ymd-His').'.xlsx', function (DataAssetTowerExport $export) use ($expected) {
                $this->assertEqualsCanonicalizing($expected, $export->query()->pluck('site_id')->all());

                return true;
            });
        }
    }

    public function test_real_excel_includes_all_matching_rows_beyond_the_visible_page(): void
    {
        $this->actingAs($this->userWithRole('viewer'));
        foreach (['A', 'B', 'C'] as $code) {
            DB::table('site_owners')->insert(['site_code' => $code, 'site_name' => 'Matched '.$code, 'site_owner' => 'MITRATEL', 'status_mla' => 'MLA']);
        }
        DB::table('site_owners')->insert(['site_code' => 'OTHER', 'site_owner' => 'TBG']);
        $filters = ['site_owner' => 'MITRATEL', 'status_mla' => 'MLA', 'search' => 'Matched'];
        $this->getJson(route('data-potensi.site-owner.data', $filters + ['start' => 0, 'length' => 1]))
            ->assertOk()->assertJsonPath('recordsFiltered', 3)->assertJsonCount(1, 'data');
        $response = $this->get(route('data-potensi.site-owner.export-excel', $filters + ['start' => 0, 'length' => 1]))->assertOk();
        $file = $response->baseResponse->getFile()->getPathname();
        try {
            $workbook = IOFactory::load($file);
            $rows = $workbook->getActiveSheet()->toArray();
            $this->assertCount(4, $rows);
            $this->assertSame('Site ID', $rows[0][1]);
            $this->assertEqualsCanonicalizing(['A', 'B', 'C'], array_column(array_slice($rows, 1), 1));
            $this->assertSame(['MITRATEL', 'MITRATEL', 'MITRATEL'], array_column(array_slice($rows, 1), 13));
            $workbook->disconnectWorksheets();
        } finally {
            unlink($file);
        }
        $this->app['auth']->forgetGuards();
        $this->get(route('data-potensi.site-owner.export-excel'))->assertRedirect(route('login'));
    }
}
