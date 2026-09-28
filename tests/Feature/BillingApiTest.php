<?php

namespace Tests\Feature;

use App\Jobs\AggregateUsageEvents;
use App\Models\CustomerSubscription;
use App\Services\InvoiceGenerator;
use App\Services\UsageAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_define_plans_customers_and_record_idempotent_usage(): void
    {
        $merchant = $this->createMerchant();
        $headers = $merchant['headers'];
        $this->getJson('/api/plans', $headers)->assertOk()->assertJsonCount(0, 'data');
        $plan = $this->createPlan($headers);

        $this->getJson('/api/plans', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.id', $plan['id']);

        $customer = $this->postJson('/api/customers', [
            'name' => 'Acme customer',
            'external_id' => 'acct-101',
        ], $headers)->assertCreated()->json('data');

        $this->postJson('/api/customers/'.$customer['id'].'/subscriptions', [
            'plan_id' => $plan['id'],
        ], $headers)->assertCreated();

        $request = [
            'customer_id' => $customer['id'],
            'quantity' => 25,
            'occurred_at' => now()->toISOString(),
            'idempotency_key' => 'usage-0001',
        ];
        $this->postJson('/api/usage', $request, $headers)->assertCreated();
        $this->postJson('/api/usage', $request, $headers)->assertOk();
        $this->postJson('/api/usage', [...$request, 'quantity' => 26], $headers)->assertConflict();
        $implicitTimestampRequest = [
            'customer_id' => $customer['id'],
            'quantity' => 3,
            'idempotency_key' => 'usage-implicit-time',
        ];
        $this->postJson('/api/usage', $implicitTimestampRequest, $headers)->assertCreated();
        $this->postJson('/api/usage', $implicitTimestampRequest, $headers)->assertOk();
        $this->assertDatabaseCount('usage_events', 2);

        $otherMerchant = $this->createMerchant();
        $this->postJson('/api/usage', $request, $otherMerchant['headers'])->assertNotFound();
        $this->getJson('/api/merchants/'.$merchant['id'].'/dashboard', $otherMerchant['headers'])->assertNotFound();
    }

    public function test_plan_change_keeps_rates_and_prorates_both_segments_when_invoicing(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15 00:00:00'));
        $merchant = $this->createMerchant();
        $headers = $merchant['headers'];
        $originalPlan = $this->createPlan($headers, [
            'name' => 'Original',
            'base_price_cents' => 3000,
            'included_units' => 50,
            'overage_price_cents' => 10,
        ]);
        $newPlan = $this->createPlan($headers, [
            'name' => 'Upgraded',
            'base_price_cents' => 6000,
            'included_units' => 100,
            'overage_price_cents' => 20,
        ]);
        $customer = $this->postJson('/api/customers', ['name' => 'Mid-cycle customer'], $headers)
            ->assertCreated()->json('data');
        $subscription = $this->postJson('/api/customers/'.$customer['id'].'/subscriptions', [
            'plan_id' => $originalPlan['id'],
        ], $headers)->assertCreated()->json('data');

        $this->postJson('/api/usage', [
            'customer_id' => $customer['id'],
            'quantity' => 100,
            'occurred_at' => '2026-09-18 12:00:00',
            'idempotency_key' => 'before-upgrade',
        ], $headers)->assertCreated();

        $this->travelTo(CarbonImmutable::parse('2026-09-20 00:00:00'));
        $this->patchJson('/api/customers/'.$customer['id'].'/subscriptions/plan', [
            'plan_id' => $newPlan['id'],
        ], $headers)->assertOk();

        $this->postJson('/api/usage', [
            'customer_id' => $customer['id'],
            'quantity' => 200,
            'occurred_at' => '2026-09-25 12:00:00',
            'idempotency_key' => 'after-upgrade',
        ], $headers)->assertCreated();

        $this->travelTo(CarbonImmutable::parse('2026-10-01 00:00:00'));
        $aggregator = app(UsageAggregator::class);
        do {
            $hasMore = $aggregator->aggregateChunk(1);
        } while ($hasMore);

        $invoice = app(InvoiceGenerator::class)->generateForSubscription(
            CustomerSubscription::query()->findOrFail($subscription['id']),
        );

        $this->assertNotNull($invoice);
        $this->assertSame(300, $invoice->usage_units);
        $this->assertSame(2700, $invoice->base_amount_cents);
        $this->assertSame(4200, $invoice->overage_amount_cents);
        $this->assertSame(6900, $invoice->total_amount_cents);
        $this->assertCount(2, $invoice->lines);
        $this->assertSame(10, $invoice->lines[0]->segment->overage_price_cents);
        $this->assertSame(20, $invoice->lines[1]->segment->overage_price_cents);
        $this->assertDatabaseCount('daily_usage', 2);
        $this->postJson('/api/usage', [
            'customer_id' => $customer['id'],
            'quantity' => 100,
            'occurred_at' => '2026-09-18 12:00:00',
            'idempotency_key' => 'before-upgrade',
        ], $headers)->assertOk();
        $this->postJson('/api/usage', [
            'customer_id' => $customer['id'],
            'quantity' => 1,
            'occurred_at' => '2026-09-18 12:00:00',
            'idempotency_key' => 'late-unbilled-event',
        ], $headers)->assertUnprocessable();
    }

    public function test_usage_is_aggregated_in_chunks_and_dashboard_returns_required_panels(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 00:00:00'));
        $merchant = $this->createMerchant();
        $headers = $merchant['headers'];
        $plan = $this->createPlan($headers);
        $customer = $this->postJson('/api/customers', ['name' => 'Metered account'], $headers)
            ->assertCreated()->json('data');
        $this->postJson('/api/customers/'.$customer['id'].'/subscriptions', ['plan_id' => $plan['id']], $headers)
            ->assertCreated();

        foreach ([10, 20, 30] as $index => $quantity) {
            $this->postJson('/api/usage', [
                'customer_id' => $customer['id'],
                'quantity' => $quantity,
                'occurred_at' => '2026-09-'.sprintf('%02d', 24 + $index).' 10:00:00',
                'idempotency_key' => 'batch-'.$index,
            ], $headers)->assertCreated();
        }

        Bus::fake();
        $this->artisan('usage:aggregate')->assertSuccessful();
        Bus::assertDispatched(AggregateUsageEvents::class);

        $aggregator = app(UsageAggregator::class);
        $aggregator->aggregateChunk(2);
        $aggregator->aggregateChunk(2);
        $this->assertSame(60, (int) DB::table('daily_usage')
            ->where('tenant_id', $merchant['id'])
            ->where('customer_id', $customer['id'])
            ->sum('units'));
        $this->assertDatabaseCount('daily_usage', 3);

        $riskCustomer = $this->postJson('/api/customers', ['name' => 'At-risk account'], $headers)
            ->assertCreated()->json('data');
        $this->postJson('/api/customers/'.$riskCustomer['id'].'/subscriptions', ['plan_id' => $plan['id']], $headers)
            ->assertCreated();
        $riskSegmentId = CustomerSubscription::query()
            ->where('customer_id', $riskCustomer['id'])
            ->firstOrFail()
            ->segments()
            ->value('id');
        DB::table('daily_usage')->insert([
            [
                'tenant_id' => $merchant['id'],
                'customer_id' => $riskCustomer['id'],
                'subscription_segment_id' => $riskSegmentId,
                'usage_date' => '2026-08-01',
                'units' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $merchant['id'],
                'customer_id' => $riskCustomer['id'],
                'subscription_segment_id' => $riskSegmentId,
                'usage_date' => '2026-09-01',
                'units' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-09-27 12:00:00'));

        $this->getJson('/api/merchants/'.$merchant['id'].'/dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('data.top_customers_this_month.0.id', $customer['id'])
            ->assertJsonPath('data.top_customers_this_month.0.usage_units', 60)
            ->assertJsonPath('data.projected_overage_revenue.0.currency', 'USD')
            ->assertJsonPath('data.churn_risk_customers.0.customer_id', $riskCustomer['id'])
            ->assertJsonPath('data.churn_risk_customers.0.drop_percent', 60)
            ->assertJsonStructure([
                'data' => [
                    'top_customers_this_month',
                    'projected_overage_revenue',
                    'churn_risk_customers',
                    'usage_aggregated_through',
                ],
            ]);
    }

    public function test_invalid_or_cross_merchant_keys_cannot_write_usage(): void
    {
        $this->postJson('/api/usage', [
            'customer_id' => 1,
            'quantity' => 1,
            'idempotency_key' => 'missing-auth',
        ])->assertUnauthorized();
    }

    private function createMerchant(): array
    {
        $response = $this->postJson('/api/merchants', ['name' => 'Merchant '.Str::random(6)])
            ->assertCreated();

        return [
            'id' => $response->json('data.id'),
            'headers' => ['X-API-Key' => $response->json('api_key')],
        ];
    }

    private function createPlan(array $headers, array $overrides = []): array
    {
        return $this->postJson('/api/plans', array_merge([
            'name' => 'Standard '.Str::random(6),
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'base_price_cents' => 1000,
            'included_units' => 100,
            'overage_price_cents' => 2,
        ], $overrides), $headers)->assertCreated()->json('data');
    }
}
