<?php

namespace Tests\Unit;

use App\Models\User;
use App\Notifications\InfrastructureSiteAlertNotification;
use Tests\TestCase;

class InfrastructureSiteAlertNotificationTest extends TestCase
{
    public function test_it_exposes_the_infrastructure_category_and_lease_warning_breakdown(): void
    {
        $data = (new InfrastructureSiteAlertNotification(
            'site_tp',
            'Site TP',
            10,
            25,
            [
                'expired' => 2,
                'within_90' => 3,
                'within_180' => 4,
                'unknown' => 1,
            ],
            'https://simaster.test/infrastruktur/sewa-lahan?ownership_scope=TP',
            '2026-09-28',
        ))->toArray(new User);

        $this->assertSame('Peringatan Site TP', $data['title']);
        $this->assertSame('site_tp', $data['category']);
        $this->assertSame(10, $data['site_count']);
        $this->assertSame(25, $data['total_site_count']);
        $this->assertSame(2, $data['status_counts']['expired']);
        $this->assertStringContainsString('91–180 hari', $data['message']);
        $this->assertStringContainsString('ownership_scope=TP', $data['url']);
        $this->assertSame('2026-09-28', $data['notification_date']);
    }
}
