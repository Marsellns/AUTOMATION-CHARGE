<?php

namespace Tests\Unit;

use App\Support\DailyNotificationGate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DailyNotificationGateTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Cache::flush();

        parent::tearDown();
    }

    public function test_it_allows_each_topic_and_channel_only_once_per_business_day(): void
    {
        config()->set('notifications.timezone', 'Asia/Jakarta');
        CarbonImmutable::setTestNow('2026-09-28 08:00:00 Asia/Jakarta');

        $firstWebsiteClaim = DailyNotificationGate::reserve('website', 'site-tp');

        $this->assertNotNull($firstWebsiteClaim);
        $this->assertNull(DailyNotificationGate::reserve('website', 'site-tp'));
        $this->assertNotNull(DailyNotificationGate::reserve('email', 'site-tp'));
        $this->assertNotNull(DailyNotificationGate::reserve('website', 'combat'));

        CarbonImmutable::setTestNow('2026-09-29 08:00:00 Asia/Jakarta');

        $this->assertNotNull(DailyNotificationGate::reserve('website', 'site-tp'));
    }

    public function test_a_failed_delivery_can_release_its_daily_slot(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 08:00:00 Asia/Jakarta');
        $claim = DailyNotificationGate::reserve('email', 'infrastructure-sites');

        $this->assertNotNull($claim);
        DailyNotificationGate::release($claim);

        $this->assertNotNull(DailyNotificationGate::reserve('email', 'infrastructure-sites'));
    }
}
