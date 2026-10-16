<?php

namespace Tests\Feature;

use App\Exports\SiteOwnerExport;
use App\Models\SiteOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class DashboardSiteOwnerExportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_exposes_site_owner_excel_downloads(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="site-owner-export"', false)
            ->assertSee('id="site-owner-detail-export"', false)
            ->assertSee(route('data-potensi.site-owner.export-excel'), false);
    }

    public function test_dashboard_groups_normalized_owners_without_counting_duplicate_master_rows(): void
    {
        $this->seedSiteOwners();
        DB::table('site_owners')->insert(['site_code' => ' site-001 ', 'site_owner' => ' Mitratel ', 'nop' => 'NOP JAKARTA']);
        foreach (range(1, 6) as $number) {
            $id = DB::table('sites')->insertGetId(['site_id' => sprintf('SITE-%03d', $number), 'site_name' => 'Site '.$number]);
            DB::table('site_monthly_metrics')->insert([
                'site_id' => $id, 'tahun' => 2026, 'bulan' => 7,
                'revenue' => 100, 'cost' => 40, 'profit_loss' => 60, 'is_anomaly' => false,
            ]);
        }

        $this->actingAs(User::factory()->create(['account_status' => 'approved']));
        $response = $this->getJson(route('dashboard.chart-data', ['tahun' => 2026, 'bulan' => 7]))
            ->assertOk()->assertJsonPath('site_owners.total_sites', 6);
        $this->assertSame([
            'Belum teridentifikasi' => 3, 'Mitratel' => 2, 'Telkomsel' => 1,
        ], collect($response->json('site_owners.items'))->pluck('total', 'name')->all());

        $filtered = $this->getJson(route('dashboard.chart-data', ['tahun' => 2026, 'bulan' => 7, 'nop' => 'NOP JAKARTA']))
            ->assertOk()->assertJsonPath('site_owners.total_sites', 4);
        $this->assertEqualsCanonicalizing([
            'Belum teridentifikasi' => 2, 'Mitratel' => 1, 'Telkomsel' => 1,
        ], collect($filtered->json('site_owners.items'))->pluck('total', 'name')->all());
    }

    public function test_site_owner_export_can_be_filtered_by_owner_and_nop(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $this->seedSiteOwners();
        Carbon::setTestNow('2026-09-25 10:15:30');
        Excel::fake();

        $this->actingAs($user)
            ->get(route('data-potensi.site-owner.export-excel', [
                'site_owner' => 'Mitratel',
                'nop' => 'NOP JAKARTA',
            ]))
            ->assertOk();

        Excel::assertDownloaded('site-owner-20260925-101530.xlsx', function (SiteOwnerExport $export): bool {
            return $export->query()->pluck('site_code')->all() === ['SITE-001'];
        });
    }

    public function test_site_owner_export_downloads_all_rows_without_filters(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $this->seedSiteOwners();
        Carbon::setTestNow('2026-09-25 10:15:30');
        Excel::fake();

        $this->actingAs($user)
            ->get(route('data-potensi.site-owner.export-excel'))
            ->assertOk();

        Excel::assertDownloaded('site-owner-20260925-101530.xlsx', function (SiteOwnerExport $export): bool {
            return $export->query()->count() === 5;
        });
    }

    public function test_site_owner_export_supports_unidentified_owner_rows(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $this->seedSiteOwners();
        Carbon::setTestNow('2026-09-25 10:15:30');
        Excel::fake();

        $this->actingAs($user)
            ->get(route('data-potensi.site-owner.export-excel', [
                'site_owner' => 'Belum teridentifikasi',
                'nop' => 'NOP JAKARTA',
            ]))
            ->assertOk();

        Excel::assertDownloaded('site-owner-20260925-101530.xlsx', function (SiteOwnerExport $export): bool {
            return $export->query()->pluck('site_code')->all() === ['SITE-004', 'SITE-003'];
        });
    }

    private function seedSiteOwners(): void
    {
        SiteOwner::query()->insert([
            ['site_code' => 'SITE-001', 'site_owner' => 'Mitratel', 'nop' => 'NOP JAKARTA'],
            ['site_code' => 'SITE-002', 'site_owner' => 'Mitratel', 'nop' => 'NOP BOGOR'],
            ['site_code' => 'SITE-003', 'site_owner' => null, 'nop' => 'NOP JAKARTA'],
            ['site_code' => 'SITE-004', 'site_owner' => '   ', 'nop' => 'NOP JAKARTA'],
            ['site_code' => 'SITE-005', 'site_owner' => 'Telkomsel', 'nop' => 'NOP JAKARTA'],
        ]);
    }
}
