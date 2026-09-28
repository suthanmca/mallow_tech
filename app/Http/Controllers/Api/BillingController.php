<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(Plan::query()->orderBy('monthly_price_cents')->get());
    }

    public function storeTenant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);
        $apiKey = Str::random(64);

        $tenant = DB::transaction(function () use ($validated, $apiKey) {
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'api_key_hash' => hash('sha256', $apiKey),
            ]);

            $tenant->subscription()->create([
                'plan_id' => $validated['plan_id'],
                'starts_at' => now(),
            ]);

            return $tenant;
        });

        return response()->json([
            'data' => $tenant,
            'api_key' => $apiKey,
        ], 201);
    }

    public function storeUsage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'occurred_at' => ['sometimes', 'date'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);
        $tenant = $request->attributes->get('tenant');
        $eventData = [
            'quantity' => $validated['quantity'],
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ];
        $existing = $tenant->usageEvents()
            ->where('idempotency_key', $validated['idempotency_key'])
            ->first();

        if ($existing !== null) {
            if ($existing->quantity !== $eventData['quantity']) {
                return response()->json([
                    'message' => 'This idempotency key was already used for a different quantity.',
                ], 409);
            }

            return response()->json(['data' => $existing], 200);
        }

        try {
            $event = $tenant->usageEvents()->create([
                ...$eventData,
                'idempotency_key' => $validated['idempotency_key'],
            ]);
        } catch (QueryException $exception) {
            $existing = $tenant->usageEvents()
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            if ($existing->quantity !== $eventData['quantity']) {
                return response()->json([
                    'message' => 'This idempotency key was already used for a different quantity.',
                ], 409);
            }

            return response()->json(['data' => $existing], 200);
        }

        return response()->json(['data' => $event], 201);
    }

    public function createInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);
        $tenant = $request->attributes->get('tenant');
        $subscription = $tenant->subscription()->with('plan')->where('active', true)->first();

        if ($subscription === null) {
            return response()->json(['message' => 'No active subscription was found.'], 422);
        }

        $periodStart = CarbonImmutable::createFromFormat('!Y-m', $validated['period']);
        $periodEnd = $periodStart->endOfMonth();

        try {
            $invoice = DB::transaction(function () use ($tenant, $subscription, $periodStart, $periodEnd) {
                $usageUnits = $tenant->usageEvents()
                    ->whereBetween('occurred_at', [$periodStart, $periodEnd])
                    ->sum('quantity');
                $plan = $subscription->plan;
                $overageUnits = max(0, $usageUnits - $plan->included_units);
                $overageAmount = $overageUnits * $plan->overage_price_cents;

                return Invoice::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'period_start' => $periodStart->toDateString(),
                    ],
                    [
                        'period_end' => $periodEnd->toDateString(),
                        'currency' => $plan->currency,
                        'usage_units' => $usageUnits,
                        'included_units' => $plan->included_units,
                        'overage_units' => $overageUnits,
                        'base_amount_cents' => $plan->monthly_price_cents,
                        'overage_amount_cents' => $overageAmount,
                        'total_amount_cents' => $plan->monthly_price_cents + $overageAmount,
                    ],
                );
            });
        } catch (QueryException $exception) {
            $invoice = $tenant->invoices()
                ->whereDate('period_start', $periodStart->toDateString())
                ->first();

            if ($invoice === null) {
                throw $exception;
            }
        }

        return response()->json(['data' => $invoice], $invoice->wasRecentlyCreated ? 201 : 200);
    }
}
