<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class UsageAggregator
{
    public function aggregateChunk(int $chunkSize = 1000, ?int $customerId = null, ?int $tenantId = null): bool
    {
        return DB::transaction(function () use ($chunkSize, $customerId, $tenantId) {
            $query = DB::table('usage_events')
                ->whereNull('aggregated_at')
                ->whereNotNull('customer_id')
                ->whereNotNull('subscription_segment_id')
                ->orderBy('id')
                ->limit($chunkSize)
                ->lockForUpdate();

            if ($customerId !== null) {
                $query->where('customer_id', $customerId);
            }
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            $events = $query->get();

            if ($events->isEmpty()) {
                return false;
            }

            $groups = [];
            foreach ($events as $event) {
                $date = substr($event->occurred_at, 0, 10);
                $key = implode(':', [$event->tenant_id, $event->customer_id, $event->subscription_segment_id, $date]);
                $groups[$key] ??= [
                    'tenant_id' => $event->tenant_id,
                    'customer_id' => $event->customer_id,
                    'subscription_segment_id' => $event->subscription_segment_id,
                    'usage_date' => $date,
                    'units' => 0,
                ];
                $groups[$key]['units'] += (int) $event->quantity;
            }

            foreach ($groups as $group) {
                $existing = DB::table('daily_usage')
                    ->where('tenant_id', $group['tenant_id'])
                    ->where('customer_id', $group['customer_id'])
                    ->where('subscription_segment_id', $group['subscription_segment_id'])
                    ->where('usage_date', $group['usage_date'])
                    ->lockForUpdate()
                    ->first();

                if ($existing === null) {
                    DB::table('daily_usage')->insert([
                        ...$group,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('daily_usage')
                        ->where('id', $existing->id)
                        ->update([
                            'units' => $existing->units + $group['units'],
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::table('usage_events')
                ->whereIn('id', $events->pluck('id'))
                ->update(['aggregated_at' => now()]);

            return $events->count() === $chunkSize;
        });
    }
}
