<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ElectricityPaymentComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ElectricityPaymentDetailTest extends TestCase
{
    use RefreshDatabase;

    private function detail(string $source, string $metric = 'sites', array $extra = [])
    {
        return $this->getJson(route('electricity.payment-detail', $extra + ['source' => $source, 'metric' => $metric, 'tahun' => 2026, 'bulan' => 1]));
    }

    public function test_inbuilding_popup_reconciles_site_and_amount_bars_without_pending_or_duplicate_sites(): void
    {
        $this->actingAs(User::factory()->create(['account_status' => 'approved']));
        DB::table('listrik_inbuilding')->insert([
            ['site_id' => 'I-1', 'status' => 'Active'], ['site_id' => 'I-OFF', 'status' => 'Inactive'],
        ]);
        DB::table('payment_ibc')->insert([
            ['site_id' => 'I-1', 'tahun' => 2026, 'bulan' => 1, 'status' => ' Done ', 'jumlah_tagihan' => 200],
            ['site_id' => ' i-1 ', 'tahun' => 2026, 'bulan' => 1, 'status' => 'Lunas', 'jumlah_tagihan' => 300],
            ['site_id' => 'I-1', 'tahun' => 2026, 'bulan' => 1, 'status' => 'Pending', 'jumlah_tagihan' => 1000],
            ['site_id' => 'I-OFF', 'tahun' => 2026, 'bulan' => 1, 'status' => 'Paid', 'jumlah_tagihan' => 400],
            ['site_id' => 'UNKNOWN', 'tahun' => 2026, 'bulan' => 1, 'status' => 'Terbayar', 'jumlah_tagihan' => 50],
            ['site_id' => 'I-1', 'tahun' => 2025, 'bulan' => 1, 'status' => 'Done', 'jumlah_tagihan' => 9000],
        ]);
        $point = app(ElectricityPaymentComparisonService::class)->summary(2026, 1, null, 'inbuilding')['series'][0]['points'][0];
        $sites = $this->detail('inbuilding')->assertOk()->assertJsonCount(1, 'sites')
            ->assertJsonPath('total', $point['paid_sites'])->assertJsonPath('records_count', 2)
            ->assertJsonPath('sites.0.site_id', 'I-1')->assertJsonPath('sites.0.membership', 'Master aktif');
        $this->assertEquals(500, $sites->json('amount'));
        $amount = $this->detail('inbuilding', 'amount')->assertOk()->assertJsonCount(3, 'sites');
        $this->assertEquals($point['amount'], $amount->json('amount'));
        $this->assertEquals($point['amount'], collect($amount->json('sites'))->sum('amount'));
        $this->assertSame('Di luar master aktif', collect($amount->json('sites'))->firstWhere('site_id', 'I-OFF')['membership']);
        $this->detail('inbuilding', 'billing')->assertOk()->assertJsonPath('records_count', 5);
        $this->detail('inbuilding', 'sites', ['nop' => 'BEKASI'])->assertUnprocessable();
        $this->detail('inbuilding', 'amount', ['bulan' => 2])->assertOk()->assertJsonPath('total', 0)->assertJsonCount(0, 'sites');
    }

    public function test_macro_details_use_flagging_snapshot_and_nop_with_upload_fallback_and_separate_billing(): void
    {
        $this->actingAs(User::factory()->create(['account_status' => 'approved']));
        DB::table('listrik_pln')->insert([
            ['site_id' => 'M-1', 'id_pelanggan' => 'A', 'nop' => 'NOP BEKASI'],
            ['site_id' => 'M-2', 'id_pelanggan' => 'B', 'nop' => 'BOGOR'],
        ]);
        DB::table('payment_pln_master_monthly')->insert([
            ['site_id' => 'M-1', 'id_pelanggan' => 'A', 'tahun' => 2026, 'bulan' => 1, 'is_paid' => true, 'amount' => 100, 'status_aktif_site' => 'Aktif'],
            ['site_id' => ' m-1 ', 'id_pelanggan' => 'A2', 'tahun' => 2026, 'bulan' => 1, 'is_paid' => true, 'amount' => 200, 'status_aktif_site' => 'Aktif'],
            ['site_id' => 'M-2', 'id_pelanggan' => 'B', 'tahun' => 2026, 'bulan' => 1, 'is_paid' => true, 'amount' => 500, 'status_aktif_site' => 'Aktif'],
            ['site_id' => 'OFF', 'id_pelanggan' => 'C', 'tahun' => 2026, 'bulan' => 1, 'is_paid' => true, 'amount' => 999, 'status_aktif_site' => 'Tidak Aktif'],
        ]);
        DB::table('listrik_all')->insert(['site_id' => 'M-1', 'id_pelanggan' => 'A', 'tahun' => 2026, 'jan' => 888, 'feb' => 77]);
        DB::table('payment_pln')->insert(['site_id' => 'M-1', 'id_pelanggan' => 'A', 'tahun' => 2026, 'bulan' => 1, 'harga' => 9999, 'status' => 'Pending']);
        $this->detail('macro', 'sites', ['nop' => 'NOP-BEKASI'])->assertOk()->assertJsonPath('total', 1)->assertJsonPath('records_count', 2);
        $this->assertEquals(300, $this->detail('macro', 'amount', ['nop' => 'bekasi'])->json('amount'));
        $point = app(ElectricityPaymentComparisonService::class)->summary(2026, 1)['series'][0]['points'][0];
        $this->assertEquals($point['amount'], $this->detail('macro', 'amount')->json('amount'));
        $this->assertEquals(77, $this->detail('macro', 'amount', ['bulan' => 2])->assertOk()->json('amount'));
        $this->assertEquals(9999, $this->detail('macro', 'billing')->assertOk()->json('amount'));
    }

    public function test_detail_pagination_preserves_total_amount_and_validates_inputs_and_authentication(): void
    {
        $this->detail('inbuilding')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['account_status' => 'approved']));
        DB::table('payment_ibc')->insert(array_map(fn ($i) => ['site_id' => sprintf('I-%03d', $i), 'tahun' => 2026, 'bulan' => 1, 'status' => 'Done', 'jumlah_tagihan' => 10], range(1, 105)));
        $this->detail('inbuilding', 'amount')->assertOk()->assertJsonCount(100, 'sites')->assertJsonPath('total', 105)->assertJsonPath('last_page', 2);
        $last = $this->detail('inbuilding', 'amount', ['page' => 2])->assertOk()->assertJsonCount(5, 'sites')->assertJsonPath('sites.0.site_id', 'I-101');
        $this->assertEquals(1050, $last->json('amount'));
        foreach ([['bulan' => 13], ['tahun' => 1999], ['source' => 'bad'], ['metric' => 'jan; DROP'], ['page' => 0]] as $input) {
            $this->detail('macro', 'sites', $input)->assertUnprocessable();
        }
    }
}
