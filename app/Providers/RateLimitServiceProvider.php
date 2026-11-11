<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** PRD 13.1 rate limits: submit 10/min, autosave 120/min per user (login 5/min lives in Breeze's LoginForm). */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('exam-submit', fn (Request $r) => Limit::perMinute(10)->by((string) ($r->user()->id ?? $r->ip())));
        RateLimiter::for('autosave', fn (Request $r) => Limit::perMinute(120)->by((string) ($r->user()->id ?? $r->ip())));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(240)->by((string) ($r->user()->id ?? $r->ip())));
    }
}
