<?php

namespace Tests\Unit;

use App\Services\BillingCycle;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BillingCycleTest extends TestCase
{
    public function test_it_finds_calendar_month_quarter_and_year_boundaries(): void
    {
        $service = new BillingCycle;
        $date = CarbonImmutable::parse('2026-09-27 12:00:00');

        [$monthStart, $monthEnd] = $service->bounds($date, 'monthly');
        [$quarterStart, $quarterEnd] = $service->bounds($date, 'quarterly');
        [$yearStart, $yearEnd] = $service->bounds($date, 'yearly');

        self::assertSame('2026-09-01', $monthStart->toDateString());
        self::assertSame('2026-10-01', $monthEnd->toDateString());
        self::assertSame('2026-07-01', $quarterStart->toDateString());
        self::assertSame('2026-10-01', $quarterEnd->toDateString());
        self::assertSame('2026-01-01', $yearStart->toDateString());
        self::assertSame('2027-01-01', $yearEnd->toDateString());
    }
}
