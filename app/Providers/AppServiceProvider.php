<?php

namespace App\Providers;

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
        URL::forceScheme('https');
        RateLimiter::for('public-chat', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip().'|'.$request->input('bot_public_key', 'unknown'));
        });

        RateLimiter::for('support-request', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Vite::prefetch(concurrency: 3);
    }
}
