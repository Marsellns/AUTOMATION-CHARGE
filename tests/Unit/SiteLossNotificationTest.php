<?php

namespace Tests\Unit;

use App\Models\User;
use App\Notifications\SiteLossNotification;
use Tests\TestCase;

class SiteLossNotificationTest extends TestCase
{
    public function test_it_links_site_loss_notifications_to_their_original_period(): void
    {
        $data = (new SiteLossNotification(7, 6, 2026))->toArray(new User);

        $this->assertSame(7, $data['site_count']);
        $this->assertSame(6, $data['month']);
        $this->assertSame(2026, $data['year']);
        $this->assertStringContainsString('bulan=6', $data['url']);
        $this->assertStringContainsString('tahun=2026', $data['url']);
        $this->assertStringContainsString('bulan=6', $data['download_url']);
        $this->assertStringContainsString('tahun=2026', $data['download_url']);
    }
}
