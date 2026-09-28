<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('usage', function (Request $request) {
            $key = (string) $request->header('X-API-Key');

            return Limit::perMinute(6000)->by($key !== '' ? hash('sha256', $key) : $request->ip());
        });
    }
}
