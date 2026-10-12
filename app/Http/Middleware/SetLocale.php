<?php

namespace App\Http\Middleware;

use App\Support\Localization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private Localization $localization) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('localization_settings', $this->localization->settings());
        app()->setLocale($this->localization->resolveLocale($request));

        return $next($request);
    }
}
