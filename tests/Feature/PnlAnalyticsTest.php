<?php

namespace Tests\Feature;

use App\Exports\PnlExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PnlAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function seedPortfolio(): void
    {
        foreach ([
            'A001' => ['NOP BEKASI', 'BEKASI', [[1, 100, 20, 0], [2, 0, 20, 0], [3, 1000, 0, 1]]],
            'B001' => ['NOP BEKASI', 'KARAWANG', [[1, 30, 50, 0]]],
            'C001' => ['NOP BEKASI', 'BEKASI', [[1, 0, 0, 0]]],
            'D001' => ['NOP BOGOR', 'BOGOR', []],
            'E001' => [null, null, [[1, 10, 5, 0]]],
        ] as $code => [$nop, $city, $metrics]) {
            $id = DB::table('sites')->insertGetId(['site_id' => $code, 'site_name' => 'Site '.$code]);
            if ($nop !== null) DB::table('site_owners')->insert(['site_code' => $code, 'nop' => $nop, 'city' => $city]);
            foreach ($metrics as [$month, $revenue, $cost, $anomaly]) {
                DB::table('site_monthly_metrics')->insert([
                    'site_id' => $id, 'bulan' => $month, 'tahun' => 2026,
                    'revenue' => $revenue, 'cost' => $cost, 'profit_loss' => $revenue - $cost, 'is_anomaly' => $anomaly,
                ]);
            }
        }
    }

    public function test_groups_reconcile_to_the_selected_period_without_duplicate_master_totals(): void
    {
        $this->seedPortfolio();
        DB::table('site_owners')->insert(['site_code' => ' a001 ', 'nop' => 'NOP BEKASI', 'city' => 'BEKASI']);
        $this->actingAs(User::factory()->create(['account_status' => 'approved']));
        $response = $this->getJson(route('pnl.analytics', ['tahun' => 2026, 'bulan' => 'all']))
            ->assertOk()->assertJsonPath('sites_count', 5)->assertJsonPath('active_count', 4)
            ->assertJsonPath('status.0.y', 2)->assertJsonPath('status.1.y', 2)->assertJsonPath('status.2.y', 1);
        $data = $response->json();
        $nop = collect($data['nop'])->keyBy('key');
        $this->assertEquals(40, $nop['NOP BEKASI']['profit_loss']);
        $this->assertEquals(60, $nop['NOP BEKASI']['profit']);
        $this->assertEquals(-20, $nop['NOP BEKASI']['loss']);
        $this->assertEquals(45, collect($data['monthly'])->sum('profit_loss'));
        $this->assertEquals(45, collect($data['cities'])->sum('profit_loss'));
        $this->assertEquals(5, collect($data['nop'])->sum('sites'));
        $this->assertNull(collect($data['analysis'])->firstWhere('site_id', 'C001')['margin']);
        $this->getJson(route('pnl.analytics', ['tahun' => 2026, 'bulan' => 3, 'city' => 'bekasi']))
            ->assertOk()->assertJsonPath('analysis.0.site_id', 'A001')->assertJsonPath('analysis.0.profit_loss', 1000);
    }

    public function test_city_and_site_drilldowns_match_table_export_and_highlights(): void
    {
        $this->seedPortfolio();
        $this->actingAs(User::factory()->create(['account_status' => 'approved']));
        $filters = ['tahun' => 2026, 'bulan' => 'all', 'city' => 'KARAWANG'];
        $this->getJson(route('pnl.analytics', $filters))->assertOk()
            ->assertJsonPath('sites_count', 1)->assertJsonPath('analysis.0.site_id', 'B001');
        $this->getJson(route('pnl.highlights', $filters))->assertOk()
            ->assertJsonPath('summary.profit_loss', -20)->assertJsonPath('top_loss.0.site_id', 'B001');
        $this->getJson(route('pnl.data', [...$filters, 'draw' => 1, 'start' => 0, 'length' => 10]))->assertOk()
            ->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.site_id', 'B001')->assertJsonPath('data.0.city', 'KARAWANG');
        $this->assertSame(['B001'], (new PnlExport(null, 2026, null, null, 'KARAWANG'))->query()->pluck('site_id')->all());
        $export = new PnlExport(null, 2026, null, null, 'KARAWANG');
        $this->assertSame('KARAWANG', $export->map($export->query()->first())[4]);
        $this->assertSame(['A001'], (new PnlExport(null, 2026, null, null, null, 'A001'))->query()->pluck('site_id')->all());
        $this->assertSame(['E001'], (new PnlExport(null, 2026, null, '__unmapped__', '__unmapped__'))->query()->pluck('site_id')->all());
        $this->getJson(route('pnl.analytics', ['tahun' => 2026, 'bulan' => 'all', 'city' => '__unmapped__']))
            ->assertOk()->assertJsonPath('sites_count', 1)->assertJsonPath('analysis.0.site_id', 'E001');
        $this->getJson(route('pnl.highlights', ['tahun' => 2026, 'bulan' => 'all', 'nop' => '__unmapped__']))
            ->assertOk()->assertJsonPath('summary.profit_loss', 5)->assertJsonPath('profit.nop', 'Belum dipetakan');
        $this->getJson(route('pnl.data', ['tahun' => 2026, 'bulan' => 'all', 'status' => 'TidakAktif', 'city' => 'BOGOR', 'draw' => 1, 'length' => 10]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.site_id', 'D001');
    }

    public function test_tabs_render_and_empty_periods_and_invalid_requests_are_handled(): void
    {
        $this->getJson(route('pnl.analytics', ['tahun' => 2026, 'bulan' => 'all']))->assertUnauthorized();
        $this->actingAs(User::factory()->create(['account_status' => 'approved']));
        $this->getJson(route('pnl.analytics', ['tahun' => 2026, 'bulan' => 13]))->assertUnprocessable();
        $this->getJson(route('pnl.analytics', ['tahun' => 2026, 'bulan' => 'all']))->assertOk()
            ->assertJsonPath('sites_count', 0)->assertJsonCount(0, 'monthly')->assertJsonCount(0, 'analysis');
        $this->get(route('pnl.index'))->assertOk()->assertSee('REPORT EASTERN JABOTABEK')
            ->assertSee('Site Performance')->assertSee('NOP Performance')->assertSee('Tren Bulanan')
            ->assertSee('Profit/Loss per NOP')->assertSee('Profit/Loss per City')->assertSee('Analisis Site')
            ->assertSee('pnl-panel-analysis', false)->assertSee('pnl-category-chart', false);
    }
}
