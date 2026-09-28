<?php

namespace App\Jobs;

use App\Services\UsageAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateUsageEvents implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(public int $tenantId) {}

    public function uniqueId(): string
    {
        return 'usage-aggregate:'.$this->tenantId;
    }

    public function handle(UsageAggregator $aggregator): void
    {
        if ($aggregator->aggregateChunk(1000, tenantId: $this->tenantId)) {
            $this->release(1);
        }
    }
}
