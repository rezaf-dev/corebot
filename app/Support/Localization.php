<?php

namespace App\Support;

use App\Models\ApplicationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class Localization
{
    /** @return array{default_locale: string, enabled_locales: list<string>} */
    public function settings(): array
    {
        $settings = ApplicationSetting::query()->where('key', 'localization')->first();

        return [
            'default_locale' => $settings?->default_locale ?? config('localization.default_locale'),
            'enabled_locales' => $settings?->enabled_locales ?? config('localization.enabled_locales'),
        ];
    }

    /** @return list<array{code: string, name: string, native_name: string, direction: string}> */
    public function availableLanguages(): array
    {
        $languages = [];
        foreach (config('localization.languages') as $code => $language) {
            $languages[] = ['code' => $code, ...$language];
        }

        return $languages;
    }

    public function resolveLocale(Request $request): string
    {
        $settings = $request->attributes->get('localization_settings') ?? $this->settings();
        $preferred = $request->session()->get('locale');

        return in_array($preferred, $settings['enabled_locales'], true)
            ? $preferred
            : $settings['default_locale'];
    }

    public function direction(string $locale): string
    {
        return config('localization.languages.'.$locale.'.direction', 'ltr');
    }

    /** @return array{locale: string, direction: string, translations: array<string, string>} */
    public function widgetLocalization(?string $preferredLocale = null): array
    {
        $settings = $this->settings();
        $locale = in_array($preferredLocale, $settings['enabled_locales'], true)
            ? $preferredLocale
            : $settings['default_locale'];

        return [
            'locale' => $locale,
            'direction' => $this->direction($locale),
            'translations' => $this->translations($locale),
        ];
    }

    /** @return array<string, string> */
    public function translations(string $locale): array
    {
        $fallback = $this->loadTranslations(config('localization.default_locale'));

        return array_replace($fallback, $this->loadTranslations($locale));
    }

    /** @return array<string, string> */
    private function loadTranslations(string $locale): array
    {
        $path = lang_path($locale.'.json');

        return File::exists($path) ? File::json($path) : [];
    }
}
