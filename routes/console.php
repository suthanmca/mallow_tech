<?php

use App\Jobs\AggregateUsageEvents;
use App\Jobs\GenerateCustomerInvoice;
use App\Models\CustomerSubscription;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('usage:aggregate', function () {
    $tenantIds = DB::table('usage_events')
        ->whereNull('aggregated_at')
        ->distinct()
        ->orderBy('tenant_id')
        ->pluck('tenant_id');

    foreach ($tenantIds as $tenantId) {
        AggregateUsageEvents::dispatch((int) $tenantId)->onQueue('usage');
    }

    $this->info('Usage aggregation queued for '.$tenantIds->count().' merchants.');
})->purpose('Queue a chunked aggregation of pending usage events');

Schedule::command('usage:aggregate')->everyMinute()->withoutOverlapping();

Schedule::call(function () {
    CustomerSubscription::query()
        ->where('active', true)
        ->where('next_billing_at', '<=', now())
        ->orderBy('id')
        ->limit(500)
        ->pluck('id')
        ->each(fn (int $id) => GenerateCustomerInvoice::dispatch($id)->onQueue('billing'));
})->name('dispatch-due-customer-invoices')->everyMinute()->withoutOverlapping();
