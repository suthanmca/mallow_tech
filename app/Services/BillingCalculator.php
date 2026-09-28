<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class BillingCalculator
{
    /**
     * @return array{base_amount_cents: int, included_units: int, overage_units: int, overage_amount_cents: int}
     */
    public function calculateSegment(
        int $basePriceCents,
        int $cycleIncludedUnits,
        int $overagePriceCents,
        int $usageUnits,
        CarbonImmutable $cycleStart,
        CarbonImmutable $cycleEnd,
        CarbonImmutable $segmentStart,
        ?CarbonImmutable $segmentEnd,
    ): array {
        $activeStart = $segmentStart->greaterThan($cycleStart) ? $segmentStart : $cycleStart;
        $activeEnd = $segmentEnd !== null && $segmentEnd->lessThan($cycleEnd) ? $segmentEnd : $cycleEnd;
        $cycleSeconds = max(1, $cycleEnd->getTimestamp() - $cycleStart->getTimestamp());
        $activeSeconds = max(0, $activeEnd->getTimestamp() - $activeStart->getTimestamp());
        $fraction = min(1, $activeSeconds / $cycleSeconds);
        $includedUnits = (int) floor($cycleIncludedUnits * $fraction);
        $overageUnits = max(0, $usageUnits - $includedUnits);

        return [
            'base_amount_cents' => (int) round($basePriceCents * $fraction, 0, PHP_ROUND_HALF_UP),
            'included_units' => $includedUnits,
            'overage_units' => $overageUnits,
            'overage_amount_cents' => $overageUnits * $overagePriceCents,
        ];
    }
}
