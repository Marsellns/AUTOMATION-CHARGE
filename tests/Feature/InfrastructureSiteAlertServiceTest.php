<?php

namespace Tests\Feature;

use App\Http\Controllers\CombatSiteController;
use App\Http\Controllers\SewaLahanRenewalController;
use App\Models\CombatSite;
use App\Models\SewaLahanRenewal;
use App\Models\User;
use App\Services\InfrastructureSiteAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class InfrastructureSiteAlertServiceTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        config()->set('database.connections.notification_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('notification_testing');
        DB::setDefaultConnection('notification_testing');

        $this->createTestSchema();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();
        DB::purge('notification_testing');
        DB::setDefaultConnection($this->originalConnection);

        parent::tearDown();
    }

    public function test_it_sends_each_infrastructure_category_as_its_own_bell_notification_and_excel_email(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 08:00:00 Asia/Jakarta');
        config()->set('mail.infrastructure_alert_to', 'infrastructure@example.test');
        config()->set('mail.default', 'array');
        config()->set('mail.from.address', 'simaster@example.test');
        app('mail.manager')->purge();

        $user = User::query()->create([
            'name' => 'Infrastructure User',
            'email' => 'infrastructure-user@example.test',
            'password' => 'test-password',
            'account_status' => 'approved',
        ]);

        SewaLahanRenewal::query()->create([
            'site_code' => 'TSEL-001',
            'site_name' => 'Site Telkomsel',
            'end_date_baru' => '2026-09-01',
            'source_details' => ['ownership' => 'Telkomsel'],
        ]);
        SewaLahanRenewal::query()->create([
            'site_code' => 'TP-001',
            'site_name' => 'Site TP',
            'end_date_baru' => '2026-12-01',
            'source_details' => ['ownership' => 'TP'],
        ]);
        SewaLahanRenewal::query()->create([
            'site_code' => 'TSEL-SAFE',
            'site_name' => 'Site Telkomsel aman',
            'end_date_baru' => '2027-12-01',
            'source_details' => ['ownership' => 'Telkomsel'],
        ]);
        CombatSite::query()->create([
            'site_code' => 'COMBAT-001',
            'site_name' => 'Combat tanpa tanggal akhir',
            'source_details' => [],
        ]);

        $service = app(InfrastructureSiteAlertService::class);
        $summaries = $this->infrastructureSummaries($service);

        $this->assertEqualsCanonicalizing(
            ['TSEL-001', 'TP-001'],
            $summaries['sewa_lahan']['alert_rows']->pluck('site_code')->all()
        );
        $this->assertSame(['TP-001'], $summaries['site_tp']['alert_rows']->pluck('site_code')->all());
        $this->assertSame(['TSEL-001'], $summaries['site_telkomsel']['alert_rows']->pluck('site_code')->all());
        $this->assertSame(['COMBAT-001'], $summaries['combat']['alert_rows']->pluck('site_code')->all());

        $first = $service->send();
        $second = $service->send();

        $this->assertEqualsCanonicalizing(
            ['sewa_lahan', 'site_tp', 'site_telkomsel', 'combat'],
            $first['website_categories']
        );
        $this->assertEqualsCanonicalizing(
            ['sewa_lahan', 'site_tp', 'site_telkomsel', 'combat'],
            $first['email_categories']
        );
        $this->assertSame(2, $first['warning_counts']['sewa_lahan']);
        $this->assertSame(1, $first['warning_counts']['site_tp']);
        $this->assertSame(1, $first['warning_counts']['site_telkomsel']);
        $this->assertSame(1, $first['warning_counts']['combat']);
        $this->assertSame([], $second['website_categories']);
        $this->assertSame([], $second['email_categories']);
        $this->assertSame(4, $user->fresh()->notifications()->count());
        $this->assertEqualsCanonicalizing(
            ['sewa_lahan', 'site_tp', 'site_telkomsel', 'combat'],
            $user->fresh()->notifications->pluck('data.category')->all()
        );

        $notificationData = $user->fresh()->notifications->pluck('data')->keyBy('category');
        foreach ($notificationData as $data) {
            $this->assertStringContainsString('filter_field=lease_alert', $data['url']);
            $this->assertStringContainsString('#infrastructure-data', $data['url']);
        }
        $this->assertStringContainsString('ownership_scope=TP', $notificationData['site_tp']['url']);
        $this->assertStringContainsString('ownership_scope=Telkomsel', $notificationData['site_telkomsel']['url']);

        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $emails = $transport->messages()
            ->map(fn ($message) => $message->getOriginalMessage());
        $this->assertCount(4, $emails);
        $this->assertEqualsCanonicalizing([
            'Peringatan harian Sewa Lahan - 2026-09-28',
            'Peringatan harian Site TP - 2026-09-28',
            'Peringatan harian Site Telkomsel - 2026-09-28',
            'Peringatan harian Combat - 2026-09-28',
        ], $emails->map(fn (Email $email): ?string => $email->getSubject())->all());
        foreach ($emails as $email) {
            $this->assertInstanceOf(Email::class, $email);
            $this->assertCount(1, $email->getAttachments());
            $this->assertStringEndsWith('.xlsx', $email->getAttachments()[0]->getFilename());
        }
    }

    public function test_notification_links_filter_tables_to_lease_alert_rows_only(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 08:00:00 Asia/Jakarta');

        SewaLahanRenewal::query()->create([
            'site_code' => 'SEWA-ALERT',
            'end_date_baru' => '2026-10-01',
        ]);
        SewaLahanRenewal::query()->create([
            'site_code' => 'SEWA-SAFE',
            'end_date_baru' => '2027-10-01',
        ]);
        CombatSite::query()->create([
            'site_code' => 'COMBAT-ALERT',
            'end_date_baru' => null,
        ]);
        CombatSite::query()->create([
            'site_code' => 'COMBAT-SAFE',
            'end_date_baru' => '2027-10-01',
        ]);

        $request = Request::create('/', 'GET', [
            'filter_field' => 'lease_alert',
            'filter_value' => 'active',
        ]);

        $sewaQuery = SewaLahanRenewal::query();
        $this->applyDashboardFilter(app(SewaLahanRenewalController::class), $sewaQuery, $request);
        $this->assertSame(['SEWA-ALERT'], $sewaQuery->pluck('site_code')->all());

        $combatQuery = CombatSite::query();
        $this->applyDashboardFilter(app(CombatSiteController::class), $combatQuery, $request);
        $this->assertSame(['COMBAT-ALERT'], $combatQuery->pluck('site_code')->all());
    }

    private function applyDashboardFilter(object $controller, object $query, Request $request): void
    {
        $method = new \ReflectionMethod($controller, 'applyDashboardFilter');
        $method->invoke($controller, $query, $request);
    }

    private function infrastructureSummaries(InfrastructureSiteAlertService $service): array
    {
        $method = new \ReflectionMethod($service, 'summaries');

        return $method->invoke($service);
    }

    private function createTestSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('account_status')->default('approved');
            $table->timestamps();
        });
        Schema::create('site_owners', function (Blueprint $table): void {
            $table->id();
            $table->string('site_code');
            $table->string('site_owner')->nullable();
            $table->timestamps();
        });
        Schema::create('sewa_lahan_renewals', function (Blueprint $table): void {
            $table->id();
            $table->json('source_details')->nullable();
            $table->string('site_code');
            $table->string('site_name')->nullable();
            $table->string('status_dokumen')->nullable();
            $table->string('status_perpanjangan')->nullable();
            $table->string('no_pks_baru')->nullable();
            $table->string('no_pks_lama')->nullable();
            $table->date('end_date_baru')->nullable();
            $table->date('end_date_lama')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('combat_sites', function (Blueprint $table): void {
            $table->id();
            $table->json('source_details')->nullable();
            $table->string('site_code');
            $table->string('site_name')->nullable();
            $table->string('status_dokumen')->nullable();
            $table->string('status_perpanjangan')->nullable();
            $table->string('no_pks_baru')->nullable();
            $table->string('no_pks_lama')->nullable();
            $table->date('end_date_baru')->nullable();
            $table->date('end_date_lama')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }
}
