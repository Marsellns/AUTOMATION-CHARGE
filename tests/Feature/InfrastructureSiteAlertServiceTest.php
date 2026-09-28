<?php

namespace Tests\Feature;

use App\Models\CombatSite;
use App\Models\SewaLahanRenewal;
use App\Models\User;
use App\Services\InfrastructureSiteAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
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

    public function test_it_creates_split_bell_notifications_and_only_one_daily_email(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 08:00:00 Asia/Jakarta');
        config()->set('mail.infrastructure_alert_to', 'infrastructure@example.test');
        Mail::fake();

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
        CombatSite::query()->create([
            'site_code' => 'COMBAT-001',
            'site_name' => 'Combat tanpa tanggal akhir',
            'source_details' => [],
        ]);

        $first = app(InfrastructureSiteAlertService::class)->send();
        $second = app(InfrastructureSiteAlertService::class)->send();

        $this->assertEqualsCanonicalizing(
            ['site_telkomsel', 'site_tp', 'combat'],
            $first['website_categories']
        );
        $this->assertTrue($first['email_sent']);
        $this->assertSame([], $second['website_categories']);
        $this->assertFalse($second['email_sent']);
        $this->assertSame(3, $user->fresh()->notifications()->count());
        $this->assertEqualsCanonicalizing(
            ['site_telkomsel', 'site_tp', 'combat'],
            $user->fresh()->notifications->pluck('data.category')->all()
        );
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
