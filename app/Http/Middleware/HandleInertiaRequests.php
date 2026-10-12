<?php

namespace App\Http\Middleware;

use App\Support\Localization;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $localization = app(Localization::class);
        $settings = $request->attributes->get('localization_settings') ?? $localization->settings();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->loadMissing('tenant'),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'demo_url' => route('demo'),
            'localization' => [
                ...$settings,
                'locale' => app()->getLocale(),
                'direction' => $localization->direction(app()->getLocale()),
                'available_languages' => $localization->availableLanguages(),
                'translations' => $localization->translations(app()->getLocale()),
            ],
        ];
    }
}
