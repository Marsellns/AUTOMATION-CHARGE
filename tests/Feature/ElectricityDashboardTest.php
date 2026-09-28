<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ElectricityDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_its_data_endpoint_require_authentication(): void
    {
        $this->get(route('electricity.dashboard'))->assertRedirect(route('login'));
        $this->getJson(route('electricity.dashboard.data'))->assertUnauthorized();
    }

    public function test_dashboard_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);

        $this->actingAs($user)
            ->get(route('electricity.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Electricity')
            ->assertSee('Tren Biaya Listrik Bulanan')
            ->assertSee('electricity\/dashboard-data', false);
    }

    public function test_dashboard_aggregates_centralized_and_inbuilding_data_with_filters(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $now = now();

        DB::table('listrik_pln')->insert([
            [
                'id_pelanggan' => 'PEL-001', 'site_id' => 'C-001', 'site_name' => 'Central One',
                'status_aktif_site' => 'Aktif', 'daya_va' => 10000, 'nop' => 'BEKASI',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id_pelanggan' => 'PEL-002', 'site_id' => 'C-002', 'site_name' => 'Central Two',
                'status_aktif_site' => 'Tidak Aktif', 'daya_va' => 20000, 'nop' => 'BOGOR',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
        DB::table('listrik_inbuilding')->insert([
            'site_id' => 'I-001', 'site_name' => 'IBC One', 'status' => 'Active', 'daya' => 5000,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('payment_pln')->insert([
            [
                'id_pelanggan' => 'PEL-001', 'site_id' => 'C-001', 'site_name' => 'Central One',
                'harga' => 1000, 'status' => 'Done', 'bulan' => 1, 'tahun' => 2026,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id_pelanggan' => 'PEL-001', 'site_id' => 'C-001', 'site_name' => 'Central One',
                'harga' => 500, 'status' => 'Pending', 'bulan' => 1, 'tahun' => 2026,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id_pelanggan' => 'PEL-002', 'site_id' => 'C-002', 'site_name' => 'Central Two',
                'harga' => 2000, 'status' => 'Done', 'bulan' => 1, 'tahun' => 2026,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
        DB::table('payment_ibc')->insert([
            [
                'site_id' => 'I-001', 'site_name' => 'IBC One', 'status' => 'Done',
                'jumlah_tagihan' => 800, 'bulan' => 1, 'tahun' => 2026,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'site_id' => 'I-001', 'site_name' => 'IBC One', 'status' => 'Pending',
                'jumlah_tagihan' => 200, 'bulan' => 1, 'tahun' => 2026,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
        DB::table('anomali_tagihan_pln')->insert([
            'id_pelanggan' => 'PEL-001', 'site_id' => 'C-001', 'site_name' => 'Central One',
            'bulan' => 1, 'tahun' => 2026, 'tagihan_sebelumnya' => 700,
            'tagihan_saat_ini' => 1000, 'selisih' => 300, 'kenaikan_persen' => 42.86,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('anomali_tagihan_inbuilding')->insert([
            'site_id' => 'I-001', 'periode_sebelumnya' => '2025-12', 'tagihan_sebelumnya' => 700,
            'periode_saat_ini' => '2026-01', 'tagihan_saat_ini' => 800,
            'selisih' => 100, 'kenaikan_persen' => 14.29,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('bongkar_rampung_mandiri')->insert([
            'id_pelanggan' => 'PEL-002', 'site_id' => 'C-002', 'site_name' => 'Central Two',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->actingAs($user)
            ->getJson(route('electricity.dashboard.data', [
                'tahun' => 2026,
                'bulan' => 1,
                'scope' => 'all',
            ]))
            ->assertOk()
            ->assertJsonPath('filters.tahun', 2026)
            ->assertJsonPath('filters.bulan', 1)
            ->assertJsonPath('filters.scope', 'all')
            ->assertJsonPath('kpi.total_sites', 3)
            ->assertJsonPath('kpi.active_sites', 2)
            ->assertJsonPath('kpi.inactive_sites', 1)
            ->assertJsonPath('kpi.total_capacity_va', 35000)
            ->assertJsonPath('kpi.total_billed', 4500)
            ->assertJsonPath('kpi.paid_amount', 3800)
            ->assertJsonPath('kpi.pending_amount', 700)
            ->assertJsonPath('kpi.payment_rate', 84.4)
            ->assertJsonPath('kpi.payment_records', 5)
            ->assertJsonPath('kpi.pending_records', 2)
            ->assertJsonPath('kpi.anomalies', 2)
            ->assertJsonPath('kpi.anomaly_impact', 400)
            ->assertJsonPath('kpi.boram', 1)
            ->assertJsonCount(2, 'site_distribution')
            ->assertJsonCount(2, 'latest_anomalies');

        $this->actingAs($user)
            ->getJson(route('electricity.dashboard.data', [
                'tahun' => 2026,
                'bulan' => 1,
                'scope' => 'all',
                'nop' => 'bekasi',
            ]))
            ->assertOk()
            ->assertJsonPath('filters.scope', 'centralized')
            ->assertJsonPath('filters.nop', 'BEKASI')
            ->assertJsonPath('kpi.total_sites', 1)
            ->assertJsonPath('kpi.total_billed', 1500)
            ->assertJsonPath('kpi.anomalies', 1)
            ->assertJsonCount(1, 'site_distribution');
    }
}
