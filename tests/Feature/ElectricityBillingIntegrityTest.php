<?php

namespace Tests\Feature;

use App\Imports\Electricity\CentralizedPaymentImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ElectricityBillingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_master_dashboard_billing_uses_complete_payment_rows_and_exposes_site_counts(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $now = now();
        $listrikId = DB::table('listrik_pln')->insertGetId([
            'id_pelanggan' => 'PEL-001',
            'site_id' => 'SITE-001',
            'site_name' => 'Site One',
            'status_aktif_site' => 'Aktif',
            'daya_va' => 23000,
            'nop' => 'NOP KARAWANG',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('listrik_pln')->insert([
            'id_pelanggan' => 'PEL-002',
            'site_id' => 'SITE-002',
            'site_name' => 'Site Two',
            'status_aktif_site' => 'Aktif',
            'daya_va' => 23000,
            'nop' => 'NOP BOGOR',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('payment_pln')->insert([
            [
                'id_pelanggan' => 'PEL-001', 'site_id' => 'SITE-001', 'site_name' => 'Site One',
                'harga' => 10000000000, 'status' => 'Done', 'bulan' => 7, 'tahun' => 2026,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id_pelanggan' => 'PEL-002', 'site_id' => 'SITE-002', 'site_name' => 'Site Two',
                'harga' => 3700000000, 'status' => 'Done', 'bulan' => 7, 'tahun' => 2026,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
        DB::table('status_pembayaran')->insert([
            'listrik_pln_id' => $listrikId,
            'id_pelanggan' => 'PEL-001',
            'bulan' => 7,
            'tahun' => 2026,
            'harga' => 99000000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($user)
            ->getJson(route('dashboard.chart-data', ['tahun' => 2026, 'bulan' => 7]))
            ->assertOk()
            ->assertJsonPath('pln.values.0', 13.7)
            ->assertJsonPath('pln.amounts.0', 13700000000)
            ->assertJsonPath('pln.site_counts.0', 2)
            ->assertJsonPath('pln.record_counts.0', 2)
            ->assertJsonPath('pln_kpi.total_tagihan', 13700000000);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Monthly Electricity Billing')
            ->assertSee(route('electricity.centralized.payment.index'), false);
    }

    public function test_payment_detail_page_filters_the_same_billing_period_and_nop_as_the_dashboard(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $now = now();

        DB::table('listrik_pln')->insert([
            [
                'id_pelanggan' => 'PEL-001', 'site_id' => 'SITE-001', 'site_name' => 'Site One',
                'status_aktif_site' => 'Aktif', 'nop' => 'NOP KARAWANG',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id_pelanggan' => 'PEL-002', 'site_id' => 'SITE-002', 'site_name' => 'Site Two',
                'status_aktif_site' => 'Aktif', 'nop' => 'NOP BOGOR',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
        DB::table('payment_pln')->insert([
            [
                'id_pelanggan' => 'PEL-001', 'site_id' => 'SITE-001', 'site_name' => 'Site One',
                'harga' => 1000, 'status' => 'Done', 'bulan' => 7, 'tahun' => 2026,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id_pelanggan' => 'PEL-002', 'site_id' => 'SITE-002', 'site_name' => 'Site Two',
                'harga' => 2000, 'status' => 'Done', 'bulan' => 7, 'tahun' => 2026,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $this->actingAs($user)
            ->getJson(route('electricity.centralized.payment.data', [
                'tahun' => 2026,
                'bulan' => 7,
                'nop' => 'KARAWANG',
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.site_id', 'SITE-001');
    }

    public function test_master_dashboard_electricity_uses_selected_site_owner_nop_across_its_data_sources(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $now = now();

        DB::table('site_owners')->insert([
            ['site_code' => 'SITE-001', 'nop' => 'NOP BEKASI'],
            ['site_code' => 'SITE-002', 'nop' => 'NOP BEKASI'],
            ['site_code' => 'SITE-003', 'nop' => 'NOP BOGOR'],
        ]);
        DB::table('listrik_pln')->insert([
            ['id_pelanggan' => 'PEL-001', 'site_id' => 'SITE-001', 'nop' => 'NOP BEKASI', 'status_aktif_site' => 'Aktif', 'daya_va' => 1000, 'created_at' => $now, 'updated_at' => $now],
            ['id_pelanggan' => 'PEL-002', 'site_id' => 'SITE-002', 'nop' => 'BEKASI', 'status_aktif_site' => 'Aktif', 'daya_va' => 2000, 'created_at' => $now, 'updated_at' => $now],
            ['id_pelanggan' => 'PEL-003', 'site_id' => 'SITE-003', 'nop' => 'NOP BOGOR', 'status_aktif_site' => 'Aktif', 'daya_va' => 3000, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('payment_pln')->insert([
            ['id_pelanggan' => 'PEL-001', 'site_id' => 'SITE-001', 'harga' => 1000, 'status' => 'Done', 'bulan' => 7, 'tahun' => 2026, 'created_at' => $now, 'updated_at' => $now],
            ['id_pelanggan' => 'PEL-002', 'site_id' => 'SITE-002', 'harga' => 250, 'status' => 'Pending', 'bulan' => 7, 'tahun' => 2026, 'created_at' => $now, 'updated_at' => $now],
            ['id_pelanggan' => 'PEL-003', 'site_id' => 'SITE-003', 'harga' => 2000, 'status' => 'Done', 'bulan' => 7, 'tahun' => 2026, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('payment_pln_master_monthly')->insert([
            ['id_pelanggan' => 'PEL-001', 'site_id' => 'SITE-001', 'bulan' => 7, 'tahun' => 2026, 'amount' => 1000, 'is_paid' => true, 'status_aktif_site' => 'Aktif', 'created_at' => $now, 'updated_at' => $now],
            ['id_pelanggan' => 'PEL-002', 'site_id' => 'SITE-002', 'bulan' => 7, 'tahun' => 2026, 'amount' => 250, 'is_paid' => false, 'status_aktif_site' => 'Aktif', 'created_at' => $now, 'updated_at' => $now],
            ['id_pelanggan' => 'PEL-003', 'site_id' => 'SITE-003', 'bulan' => 7, 'tahun' => 2026, 'amount' => 2000, 'is_paid' => true, 'status_aktif_site' => 'Aktif', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('listrik_all')->insert([
            ['id_pelanggan' => 'PEL-001', 'site_id' => 'SITE-001', 'tahun' => 2026, 'jul' => 1100, 'created_at' => $now, 'updated_at' => $now],
            ['id_pelanggan' => 'PEL-002', 'site_id' => 'SITE-002', 'tahun' => 2026, 'jul' => 200, 'created_at' => $now, 'updated_at' => $now],
            ['id_pelanggan' => 'PEL-003', 'site_id' => 'SITE-003', 'tahun' => 2026, 'jul' => 2200, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->actingAs($user)
            ->getJson(route('dashboard.chart-data', ['tahun' => 2026, 'bulan' => 7, 'nop' => 'NOP BEKASI']))
            ->assertOk()
            ->assertJsonPath('filters.nop', 'NOP BEKASI')
            ->assertJsonPath('pln_kpi.pelanggan_count', 2)
            ->assertJsonPath('pln_kpi.total_daya_va', 3000)
            ->assertJsonPath('pln_kpi.total_tagihan', 1250)
            ->assertJsonPath('pln.amounts.0', 1250)
            ->assertJsonPath('pln.site_counts.0', 2)
            ->assertJsonPath('electricity_payment.active_sites.0', 2)
            ->assertJsonPath('electricity_payment.paid_counts.0', 1)
            ->assertJsonPath('electricity_all.site_count', 2)
            ->assertJsonPath('electricity_all.costs.6', 1300);

        $this->actingAs($user)
            ->getJson(route('dashboard.electricity-payment-data', ['tahun' => 2026, 'bulan' => 7, 'nop' => 'NOP BEKASI']))
            ->assertOk()
            ->assertJsonPath('active_sites.0', 2)
            ->assertJsonPath('paid_counts.0', 1);

        $this->actingAs($user)
            ->getJson(route('dashboard.chart-data', ['tahun' => 2026, 'bulan' => 'all', 'nop' => 'NOP BEKASI']))
            ->assertOk()
            ->assertJsonPath('pln_kpi.pelanggan_count', 2)
            ->assertJsonPath('pln_kpi.total_tagihan', 1250)
            ->assertJsonPath('pln.amounts.6', 1250)
            ->assertJsonPath('electricity_payment.active_sites.6', 2)
            ->assertJsonPath('electricity_all.site_count', 2);

        $this->actingAs($user)
            ->getJson(route('dashboard.electricity-payment-detail', ['tahun' => 2026, 'bulan' => 7, 'nop' => 'NOP BEKASI']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('sites.0.site_id', 'SITE-002');

        $this->actingAs($user)
            ->getJson(route('dashboard.chart-data', ['tahun' => 2026, 'bulan' => 7]))
            ->assertOk()
            ->assertJsonPath('pln_kpi.pelanggan_count', 3)
            ->assertJsonPath('pln_kpi.total_tagihan', 3250)
            ->assertJsonPath('electricity_all.site_count', 3)
            ->assertJsonPath('electricity_all.costs.6', 3500);
    }

    public function test_uploaded_payment_rows_are_deduplicated_and_numeric_thousands_are_restored(): void
    {
        $import = new CentralizedPaymentImport('Done', 5, 2026);
        $row = collect([1, '538671590801', 'COC063', 'COMBATCIBODASJONGGOL', 6600, 1, 'B2', 'BOGOR', 400.471, 'Desy', '26-05-2026']);

        $import->collection(collect([
            collect(['Tagihan_Mei_2026']),
            collect(['No', 'ID Pelanggan', 'Site ID', 'Site Name', 'Daya', 'Phasa', 'Gol Tarif', 'Unit PLN', 'Harga', 'Update By', 'Tanggal']),
            $row,
            $row,
        ]));

        $this->assertDatabaseCount('payment_pln', 1);
        $this->assertDatabaseHas('payment_pln', [
            'id_pelanggan' => '538671590801',
            'site_id' => 'COC063',
            'harga' => 400471,
            'status' => 'Done',
            'bulan' => 5,
            'tahun' => 2026,
        ]);
    }
}
