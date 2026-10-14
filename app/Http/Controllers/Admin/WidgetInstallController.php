<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWidgetConfigRequest;
use App\Models\Bot;
use App\Support\Localization;
use App\Support\TenantAccess;
use App\Support\WidgetAsset;
use App\Support\WidgetConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WidgetInstallController extends Controller
{
    public function preview(Bot $bot, WidgetAsset $widgetAsset): Response
    {
        abort_unless($bot->isActive(), 404);

        return Inertia::render('Widget/Preview', [
            'bot' => $bot->only(['name']),
            'widget_url' => $widgetAsset->url($bot),
        ]);
    }

    public function __invoke(TenantAccess $access, WidgetAsset $widgetAsset, Localization $localization): Response
    {
        $bots = $access->scope(Bot::query(), auth()->user())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'public_key', 'widget_config', 'welcome_message', 'fallback_message']);

        $languageSettings = $localization->settings();
        $languages = array_values(array_filter($localization->availableLanguages(), fn (array $language): bool => in_array($language['code'], $languageSettings['enabled_locales'], true)));
        $previewKeys = [
            WidgetConfig::DEFAULTS['title'], WidgetConfig::DEFAULTS['subtitle'],
            WidgetConfig::DEFAULTS['launcher_label'], WidgetConfig::DEFAULTS['input_placeholder'],
            WidgetConfig::DEFAULTS['send_button_label'], Bot::DEFAULT_WELCOME_MESSAGE, Bot::DEFAULT_FALLBACK_MESSAGE,
            ...WidgetConfig::DEFAULT_SUGGESTED_PROMPTS,
            'Need help?', 'Chat with our team', 'Questions? Ask us', 'Support is online', "Let's talk",
        ];

        return Inertia::render('Widget/Install', [
            'bots' => $bots->map(fn (Bot $bot) => [
                'id' => $bot->id,
                'name' => $bot->name,
                'public_key' => $bot->public_key,
                'widget_url' => $widgetAsset->url($bot),
                'widget_config' => $bot->resolvedWidgetConfig(),
                'welcome_message' => $bot->welcome_message,
                'fallback_message' => $bot->fallback_message,
                'localization' => Arr::except($localization->widgetLocalization(bot: $bot), ['translations']),
            ]),
            'defaults' => WidgetConfig::DEFAULTS,
            'language_settings' => $languageSettings,
            'languages' => $languages,
            'preview_translations' => collect($languages)->mapWithKeys(fn (array $language): array => [
                $language['code'] => Arr::only($localization->translations($language['code']), $previewKeys),
            ])->all(),
            'positions' => WidgetConfig::positions(),
            'icons' => WidgetConfig::icons(),
        ]);
    }

    public function update(UpdateWidgetConfigRequest $request, Bot $bot, TenantAccess $access): RedirectResponse
    {
        $access->ensureCanAccess(auth()->user(), $bot);

        $data = $request->validated();
        $existingConfig = $bot->resolvedWidgetConfig();
        $config = WidgetConfig::resolve(array_replace($existingConfig, Arr::except($data, ['avatar', 'avatar_path', 'remove_avatar'])));
        $config['default_locale'] = $data['default_locale'] ?? ($request->exists('default_locale') ? '' : $existingConfig['default_locale']);
        $config['avatar_path'] = $existingConfig['avatar_path'];
        $config['avatar_url'] = $existingConfig['avatar_url'];

        if ($request->boolean('remove_avatar')) {
            $config['avatar_path'] = '';
            $config['avatar_url'] = '';
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->storePublicly("tenants/{$bot->tenant_id}/widget-avatars", 'public');
            $config['avatar_path'] = $path;
            $config['avatar_url'] = Storage::disk('public')->url($path);
        }

        $bot->update(['widget_config' => $config]);

        if (($request->hasFile('avatar') || $request->boolean('remove_avatar')) && filled($existingConfig['avatar_path'])) {
            Storage::disk('public')->delete($existingConfig['avatar_path']);
        }

        return back()->with('success', 'Widget appearance saved.');
    }
}
