<?php

namespace Tests\Unit;

use App\Services\BillingCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BillingCalculatorTest extends TestCase
{
    public function test_it_prorates_base_price_and_allowance_for_a_mid_cycle_start(): void
    {
        $result = (new BillingCalculator)->calculateSegment(
            3100,
            310,
            10,
            160,
            CarbonImmutable::parse('2026-01-01 00:00:00'),
            CarbonImmutable::parse('2026-02-01 00:00:00'),
            CarbonImmutable::parse('2026-01-16 00:00:00'),
            null,
        );

        self::assertSame(1600, $result['base_amount_cents']);
        self::assertSame(160, $result['included_units']);
        self::assertSame(0, $result['overage_units']);
        self::assertSame(0, $result['overage_amount_cents']);
    }

    public function test_overage_starts_only_after_prorated_included_units_are_exceeded(): void
    {
        $calculator = new BillingCalculator;
        $start = CarbonImmutable::parse('2026-01-16 00:00:00');
        $end = CarbonImmutable::parse('2026-02-01 00:00:00');
        $cycleStart = CarbonImmutable::parse('2026-01-01 00:00:00');

        $atAllowance = $calculator->calculateSegment(3100, 310, 10, 160, $cycleStart, $end, $start, null);
        $aboveAllowance = $calculator->calculateSegment(3100, 310, 10, 161, $cycleStart, $end, $start, null);

        self::assertSame(0, $atAllowance['overage_units']);
        self::assertSame(1, $aboveAllowance['overage_units']);
        self::assertSame(10, $aboveAllowance['overage_amount_cents']);
    }

    public function test_a_plan_segment_ending_mid_cycle_is_prorated_and_keeps_its_own_rate(): void
    {
        $result = (new BillingCalculator)->calculateSegment(
            3000,
            60,
            25,
            40,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-02-01'),
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-16'),
        );

        self::assertSame(1452, $result['base_amount_cents']);
        self::assertSame(29, $result['included_units']);
        self::assertSame(11, $result['overage_units']);
        self::assertSame(275, $result['overage_amount_cents']);
    }
}
