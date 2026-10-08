<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NotificationLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_domain_links_use_the_current_application_and_keep_filters(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $path = route('infrastruktur.sewa-lahan.index', [], false);
        $notification = $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'infrastructure-alert',
            'data' => ['url' => 'https://old-installation.ngrok-free.app'.$path.'?ownership_scope=TP#infrastructure-data'],
        ]);

        $this->actingAs($user)->get(route('notifications.read', $notification))
            ->assertRedirect(route('infrastruktur.sewa-lahan.index', ['ownership_scope' => 'TP']).'#infrastructure-data');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_download_links_are_rebased_and_unknown_targets_are_rejected(): void
    {
        $path = route('electricity.centralized.anomali.export-excel', [], false);
        $data = ['download_url' => 'https://old-installation.ngrok-free.app'.$path];
        $this->assertSame(route('electricity.centralized.anomali.export-excel'), NotificationLinks::download($data));
        $user = User::factory()->create(['account_status' => 'approved']);
        $notification = $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'electricity-alert', 'data' => $data,
        ]);
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()
            ->assertSee(route('notifications.read', $notification))
            ->assertDontSee('Lihat data')
            ->assertDontSee('Download Excel')
            ->assertDontSee('old-installation.ngrok-free.app');

        foreach (['https://external.test/phishing', 'javascript:alert(1)', '/logout', '//external.test', '/notifications/read-all', "https://external.test/\r\nLocation:bad"] as $url) {
            $this->assertSame(route('notifications.index'), NotificationLinks::read(['url' => $url]));
            $this->assertNull(NotificationLinks::download(['download_url' => $url]));
        }
    }

    public function test_presales_route_parameters_cannot_be_replaced_by_query_parameters(): void
    {
        $path = route('presales.show', 42, false);
        $this->assertSame(route('presales.show', 42), NotificationLinks::read(['url' => 'https://old.test'.$path.'?document=99']));
    }

    public function test_site_loss_period_is_kept_and_other_users_cannot_read_the_notification(): void
    {
        $data = ['title' => 'Peringatan site Loss', 'month' => 8, 'year' => 2026, 'url' => 'https://old.test/legacy'];
        $this->assertSame(route('notifications.site-loss', ['bulan' => 8, 'tahun' => 2026]), NotificationLinks::read($data));
        $this->assertSame(route('notifications.site-loss.export', ['bulan' => 8, 'tahun' => 2026]), NotificationLinks::download($data));
        $owner = User::factory()->create(['account_status' => 'approved']);
        $other = User::factory()->create(['account_status' => 'approved']);
        $notification = $owner->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'site-loss', 'data' => $data,
        ]);
        $this->actingAs($other)->get(route('notifications.read', $notification))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_alert_links_open_only_the_relevant_issue_tables(): void
    {
        foreach ([
            'sewa_lahan' => ['infrastruktur.sewa-lahan.index', null],
            'site_tp' => ['infrastruktur.sewa-lahan.index', 'TP'],
            'site_telkomsel' => ['infrastruktur.sewa-lahan.index', 'Telkomsel'],
            'combat' => ['infrastruktur.combat.index', null],
        ] as $category => [$routeName, $ownership]) {
            $parameters = ['filter_field' => 'lease_alert', 'filter_value' => 'active', 'unique_sites' => 1];
            if ($ownership !== null) {
                $parameters['ownership_scope'] = $ownership;
            }

            $this->assertSame(
                route($routeName, $parameters).'#infrastructure-data',
                NotificationLinks::read(['category' => $category, 'url' => route('infrastruktur.index')]),
            );
        }

        $this->assertSame(
            route('infrastruktur.sewa-lahan.index', [
                'filter_field' => 'lease_alert', 'filter_value' => 'active',
                'unique_sites' => 1, 'ownership_scope' => 'TP',
            ]).'#infrastructure-data',
            NotificationLinks::read(['title' => 'Peringatan Site TP']),
        );

        $this->assertSame(
            route('electricity.centralized.anomali.index'),
            NotificationLinks::read(['title' => 'Peringatan anomali tagihan listrik', 'source' => 'Centralized PLN']),
        );
        $this->assertSame(
            route('electricity.inbuilding.anomali.index'),
            NotificationLinks::read(['title' => 'Peringatan anomali tagihan listrik', 'source' => 'Inbuilding']),
        );
    }

    public function test_inbuilding_notification_shows_issues_across_all_years_by_default(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        foreach ([
            ['site_id' => 'ISSUE-2025', 'periode_saat_ini' => '2025-12', 'kenaikan_persen' => 75],
            ['site_id' => 'ISSUE-2026', 'periode_saat_ini' => '2026-01', 'kenaikan_persen' => 80],
            ['site_id' => 'NORMAL-2026', 'periode_saat_ini' => '2026-02', 'kenaikan_persen' => 20],
        ] as $row) {
            DB::table('anomali_tagihan_inbuilding')->insert(array_merge($row, [
                'tagihan_sebelumnya' => 100,
                'tagihan_saat_ini' => 180,
                'selisih' => 80,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->actingAs($user)->get(route('electricity.inbuilding.anomali.index'))
            ->assertOk()
            ->assertSee('Semua Tahun')
            ->assertSee('value="2025"', false)
            ->assertSee('value="2026"', false);

        $this->getJson(route('electricity.inbuilding.anomali.data'))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonCount(2, 'data')
            ->assertDontSee('NORMAL-2026');
    }
}
