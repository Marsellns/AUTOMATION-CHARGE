<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteMonthlyMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataPotensiSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_financials_follow_selected_period_and_latest_default_without_invoice_multiplication(): void
    {
        $this->actingAs(User::factory()->create(['account_status' => 'approved']));
        $site = Site::factory()->create(['site_id' => 'TEST001']);
        DB::table('site_owners')->insert(['site_code' => 'TEST001', 'site_name' => 'Site Test']);
        // Two contracts with the same displayed ANT fields and two invoices
        // must not produce four indistinguishable search rows.
        DB::table('recurring_ipas')->insert([
            ['site_code' => 'TEST001', 'site_name' => 'ANT Test', 'site_owner' => 'TELKOMSEL', 'rtp' => 'RTP1'],
            ['site_code' => 'TEST001', 'site_name' => 'ANT Test', 'site_owner' => 'TELKOMSEL', 'rtp' => 'RTP1'],
        ]);
        DB::table('recurring_tagihan_ipas')->insert([['site_code' => 'TEST001'], ['site_code' => 'TEST001']]);
        SiteMonthlyMetric::create(['site_id' => $site->id, 'tahun' => 2026, 'bulan' => 6, 'revenue' => 100, 'cost' => 40]);
        SiteMonthlyMetric::create(['site_id' => $site->id, 'tahun' => 2026, 'bulan' => 7, 'revenue' => 200, 'cost' => 250]);
        // Legacy FAST data must not replace the monthly P&L.
        DB::table('site_financials')->insert(['site_id' => 'TEST001', 'periode' => '2026-06', 'revenue' => 999, 'cost' => 1]);

        $response = $this->getJson(route('data-potensi.search-all-resource.data', ['periode' => '2026-06']))->assertOk();
        $this->assertSame(1, $response->json('recordsTotal'));
        $this->assertEquals(100, $response->json('data.0.revenue'));
        $this->assertEquals(60, $response->json('data.0.profit_value'));
        $this->assertSame('Profit', $response->json('data.0.profit_status'));
        $this->assertSame('2026-06', $response->json('data.0.periode'));

        $latest = $this->getJson(route('data-potensi.search-all-resource.data'))->assertOk();
        $this->assertEquals(200, $latest->json('data.0.revenue'));
        $this->assertSame('Loss', $latest->json('data.0.profit_status'));
        $this->assertSame('2026-07', $latest->json('data.0.periode'));
        $this->getJson(route('data-potensi.search-all-resource.show', ['siteId' => 'test001', 'periode' => '2026-06']))
            ->assertOk()->assertJsonPath('data.periode', '2026-06');
        $this->get(route('data-potensi.search-all-resource.index'))->assertOk()->assertSee('2026-06')->assertSee('2026-07');
    }

    public function test_missing_and_anomalous_financials_are_not_reported_as_zero(): void
    {
        $this->actingAs(User::factory()->create(['account_status' => 'approved']));
        $site = Site::factory()->create(['site_id' => 'BAD001']);
        DB::table('site_owners')->insert([['site_code' => 'BAD001'], ['site_code' => 'MISSING001']]);
        $metric = SiteMonthlyMetric::create(['site_id' => $site->id, 'tahun' => 2026, 'bulan' => 7, 'revenue' => 2147483647, 'cost' => 10]);
        DB::table('site_monthly_metrics')->where('id', $metric->id)->update(['is_anomaly' => true]);

        $rows = collect($this->getJson(route('data-potensi.search-all-resource.data', ['periode' => '2026-07']))->assertOk()->json('data'))->keyBy('site_id');
        $this->assertNull($rows['BAD001']['revenue']);
        $this->assertNull($rows['BAD001']['profit_value']);
        $this->assertSame('Anomali', $rows['BAD001']['profit_status']);
        $this->assertNull($rows['MISSING001']['cost']);
        $this->assertSame('Tidak tersedia', $rows['MISSING001']['profit_status']);
        $this->getJson(route('data-potensi.search-all-resource.data', ['periode' => '2026-13']))->assertUnprocessable();
    }
}
