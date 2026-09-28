<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PlanPricing
{
    public function plansForMerchant(int $merchantId): Collection
    {
        return Cache::remember(
            $this->cacheKey($merchantId),
            now()->addMinutes(5),
            fn () => Plan::query()
                ->where('tenant_id', $merchantId)
                ->orderBy('base_price_cents')
                ->get(),
        );
    }

    public function find(int $merchantId, int $planId): ?Plan
    {
        return $this->plansForMerchant($merchantId)->firstWhere('id', $planId);
    }

    public function invalidate(int $merchantId): void
    {
        Cache::forget($this->cacheKey($merchantId));
    }

    private function cacheKey(int $merchantId): string
    {
        return "merchant:{$merchantId}:plans:v1";
    }
}
