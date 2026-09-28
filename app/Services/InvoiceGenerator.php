<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InvoiceGenerator
{
    public function __construct(private BillingCalculator $calculator, private UsageAggregator $aggregator) {}

    public function generateForSubscription(CustomerSubscription $subscription): ?Invoice
    {
        return DB::transaction(function () use ($subscription) {
            $subscription = CustomerSubscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->first();

            if ($subscription === null || ! $subscription->active || $subscription->next_billing_at->isFuture()) {
                return null;
            }

            while ($this->aggregator->aggregateChunk(1000, $subscription->customer_id)) {
                // Drain usage while holding the subscription lock used by event writes.
            }

            $periodStart = CarbonImmutable::instance($subscription->current_period_start);
            $periodEnd = CarbonImmutable::instance($subscription->next_billing_at);
            $existing = Invoice::query()
                ->where('customer_id', $subscription->customer_id)
                ->whereDate('period_start', $periodStart->toDateString())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $segments = $subscription->segments()
                ->where('starts_at', '<', $periodEnd)
                ->where(function ($query) use ($periodStart) {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>', $periodStart);
                })
                ->orderBy('starts_at')
                ->get();
            $usageBySegment = DB::table('daily_usage')
                ->where('customer_id', $subscription->customer_id)
                ->where('usage_date', '>=', $periodStart->toDateString())
                ->where('usage_date', '<', $periodEnd->toDateString())
                ->select('subscription_segment_id', DB::raw('SUM(units) as units'))
                ->groupBy('subscription_segment_id')
                ->pluck('units', 'subscription_segment_id');

            if ($segments->isEmpty()) {
                return null;
            }

            $currency = $segments->first()->currency;
            $invoice = Invoice::create([
                'tenant_id' => $subscription->tenant_id,
                'customer_id' => $subscription->customer_id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->subDay()->toDateString(),
                'currency' => $currency,
                'usage_units' => 0,
                'included_units' => 0,
                'overage_units' => 0,
                'base_amount_cents' => 0,
                'overage_amount_cents' => 0,
                'total_amount_cents' => 0,
                'status' => 'issued',
            ]);
            $totals = [
                'usage_units' => 0,
                'included_units' => 0,
                'overage_units' => 0,
                'base_amount_cents' => 0,
                'overage_amount_cents' => 0,
            ];

            foreach ($segments as $segment) {
                $usage = (int) $usageBySegment->get($segment->id, 0);
                $amounts = $this->calculator->calculateSegment(
                    (int) $segment->base_price_cents,
                    (int) $segment->included_units,
                    (int) $segment->overage_price_cents,
                    $usage,
                    $periodStart,
                    $periodEnd,
                    CarbonImmutable::instance($segment->starts_at),
                    $segment->ends_at === null ? null : CarbonImmutable::instance($segment->ends_at),
                );

                $invoice->lines()->create([
                    'subscription_segment_id' => $segment->id,
                    'usage_units' => $usage,
                    ...$amounts,
                ]);
                $totals['usage_units'] += $usage;
                $totals['included_units'] += $amounts['included_units'];
                $totals['overage_units'] += $amounts['overage_units'];
                $totals['base_amount_cents'] += $amounts['base_amount_cents'];
                $totals['overage_amount_cents'] += $amounts['overage_amount_cents'];
            }

            $invoice->update([
                ...$totals,
                'total_amount_cents' => $totals['base_amount_cents'] + $totals['overage_amount_cents'],
            ]);

            $next = app(BillingCycle::class)->bounds($periodEnd, $subscription->billing_cycle)[1];
            $subscription->update([
                'current_period_start' => $periodEnd,
                'next_billing_at' => $next,
            ]);

            return $invoice->fresh(['lines']);
        });
    }
}
