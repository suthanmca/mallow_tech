<?php

namespace App\Http\Controllers\Api;

use App\Actions\CustomerSubscriptionManager;
use App\Actions\RecordUsageEvent;
use App\Exceptions\SubscriptionRuleViolation;
use App\Exceptions\UsageEventConflict;
use App\Exceptions\UsageNotBillable;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\MerchantDashboard;
use App\Services\PlanPricing;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MerchantController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $apiKey = Str::random(64);
        $merchant = Tenant::create([
            'name' => $validated['name'],
            'api_key_hash' => hash('sha256', $apiKey),
        ]);

        return response()->json(['data' => $merchant, 'api_key' => $apiKey], 201);
    }

    public function plans(Request $request, PlanPricing $pricing): JsonResponse
    {
        return response()->json(['data' => $pricing->plansForMerchant($this->merchant($request)->id)->values()]);
    }

    public function storePlan(Request $request, PlanPricing $pricing): JsonResponse
    {
        $merchant = $this->merchant($request);
        $validated = $this->validatePlan($request, $merchant->id);
        $validated['monthly_price_cents'] = $validated['base_price_cents'];
        $plan = $merchant->plans()->create($validated);
        $pricing->invalidate($merchant->id);

        return response()->json(['data' => $plan], 201);
    }

    public function updatePlan(Request $request, int $plan, PlanPricing $pricing): JsonResponse
    {
        $merchant = $this->merchant($request);
        $plan = $merchant->plans()->findOrFail($plan);
        $validated = $this->validatePlan($request, $merchant->id, $plan->id, true);
        if (array_key_exists('base_price_cents', $validated)) {
            $validated['monthly_price_cents'] = $validated['base_price_cents'];
        }
        $plan->update($validated);
        $pricing->invalidate($merchant->id);

        return response()->json(['data' => $plan->fresh()]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'external_id' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('customers', 'external_id')->where('tenant_id', $merchant->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $customer = $merchant->customers()->create($validated);

        return response()->json(['data' => $customer], 201);
    }

    public function subscribe(Request $request, int $customer, PlanPricing $pricing, CustomerSubscriptionManager $subscriptions): JsonResponse
    {
        $validated = $request->validate(['plan_id' => ['required', 'integer']]);
        $merchant = $this->merchant($request);
        $customer = $merchant->customers()->findOrFail($customer);
        $plan = $pricing->find($merchant->id, (int) $validated['plan_id']);

        if ($plan === null) {
            return response()->json(['message' => 'Plan not found for this merchant.'], 404);
        }

        try {
            $subscription = $subscriptions->subscribe($merchant, $customer, $plan);
        } catch (SubscriptionRuleViolation $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        return response()->json(['data' => $subscription], 201);
    }

    public function changePlan(Request $request, int $customer, PlanPricing $pricing, CustomerSubscriptionManager $subscriptions): JsonResponse
    {
        $validated = $request->validate(['plan_id' => ['required', 'integer']]);
        $merchant = $this->merchant($request);
        $customer = $merchant->customers()->findOrFail($customer);
        $plan = $pricing->find($merchant->id, (int) $validated['plan_id']);

        if ($plan === null) {
            return response()->json(['message' => 'Plan not found for this merchant.'], 404);
        }

        try {
            $subscription = $subscriptions->changePlan($customer, $plan);
        } catch (SubscriptionRuleViolation $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        return response()->json(['data' => $subscription]);
    }

    public function storeUsage(Request $request, RecordUsageEvent $recordUsage): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'occurred_at' => ['sometimes', 'date'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);
        $merchant = $this->merchant($request);
        $customer = $merchant->customers()->find($validated['customer_id']);

        if ($customer === null) {
            return response()->json(['message' => 'Customer not found for this merchant.'], 404);
        }

        $requestedOccurredAt = isset($validated['occurred_at'])
            ? CarbonImmutable::parse($validated['occurred_at'])->startOfSecond()
            : null;
        try {
            $result = $recordUsage->handle(
                $merchant,
                $customer,
                (int) $validated['quantity'],
                $validated['idempotency_key'],
                $requestedOccurredAt,
            );
        } catch (UsageEventConflict $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (UsageNotBillable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result->event], $result->created ? 201 : 200);
    }

    public function dashboard(Request $request, int $merchant, MerchantDashboard $dashboard): JsonResponse
    {
        $tenant = $this->merchant($request);
        abort_unless($tenant->id === $merchant, 404);

        return response()->json(['data' => $dashboard->forMerchant($tenant)]);
    }

    private function validatePlan(Request $request, int $merchantId, ?int $ignorePlanId = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $uniqueName = Rule::unique('plans', 'name')->where('tenant_id', $merchantId);
        if ($ignorePlanId !== null) {
            $uniqueName->ignore($ignorePlanId);
        }

        return $request->validate([
            'name' => [$required, 'string', 'max:120', $uniqueName],
            'currency' => [$required, 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'billing_cycle' => [$required, 'in:monthly,quarterly,yearly'],
            'base_price_cents' => [$required, 'integer', 'min:0'],
            'included_units' => [$required, 'integer', 'min:0'],
            'overage_price_cents' => [$required, 'integer', 'min:0'],
        ]);
    }

    private function merchant(Request $request): Tenant
    {
        return $request->attributes->get('tenant');
    }
}
