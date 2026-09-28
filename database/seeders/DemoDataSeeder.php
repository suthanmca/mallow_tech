<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\PlanPricing;
use App\Services\UsageAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Tenant::query()->where('email', 'admin@test.com')->firstOrFail();
        $plans = collect([
            [
                'name' => 'Launch',
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'base_price_cents' => 2900,
                'monthly_price_cents' => 2900,
                'included_units' => 1200,
                'overage_price_cents' => 10,
            ],
            [
                'name' => 'Scale',
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'base_price_cents' => 7900,
                'monthly_price_cents' => 7900,
                'included_units' => 2500,
                'overage_price_cents' => 6,
            ],
            [
                'name' => 'Growth',
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'base_price_cents' => 14900,
                'monthly_price_cents' => 14900,
                'included_units' => 4000,
                'overage_price_cents' => 4,
            ],
        ])->mapWithKeys(function (array $attributes) use ($merchant) {
            $name = $attributes['name'];
            $plan = Plan::updateOrCreate(
                ['tenant_id' => $merchant->id, 'name' => $name],
                $attributes,
            );

            return [$name => $plan];
        });
        app(PlanPricing::class)->invalidate($merchant->id);

        $customers = [
            ['external_id' => 'demo-orbit-labs', 'name' => 'Orbit Labs', 'email' => 'billing@orbit.example', 'plan' => 'Growth', 'previous' => 5200, 'current' => 7600],
            ['external_id' => 'demo-juniper-ai', 'name' => 'Juniper AI', 'email' => 'finance@juniper.example', 'plan' => 'Growth', 'previous' => 3700, 'current' => 6400],
            ['external_id' => 'demo-meridian-systems', 'name' => 'Meridian Systems', 'email' => 'ops@meridian.example', 'plan' => 'Scale', 'previous' => 4200, 'current' => 5400],
            ['external_id' => 'demo-atlas-commerce', 'name' => 'Atlas Commerce', 'email' => 'accounts@atlas.example', 'plan' => 'Scale', 'previous' => 3300, 'current' => 4300],
            ['external_id' => 'demo-northstar-health', 'name' => 'Northstar Health', 'email' => 'billing@northstar.example', 'plan' => 'Launch', 'previous' => 3100, 'current' => 3600],
            ['external_id' => 'demo-brightline-media', 'name' => 'Brightline Media', 'email' => 'finance@brightline.example', 'plan' => 'Growth', 'previous' => 9800, 'current' => 2200],
            ['external_id' => 'demo-pinecone-cloud', 'name' => 'Pinecone Cloud', 'email' => 'accounts@pinecone.example', 'plan' => 'Launch', 'previous' => 1200, 'current' => 1800],
        ];

        $now = CarbonImmutable::now();
        $currentPeriodStart = $now->startOfMonth();
        $nextBillingAt = $currentPeriodStart->addMonth();
        $previousPeriodStart = $currentPeriodStart->subMonth();
        $previousPeriodEnd = $currentPeriodStart->subDay();
        $currentUsageEnd = $now->startOfDay()->subDay();
        if ($currentUsageEnd->lessThan($currentPeriodStart)) {
            $currentUsageEnd = $currentPeriodStart;
        }

        foreach ($customers as $sample) {
            $customer = $merchant->customers()->updateOrCreate(
                ['external_id' => $sample['external_id']],
                ['name' => $sample['name'], 'email' => $sample['email']],
            );
            $plan = $plans->get($sample['plan']);
            $subscription = CustomerSubscription::updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'tenant_id' => $merchant->id,
                    'billing_cycle' => 'monthly',
                    'started_at' => $previousPeriodStart,
                    'current_period_start' => $currentPeriodStart,
                    'next_billing_at' => $nextBillingAt,
                    'active' => true,
                ],
            );
            $segment = $subscription->segments()->whereNull('ends_at')->first();
            $segmentAttributes = [
                'plan_id' => $plan->id,
                'ends_at' => null,
                'currency' => $plan->currency,
                'base_price_cents' => $plan->base_price_cents,
                'included_units' => $plan->included_units,
                'overage_price_cents' => $plan->overage_price_cents,
            ];

            if ($segment === null) {
                $segment = $subscription->segments()->create([
                    ...$segmentAttributes,
                    'starts_at' => $previousPeriodStart,
                ]);
            } else {
                $segment->update($segmentAttributes);
            }

            $this->recordDailyUsage(
                $merchant,
                $customer,
                $segment,
                $sample['external_id'],
                $previousPeriodStart,
                $previousPeriodEnd,
                $sample['previous'],
            );
            $this->recordDailyUsage(
                $merchant,
                $customer,
                $segment,
                $sample['external_id'],
                $currentPeriodStart,
                $currentUsageEnd,
                $sample['current'],
            );

            $includedUnits = (int) $plan->included_units;
            $overageUnits = max(0, $sample['previous'] - $includedUnits);
            $overageAmount = $overageUnits * (int) $plan->overage_price_cents;
            $invoice = Invoice::query()
                ->where('customer_id', $customer->id)
                ->whereDate('period_start', $previousPeriodStart->toDateString())
                ->first();
            $invoice ??= new Invoice([
                'customer_id' => $customer->id,
                'period_start' => $previousPeriodStart->toDateString(),
            ]);
            $invoice->fill([
                'tenant_id' => $merchant->id,
                'period_end' => $previousPeriodEnd->toDateString(),
                'currency' => $plan->currency,
                'usage_units' => $sample['previous'],
                'included_units' => $includedUnits,
                'overage_units' => $overageUnits,
                'base_amount_cents' => $plan->base_price_cents,
                'overage_amount_cents' => $overageAmount,
                'total_amount_cents' => $plan->base_price_cents + $overageAmount,
                'status' => 'paid',
            ]);
            $invoice->save();
            $invoice->lines()->updateOrCreate(
                ['subscription_segment_id' => $segment->id],
                [
                    'usage_units' => $sample['previous'],
                    'included_units' => $includedUnits,
                    'overage_units' => $overageUnits,
                    'base_amount_cents' => $plan->base_price_cents,
                    'overage_amount_cents' => $overageAmount,
                ],
            );
        }

        $aggregator = app(UsageAggregator::class);
        while ($aggregator->aggregateChunk(1000, tenantId: $merchant->id)) {
            // Continue until all merchant usage, including the demo rows, is rolled up.
        }
        $aggregator->aggregateChunk(1000, tenantId: $merchant->id);
    }

    private function recordDailyUsage(
        Tenant $merchant,
        Customer $customer,
        $segment,
        string $externalId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $totalUnits,
    ): void {
        $dayCount = max(1, $start->diffInDays($end) + 1);
        $dailyUnits = intdiv($totalUnits, $dayCount);
        $remainder = $totalUnits % $dayCount;

        for ($day = 0; $day < $dayCount; $day++) {
            $date = $start->addDays($day);
            $quantity = $dailyUnits + ($day < $remainder ? 1 : 0);
            $key = 'demo:'.$externalId.':'.$date->toDateString();
            $merchant->usageEvents()->firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'customer_id' => $customer->id,
                    'subscription_segment_id' => $segment->id,
                    'quantity' => $quantity,
                    'occurred_at' => $date->startOfDay()->addHours(12),
                ],
            );
        }
    }
}
