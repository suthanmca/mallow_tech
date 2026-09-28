<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class BillingCycle
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function bounds(CarbonImmutable $date, string $cycle): array
    {
        return match ($cycle) {
            'monthly' => [$date->startOfMonth(), $date->startOfMonth()->addMonth()],
            'quarterly' => $this->quarterBounds($date),
            'yearly' => [$date->startOfYear(), $date->startOfYear()->addYear()],
            default => throw new \InvalidArgumentException('Unsupported billing cycle.'),
        };
    }

    private function quarterBounds(CarbonImmutable $date): array
    {
        $startMonth = intdiv($date->month - 1, 3) * 3 + 1;
        $start = $date->setDate($date->year, $startMonth, 1)->startOfDay();

        return [$start, $start->addMonths(3)];
    }
}
