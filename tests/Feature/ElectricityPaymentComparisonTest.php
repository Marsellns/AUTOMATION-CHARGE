<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ElectricityPaymentComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ElectricityPaymentComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_counts_sites_once_and_keeps_payment_sources_and_populations_separate(): void
    {
        DB::table('payment_pln_master_monthly')->insert([
            ['site_id' => 'M-1', 'id_pelanggan' => 'A-1', 'tahun' => 2026, 'bulan' => 1, 'is_paid' => true, 'amount' => 1000, 'status_aktif_site' => 'Aktif'],
            ['site_id' => ' m-1 ', 'id_pelanggan' => 'A-2', 'tahun' => 2026, 'bulan' => 1, 'is_paid' => true, 'amount' => 2000, 'status_aktif_site' => 'Aktif'],
            ['site_id' => 'M-2', 'id_pelanggan' => 'A-3', 'tahun' => 2026, 'bulan' => 1, 'is_paid' => false, 'amount' => null, 'status_aktif_site' => 'Aktif'],
            ['site_id' => 'M-OFF', 'id_pelanggan' => 'A-4', 'tahun' => 2026, 'bulan' => 1, 'is_paid' => true, 'amount' => 99, 'status_aktif_site' => 'Tidak Aktif'],
        ]);
        DB::table('payment_pln')->insert(['site_id' => 'M-1', 'id_pelanggan' => 'A-1', 'tahun' => 2026, 'bulan' => 1, 'status' => 'Done', 'harga' => 999999]);
        DB::table('listrik_inbuilding')->insert([
            ['site_id' => 'I-1', 'status' => 'Active'], ['site_id' => 'I-2', 'status' => 'Aktif'], ['site_id' => 'I-OFF', 'status' => 'Inactive'],
        ]);
        DB::table('payment_ibc')->insert([
            ['site_id' => ' i-1 ', 'tahun' => 2026, 'bulan' => 1, 'status' => ' Done ', 'jumlah_tagihan' => 200],
            ['site_id' => 'I-1', 'tahun' => 2026, 'bulan' => 1, 'status' => 'Paid', 'jumlah_tagihan' => 300],
            ['site_id' => 'I-1', 'tahun' => 2026, 'bulan' => 1, 'status' => 'Pending', 'jumlah_tagihan' => 1000],
            ['site_id' => 'UNKNOWN', 'tahun' => 2026, 'bulan' => 1, 'status' => 'Done', 'jumlah_tagihan' => 400],
            ['site_id' => 'I-2', 'tahun' => 2025, 'bulan' => 1, 'status' => 'Done', 'jumlah_tagihan' => 9000],
        ]);
        $result = app(ElectricityPaymentComparisonService::class)->summary(2026);
        $macro = $result['series'][0]['points'][0];
        $ibc = $result['series'][1]['points'][0];
        $this->assertSame(1, $macro['paid_sites']);
        $this->assertSame(2, $macro['population']);
        $this->assertSame(50.0, $macro['percentage']);
        $this->assertSame(3000.0, $macro['amount']);
        $this->assertSame(1, $ibc['paid_sites']);
        $this->assertSame(2, $ibc['population']);
        $this->assertSame(50.0, $ibc['percentage']);
        $this->assertSame(900.0, $ibc['amount']);
        $this->assertSame(1, $ibc['unmatched_sites']);
        $this->assertFalse($result['series'][0]['points'][1]['available']);
        $this->assertNull($result['series'][1]['points'][1]['paid_sites']);
    }

    public function test_known_zero_and_absent_months_are_distinct_and_nop_never_implies_inbuilding_is_filtered(): void
    {
        DB::table('listrik_pln')->insert([
            ['site_id' => 'M-1', 'id_pelanggan' => 'A-1', 'nop' => 'NOP BEKASI'], ['site_id' => 'M-2', 'id_pelanggan' => 'A-2', 'nop' => 'BOGOR'],
        ]);
        DB::table('payment_pln_master_monthly')->insert([
            ['site_id' => 'M-1', 'tahun' => 2026, 'bulan' => 2, 'is_paid' => false, 'amount' => null],
            ['site_id' => 'M-2', 'tahun' => 2026, 'bulan' => 2, 'is_paid' => true, 'amount' => 99],
        ]);
        DB::table('listrik_inbuilding')->insert(['site_id' => 'I-1', 'status' => 'Active']);
        DB::table('payment_ibc')->insert(['site_id' => 'I-1', 'tahun' => 2026, 'bulan' => 2, 'status' => 'Pending', 'jumlah_tagihan' => 500]);
        $service = app(ElectricityPaymentComparisonService::class);
        $result = $service->summary(2026, 2, 'NOP-BEKASI');
        $this->assertCount(1, $result['series']);
        $this->assertSame(0, $result['series'][0]['points'][0]['paid_sites']);
        $this->assertSame(0.0, $result['series'][0]['points'][0]['percentage']);
        $this->assertStringContainsString('belum menyimpan NOP', implode(' ', $result['notes']));
        $ibc = $service->summary(2026, 2, null, 'inbuilding');
        $this->assertSame('inbuilding', $ibc['series'][0]['key']);
        $this->assertTrue($ibc['series'][0]['points'][0]['available']);
        $this->assertSame(0.0, $ibc['series'][0]['points'][0]['amount']);
    }

    public function test_listrik_all_upload_fallback_and_both_dashboards_return_the_same_comparison(): void
    {
        DB::table('listrik_all')->insert([
            ['site_id' => 'M-1', 'id_pelanggan' => 'A-1', 'tahun' => 2026, 'jan' => 100],
            ['site_id' => 'M-1', 'id_pelanggan' => 'A-2', 'tahun' => 2026, 'jan' => 200],
            ['site_id' => 'M-2', 'id_pelanggan' => 'A-3', 'tahun' => 2026, 'jan' => 0],
        ]);
        $result = app(ElectricityPaymentComparisonService::class)->summary(2026);
        $this->assertSame(1, $result['series'][0]['points'][0]['paid_sites']);
        $this->assertSame(2, $result['series'][0]['points'][0]['population']);
        $this->assertSame(300.0, $result['series'][0]['points'][0]['amount']);
        $this->assertFalse($result['series'][0]['points'][1]['available']);

        // A monthly snapshot makes the period discoverable in both modules.
        DB::table('payment_pln_master_monthly')->insert(['site_id' => 'M-1', 'tahun' => 2026, 'bulan' => 1, 'amount' => 300, 'is_paid' => true]);
        $user = User::factory()->create(['account_status' => 'approved']);
        $main = $this->actingAs($user)->getJson(route('dashboard.chart-data', ['tahun' => 2026, 'bulan' => 'all']))->assertOk();
        $module = $this->getJson(route('electricity.dashboard.data', ['tahun' => 2026, 'bulan' => 'all']))->assertOk();
        $this->assertSame($main->json('electricity_comparison'), $module->json('electricity_comparison'));
    }

    public function test_an_inbuilding_only_year_is_available_on_both_dashboards(): void
    {
        DB::table('listrik_inbuilding')->insert(['site_id' => 'IBC', 'status' => 'Active']);
        DB::table('payment_ibc')->insert(['site_id' => 'IBC', 'tahun' => 2027, 'bulan' => 2, 'status' => 'Done', 'jumlah_tagihan' => 100]);
        $user = User::factory()->create(['account_status' => 'approved']);
        foreach (['dashboard.chart-data', 'electricity.dashboard.data'] as $route) {
            $this->actingAs($user)->getJson(route($route, ['tahun' => 2027, 'bulan' => 'all']))->assertOk()
                ->assertJsonPath('filters.tahun', 2027)
                ->assertJsonPath('electricity_comparison.series.1.points.1.paid_sites', 1);
        }
    }
}
