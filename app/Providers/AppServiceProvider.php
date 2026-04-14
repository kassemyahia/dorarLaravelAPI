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
        $maxAttempts = max(1, (int) config('dorar.rate_limit_max', 100));
        $windowMs = max(1000, (int) config('dorar.rate_limit_each_ms', 24 * 60 * 60 * 1000));
        $decayMinutes = max(1, (int) ceil($windowMs / 60000));

        RateLimiter::for('api', function (Request $request) use ($maxAttempts, $decayMinutes) {
            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($request->ip() ?: 'global');
        });
    }
}
