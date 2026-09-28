<?php

namespace App\Actions;

use App\Data\UsageEventResult;
use App\Exceptions\UsageEventConflict;
use App\Exceptions\UsageNotBillable;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RecordUsageEvent
{
    public function handle(
        Tenant $merchant,
        Customer $customer,
        int $quantity,
        string $idempotencyKey,
        ?CarbonImmutable $occurredAt,
    ): UsageEventResult {
        $eventTime = $occurredAt ?? CarbonImmutable::now()->startOfSecond();

        try {
            return DB::transaction(function () use ($merchant, $customer, $quantity, $idempotencyKey, $occurredAt, $eventTime) {
                $existing = $merchant->usageEvents()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $this->retryResult($existing, $customer->id, $quantity, $occurredAt);
                }

                $subscription = CustomerSubscription::query()
                    ->where('tenant_id', $merchant->id)
                    ->where('customer_id', $customer->id)
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();
                if ($subscription === null) {
                    throw new UsageNotBillable('No active subscription was found.');
                }

                if ($eventTime->lessThan($subscription->current_period_start)
                    || $eventTime->greaterThanOrEqualTo($subscription->next_billing_at)) {
                    throw new UsageNotBillable('The event time is outside the active billing cycle.');
                }

                $segment = $subscription->segments()
                    ->where('starts_at', '<=', $eventTime)
                    ->where(function ($query) use ($eventTime) {
                        $query->whereNull('ends_at')->orWhere('ends_at', '>', $eventTime);
                    })
                    ->first();
                if ($segment === null) {
                    throw new UsageNotBillable('No subscription pricing segment was active at the event time.');
                }

                return new UsageEventResult($merchant->usageEvents()->create([
                    'customer_id' => $customer->id,
                    'subscription_segment_id' => $segment->id,
                    'quantity' => $quantity,
                    'occurred_at' => $eventTime,
                    'idempotency_key' => $idempotencyKey,
                ]), true);
            });
        } catch (QueryException $exception) {
            $existing = $merchant->usageEvents()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing === null) {
                throw $exception;
            }

            return $this->retryResult($existing, $customer->id, $quantity, $occurredAt);
        }
    }

    private function retryResult($existing, int $customerId, int $quantity, ?CarbonImmutable $occurredAt): UsageEventResult
    {
        if (
            (int) $existing->customer_id !== $customerId
            || (int) $existing->quantity !== $quantity
            || ($occurredAt !== null && ! CarbonImmutable::parse($existing->occurred_at)->equalTo($occurredAt))
        ) {
            throw new UsageEventConflict('This idempotency key was already used for a different event.');
        }

        return new UsageEventResult($existing, false);
    }
}
