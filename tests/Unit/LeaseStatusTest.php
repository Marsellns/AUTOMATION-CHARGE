<?php

namespace Tests\Unit;

use App\Support\LeaseStatus;
use Carbon\Carbon;
use Tests\TestCase;

class LeaseStatusTest extends TestCase
{
    public function test_it_classifies_lease_end_dates_consistently(): void
    {
        $today = Carbon::parse('2026-09-18');

        $this->assertSame(LeaseStatus::NO_END_DATE, LeaseStatus::fromEndDate(null, $today));
        $this->assertSame(LeaseStatus::EXPIRED, LeaseStatus::fromEndDate($today->copy()->subDay(), $today));
        $this->assertSame(LeaseStatus::WITHIN_90_DAYS, LeaseStatus::fromEndDate($today->copy()->addDays(90), $today));
        $this->assertSame(LeaseStatus::WITHIN_180_DAYS, LeaseStatus::fromEndDate($today->copy()->addDays(180), $today));
        $this->assertSame(LeaseStatus::SAFE, LeaseStatus::fromEndDate($today->copy()->addDays(181), $today));
    }
}
