<?php

namespace App\Jobs;

use App\Models\CustomerSubscription;
use App\Services\InvoiceGenerator;
use App\Services\UsageAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateCustomerInvoice implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 1800;

    public function __construct(public int $subscriptionId) {}

    public function uniqueId(): string
    {
        return 'customer-invoice:'.$this->subscriptionId;
    }

    public function handle(UsageAggregator $aggregator, InvoiceGenerator $generator): void
    {
        $subscription = CustomerSubscription::query()->find($this->subscriptionId);

        if ($subscription === null || ! $subscription->active || $subscription->next_billing_at->isFuture()) {
            return;
        }

        if ($aggregator->aggregateChunk(1000, $subscription->customer_id)) {
            $this->release(1);

            return;
        }

        $generator->generateForSubscription($subscription->fresh());
    }
}
