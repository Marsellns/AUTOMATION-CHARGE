<?php

namespace Tests\Feature;

use App\Http\Controllers\InfrastructureDashboardController;
use App\Models\CombatSite;
use App\Models\SewaLahanRenewal;
use App\Support\InfrastructureCanonicalSites;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InfrastructureDashboardConsistencyTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config()->set('database.connections.infrastructure_dashboard_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('infrastructure_dashboard_testing');
        DB::setDefaultConnection('infrastructure_dashboard_testing');

        Schema::create('site_owners', function (Blueprint $table): void {
            $table->id();
            $table->string('site_code');
            $table->string('site_owner')->nullable();
            $table->timestamps();
        });
        foreach (['sewa_lahan_renewals', 'combat_sites'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                $table->json('source_details')->nullable();
                $table->string('site_code');
                $table->string('site_name')->nullable();
                $table->integer($name === 'combat_sites' ? 'tahun_justi_dirnet' : 'tahun_renewal')->nullable();
                $table->string('status_dokumen')->nullable();
                $table->string('status_perpanjangan')->nullable();
                $table->string('no_pks_baru')->nullable();
                $table->string('no_pks_lama')->nullable();
                $table->date('end_date_baru')->nullable();
                $table->date('end_date_lama')->nullable();
                $table->decimal('total_harga_baru', 18, 2)->nullable();
                $table->decimal('harga_baru', 18, 2)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    protected function tearDown(): void
    {
        DB::purge('infrastructure_dashboard_testing');
        DB::setDefaultConnection($this->originalConnection);

        parent::tearDown();
    }

    public function test_overview_renewal_chart_uses_only_sewa_years_and_risk_drilldown_matches_the_card(): void
    {
        SewaLahanRenewal::query()->create([
            'site_code' => 'SEWA-CONTRACT',
            'tahun_renewal' => 2025,
            'status_dokumen' => 'A. Negosiasi',
            'no_pks_baru' => 'PKS-001',
            'total_harga_baru' => 100,
        ]);
        SewaLahanRenewal::query()->create([
            'site_code' => 'SEWA-NO-PKS',
            'tahun_renewal' => 2025,
            'status_dokumen' => 'K. Pembayaran Done',
            'total_harga_baru' => 200,
        ]);
        CombatSite::query()->create([
            'site_code' => 'COMBAT-SAFE',
            'tahun_justi_dirnet' => 2030,
            'status_dokumen' => 'J. Reimburse RPJ Done',
            'no_pks_baru' => 'PKS-002',
            'total_harga_baru' => 300,
        ]);

        $controller = app(InfrastructureDashboardController::class);
        $overview = $controller->data(Request::create('/infrastruktur/dashboard-data'))->getData(true);

        $this->assertSame(3, $overview['cards']['total']);
        $this->assertSame(300, (int) $overview['cards']['risk_value']);
        $this->assertSame([['name' => '2025', 'y' => 2, 'filter_field' => 'tahun', 'filter_value' => '2025']], $overview['renewal_years']);

        $combat = $controller->data(Request::create('/infrastruktur/dashboard-data', 'GET', ['scope' => 'combat']))->getData(true);
        $this->assertSame('2030', $combat['renewal_years'][0]['name']);

        $risk = $controller->details(Request::create('/infrastruktur/dashboard-details', 'GET', [
            'filter_field' => 'summary_status',
            'filter_value' => 'risk',
        ]))->getData(true);

        $this->assertSame(2, $risk['count']);
        $this->assertEqualsCanonicalizing(['SEWA-CONTRACT', 'SEWA-NO-PKS'], array_column($risk['data'], 'site_code'));
    }

    public function test_duplicate_site_rows_are_selected_before_grouping_by_year(): void
    {
        SewaLahanRenewal::query()->create([
            'site_code' => 'CROSS-YEAR',
            'tahun_renewal' => 2025,
            'status_dokumen' => 'A. Negosiasi',
        ]);
        SewaLahanRenewal::query()->create([
            'site_code' => 'CROSS-YEAR',
            'tahun_renewal' => 2027,
        ]);

        $selected = InfrastructureCanonicalSites::fromRows(SewaLahanRenewal::query()->get());
        $this->assertSame(2025, $selected->first()->tahun_renewal);

        $overview = app(InfrastructureDashboardController::class)
            ->data(Request::create('/infrastruktur/dashboard-data'))
            ->getData(true);
        $this->assertSame(2, $overview['cards']['total']);
        $this->assertSame(1, $overview['cards']['unique_sites']);
        $this->assertSame([['name' => '2025', 'y' => 1, 'filter_field' => 'tahun', 'filter_value' => '2025']], $overview['renewal_years']);
    }

    public function test_overview_alerts_count_an_overlapping_site_once(): void
    {
        $endDate = today()->subDay()->toDateString();
        SewaLahanRenewal::query()->create([
            'site_code' => 'OVERLAP-001',
            'status_dokumen' => 'A. Negosiasi',
            'end_date_baru' => $endDate,
        ]);
        CombatSite::query()->create([
            'site_code' => 'OVERLAP-001',
            'end_date_baru' => $endDate,
        ]);

        $overview = app(InfrastructureDashboardController::class)
            ->data(Request::create('/infrastruktur/dashboard-data'))
            ->getData(true);

        $this->assertSame(2, $overview['cards']['total']);
        $this->assertSame(1, $overview['cards']['unique_sites']);
        $this->assertCount(1, $overview['alerts']['expired']);
        $this->assertSame('Sewa Lahan', $overview['alerts']['expired'][0]['source']);
    }
}
