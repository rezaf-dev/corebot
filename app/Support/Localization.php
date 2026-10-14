<?php

namespace App\Support;

use App\Models\ApplicationSetting;
use App\Models\Bot;
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

    /** @return array<string, mixed> */
    public function widgetLocalization(?string $preferredLocale = null, ?Bot $bot = null): array
    {
        $settings = $this->settings();
        $config = $bot?->resolvedWidgetConfig() ?? [];
        $requested = $config['enabled_locales'] ?? [];
        $enabled = is_array($requested) && $requested !== []
            ? array_values(array_intersect($settings['enabled_locales'], $requested))
            : $settings['enabled_locales'];
        $enabled = $enabled !== [] ? $enabled : $settings['enabled_locales'];
        $default = $config['default_locale'] ?? '';
        if (! in_array($default, $enabled, true)) {
            $default = in_array($settings['default_locale'], $enabled, true) ? $settings['default_locale'] : $enabled[0];
        }
        $locale = in_array($preferredLocale, $enabled, true) ? $preferredLocale : $default;

        return [
            'locale' => $locale,
            'default_locale' => $default,
            'enabled_locales' => $enabled,
            'available_languages' => array_values(array_filter($this->availableLanguages(), fn (array $language): bool => in_array($language['code'], $enabled, true))),
            'direction' => $this->direction($locale),
            'translations' => $this->translations($locale),
        ];
    }

    /** @return array<string, mixed> */
    public function widgetConfig(Bot $bot, string $locale): array
    {
        $config = $bot->resolvedWidgetConfig();
        $translations = $this->translations($locale);
        $config['welcome_message'] = filled($bot->welcome_message) ? $bot->welcome_message : Bot::DEFAULT_WELCOME_MESSAGE;
        $config['fallback_message'] = $bot->fallback_message;
        $defaults = [
            ...WidgetConfig::DEFAULTS,
            'welcome_message' => Bot::DEFAULT_WELCOME_MESSAGE,
            'fallback_message' => Bot::DEFAULT_FALLBACK_MESSAGE,
        ];
        foreach (WidgetConfig::LOCALIZED_FIELDS as $field) {
            if ($field !== 'suggested_prompts' && ($config[$field] ?? null) === $defaults[$field]) {
                $config[$field] = $translations[$defaults[$field]] ?? $defaults[$field];
            }
        }
        $config['suggested_prompts'] = array_map(
            fn (string $prompt): string => in_array($prompt, WidgetConfig::DEFAULT_SUGGESTED_PROMPTS, true)
                ? ($translations[$prompt] ?? $prompt)
                : $prompt,
            $config['suggested_prompts'] ?: WidgetConfig::DEFAULT_SUGGESTED_PROMPTS,
        );
        $overrides = $config['localized_content'][$locale] ?? [];
        foreach (WidgetConfig::LOCALIZED_FIELDS as $field) {
            if (array_key_exists($field, $overrides) && $overrides[$field] !== null) {
                $config[$field] = $overrides[$field];
            }
        }

        return $config;
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
