<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ElectricityAnomalyNotification;
use App\Notifications\InfrastructureSiteAlertNotification;
use App\Notifications\SiteLossNotification;
use App\Services\BackfillOperationalNotifications;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class OperationalNotificationBackfillTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_approval_backfills_current_operational_alerts_without_resending_email(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow('2026-10-13 08:01:00 Asia/Jakarta');
        $admin = $this->userWithRole('admin');
        $pending = User::factory()->create(['account_status' => 'pending', 'requested_role' => 'viewer']);

        $admin->notify(new InfrastructureSiteAlertNotification(
            'site_tp', 'Site TP', 1, 5,
            ['expired' => 1, 'within_90' => 0, 'within_180' => 0, 'unknown' => 0],
            route('infrastruktur.sewa-lahan.index'), '2026-10-13',
        ));
        $admin->notify(new SiteLossNotification(2, 9, 2026));
        $admin->notify(new ElectricityAnomalyNotification(
            'Centralized PLN', 3, route('electricity.centralized.anomali.index'),
            route('electricity.centralized.anomali.export-excel'),
        ));
        $admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\PresalesDocumentRejectedNotification',
            'data' => ['title' => 'Notifikasi pribadi', 'message' => 'Tidak boleh disalin'],
        ]);

        CarbonImmutable::setTestNow('2026-10-13 08:08:00 Asia/Jakarta');
        $this->actingAs($admin)->post(route('admin.user-approvals.approve', $pending))->assertRedirect();

        $this->assertSame('approved', $pending->fresh()->account_status);
        $this->assertCount(3, $pending->notifications);
        $this->assertSame(3, $pending->unreadNotifications()->count());
        $this->assertEqualsCanonicalizing([
            ElectricityAnomalyNotification::class,
            InfrastructureSiteAlertNotification::class,
            SiteLossNotification::class,
        ], $pending->notifications->pluck('type')->all());
        $this->assertSame(0, app(BackfillOperationalNotifications::class)->forApprovedUser($pending->fresh()));
        Mail::assertNothingSent();
    }

    public function test_backfill_only_uses_the_current_schedule_slot(): void
    {
        CarbonImmutable::setTestNow('2026-10-13 08:01:00 Asia/Jakarta');
        $admin = $this->userWithRole('admin');
        $pending = User::factory()->create(['account_status' => 'pending', 'requested_role' => 'viewer']);
        $admin->notify(new SiteLossNotification(2, 9, 2026));

        CarbonImmutable::setTestNow('2026-10-13 17:01:00 Asia/Jakarta');
        $admin->notify(new SiteLossNotification(3, 9, 2026));
        $this->assertSame(0, app(BackfillOperationalNotifications::class)->forApprovedUser($pending));

        CarbonImmutable::setTestNow('2026-10-13 17:08:00 Asia/Jakarta');
        $this->actingAs($admin)->post(route('admin.user-approvals.approve', $pending))->assertRedirect();

        $this->assertCount(1, $pending->fresh()->notifications);
        $this->assertSame(3, $pending->fresh()->notifications->first()->data['site_count']);
    }
}
