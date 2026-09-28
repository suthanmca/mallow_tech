<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MerchantDashboard
{
    public function __construct(private BillingCalculator $calculator) {}

    public function forMerchant(Tenant $tenant): array
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $topCustomers = DB::table('daily_usage')
            ->join('customers', 'customers.id', '=', 'daily_usage.customer_id')
            ->where('daily_usage.tenant_id', $tenant->id)
            ->whereBetween('daily_usage.usage_date', [$monthStart->toDateString(), $now->toDateString()])
            ->select('customers.id', 'customers.name', DB::raw('SUM(daily_usage.units) as usage_units'))
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('usage_units')
            ->limit(5)
            ->get();
        $comparisonDays = min($now->day, $now->subMonthNoOverflow()->daysInMonth);
        $previousStart = $now->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $previousStart->addDays($comparisonDays - 1);
        $currentComparisonEnd = $monthStart->addDays($comparisonDays - 1);
        $previousUsage = DB::table('daily_usage')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('usage_date', [$previousStart->toDateString(), $previousEnd->toDateString()])
            ->select('customer_id', DB::raw('SUM(units) as units'))
            ->groupBy('customer_id')
            ->pluck('units', 'customer_id');
        $currentUsage = DB::table('daily_usage')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('usage_date', [$monthStart->toDateString(), $currentComparisonEnd->toDateString()])
            ->select('customer_id', DB::raw('SUM(units) as units'))
            ->groupBy('customer_id')
            ->pluck('units', 'customer_id');
        $riskCustomers = $tenant->customers()
            ->whereIn('id', $previousUsage->keys())
            ->get(['id', 'name'])
            ->filter(function (Customer $customer) use ($previousUsage, $currentUsage) {
                $previous = (int) $previousUsage->get($customer->id, 0);
                $current = (int) $currentUsage->get($customer->id, 0);

                return $previous > 0 && $current < $previous * 0.5;
            })
            ->map(fn (Customer $customer) => [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'previous_period_units' => (int) $previousUsage->get($customer->id),
                'current_period_units' => (int) $currentUsage->get($customer->id, 0),
                'drop_percent' => round((1 - $currentUsage->get($customer->id, 0) / $previousUsage->get($customer->id)) * 100, 1),
            ])->values();

        $projectedOverageByCurrency = [];
        foreach ($tenant->customerSubscriptions()->where('active', true)->with('segments')->get() as $subscription) {
            $cycleStart = CarbonImmutable::instance($subscription->current_period_start);
            $cycleEnd = CarbonImmutable::instance($subscription->next_billing_at);

            foreach ($subscription->segments as $segment) {
                $segmentStart = CarbonImmutable::instance($segment->starts_at);
                $activeStart = $segmentStart->greaterThan($cycleStart) ? $segmentStart : $cycleStart;
                $activeEnd = $segment->ends_at === null
                    ? $cycleEnd
                    : CarbonImmutable::instance($segment->ends_at)->min($cycleEnd);
                $elapsedEnd = $now->min($activeEnd);
                $elapsed = max(0, $elapsedEnd->getTimestamp() - $activeStart->getTimestamp());
                $activeSeconds = max(0, $activeEnd->getTimestamp() - $activeStart->getTimestamp());

                if ($elapsed === 0 || $activeSeconds === 0) {
                    continue;
                }

                $usage = (int) DB::table('daily_usage')
                    ->where('tenant_id', $tenant->id)
                    ->where('customer_id', $subscription->customer_id)
                    ->where('subscription_segment_id', $segment->id)
                    ->whereBetween('usage_date', [$activeStart->toDateString(), $elapsedEnd->toDateString()])
                    ->sum('units');
                $projectedUsage = (int) round($usage * $activeSeconds / $elapsed);
                $amounts = $this->calculator->calculateSegment(
                    (int) $segment->base_price_cents,
                    (int) $segment->included_units,
                    (int) $segment->overage_price_cents,
                    $projectedUsage,
                    $cycleStart,
                    $cycleEnd,
                    $segmentStart,
                    $segment->ends_at === null ? null : CarbonImmutable::instance($segment->ends_at),
                );
                $projectedOverageByCurrency[$segment->currency] = ($projectedOverageByCurrency[$segment->currency] ?? 0)
                    + $amounts['overage_amount_cents'];
            }
        }

        return [
            'top_customers_this_month' => $topCustomers,
            'projected_overage_revenue' => collect($projectedOverageByCurrency)
                ->map(fn (int $amount, string $currency) => ['currency' => $currency, 'amount_cents' => $amount])
                ->values(),
            'churn_risk_customers' => $riskCustomers,
            'usage_aggregated_through' => DB::table('usage_events')
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('aggregated_at')
                ->max('aggregated_at'),
        ];
    }
}
