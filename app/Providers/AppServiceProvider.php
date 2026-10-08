<?php

namespace App\Providers;

use App\Services\ClientIpResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
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
        URL::forceRootUrl(config('app.url'));
        RateLimiter::for('public-chat', function (Request $request) {
            return Limit::perMinute(30)->by(app(ClientIpResolver::class)->resolve($request).'|'.$request->input('bot_public_key', 'unknown'));
        });

        RateLimiter::for('support-request', function (Request $request) {
            return Limit::perMinute(5)->by(app(ClientIpResolver::class)->resolve($request));
        });

        RateLimiter::for('knowledge-research', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->user()?->id ?: app(ClientIpResolver::class)->resolve($request));
        });

        Vite::prefetch(concurrency: 3);
    }
}
