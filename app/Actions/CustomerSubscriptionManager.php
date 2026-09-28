<?php

namespace App\Actions;

use App\Exceptions\SubscriptionRuleViolation;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\BillingCycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CustomerSubscriptionManager
{
    public function __construct(private BillingCycle $cycles) {}

    public function subscribe(Tenant $merchant, Customer $customer, Plan $plan): CustomerSubscription
    {
        if ($customer->subscription()->exists()) {
            throw new SubscriptionRuleViolation('This customer already has a subscription.', 409);
        }

        $startedAt = CarbonImmutable::now();
        [$periodStart, $nextBillingAt] = $this->cycles->bounds($startedAt, $plan->billing_cycle);

        return DB::transaction(function () use ($merchant, $customer, $plan, $startedAt, $periodStart, $nextBillingAt) {
            $subscription = $customer->subscription()->create([
                'tenant_id' => $merchant->id,
                'billing_cycle' => $plan->billing_cycle,
                'started_at' => $startedAt,
                'current_period_start' => $periodStart,
                'next_billing_at' => $nextBillingAt,
                'active' => true,
            ]);
            $this->addSegment($subscription, $plan, $startedAt);

            return $subscription->load('segments');
        });
    }

    public function changePlan(Customer $customer, Plan $plan): CustomerSubscription
    {
        $subscriptionId = $customer->subscription()->where('active', true)->value('id');
        if ($subscriptionId === null) {
            throw new SubscriptionRuleViolation('No active subscription was found.', 404);
        }

        return DB::transaction(function () use ($subscriptionId, $plan) {
            $subscription = CustomerSubscription::query()
                ->whereKey($subscriptionId)
                ->lockForUpdate()
                ->firstOrFail();
            $currentSegment = $subscription->segments()->whereNull('ends_at')->lockForUpdate()->firstOrFail();
            $changedAt = CarbonImmutable::now();

            if ($plan->billing_cycle !== $subscription->billing_cycle) {
                throw new SubscriptionRuleViolation('A plan change cannot alter the active billing cycle.');
            }
            if ((int) $currentSegment->plan_id === $plan->id) {
                throw new SubscriptionRuleViolation('The customer is already on this plan.');
            }
            if ($currentSegment->currency !== $plan->currency) {
                throw new SubscriptionRuleViolation('Plan changes must use the current billing currency.');
            }
            if ($changedAt->greaterThanOrEqualTo($subscription->next_billing_at)) {
                throw new SubscriptionRuleViolation('The current billing cycle has ended.', 409);
            }

            $currentSegment->update(['ends_at' => $changedAt]);
            $this->addSegment($subscription, $plan, $changedAt);

            return $subscription->fresh()->load('segments');
        });
    }

    private function addSegment(CustomerSubscription $subscription, Plan $plan, CarbonImmutable $startsAt): void
    {
        $subscription->segments()->create([
            'plan_id' => $plan->id,
            'starts_at' => $startsAt,
            'currency' => $plan->currency,
            'base_price_cents' => $plan->base_price_cents,
            'included_units' => $plan->included_units,
            'overage_price_cents' => $plan->overage_price_cents,
        ]);
    }
}
