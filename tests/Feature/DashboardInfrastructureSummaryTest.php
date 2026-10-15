<?php

namespace Tests\Feature;

use App\Http\Controllers\InfrastructureDashboardController;
use App\Models\CombatSite;
use App\Models\RecurringIpas;
use App\Models\SewaLahanRenewal;
use App\Models\UploadFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class DashboardInfrastructureSummaryTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    public function test_dashboard_counts_unique_sites_from_sewa_lahan_and_combat_only(): void
    {
        SewaLahanRenewal::query()->create(['site_code' => 'SHARED-001', 'tahun_renewal' => 2025]);
        SewaLahanRenewal::query()->create(['site_code' => 'SHARED-001', 'tahun_renewal' => 2026]);
        SewaLahanRenewal::query()->create(['site_code' => 'SEWA-ONLY']);
        CombatSite::query()->create(['site_code' => ' shared-001 ']);
        CombatSite::query()->create(['site_code' => 'COMBAT-ONLY']);
        SewaLahanRenewal::query()->create(['site_code' => 'DELETED-SEWA'])->delete();
        CombatSite::query()->create(['site_code' => 'DELETED-COMBAT'])->delete();
        RecurringIpas::factory()->count(2)->create();
        UploadFile::factory()->count(2)->create();

        $response = $this->actingAs($this->userWithRole('viewer'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Site Sewa Lahan &amp; Combat', false)
            ->assertViewHas('infraTotal', 3);

        $this->assertSame(3, $response->viewData('moduleDataComposition')['infrastructure']['count']);
        $overview = app(InfrastructureDashboardController::class)
            ->data(Request::create('/infrastruktur/dashboard-data'))
            ->getData(true);
        $this->assertSame($overview['cards']['unique_sites'], $response->viewData('infraTotal'));
    }

    public function test_dashboard_shows_zero_infrastructure_sites_when_only_other_data_exists(): void
    {
        RecurringIpas::factory()->create();
        UploadFile::factory()->create();

        $response = $this->actingAs($this->userWithRole('viewer'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('infraTotal', 0);

        $this->assertSame(['count' => 0, 'percentage' => 0], $response->viewData('moduleDataComposition')['infrastructure']);
    }
}
