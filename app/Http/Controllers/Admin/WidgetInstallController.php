<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Support\TenantAccess;
use App\Support\WidgetConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WidgetInstallController extends Controller
{
    public function __invoke(TenantAccess $access): Response
    {
        $bots = $access->scope(Bot::query(), auth()->user())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'public_key', 'widget_config']);

        return Inertia::render('Widget/Install', [
            'bots' => $bots->map(fn (Bot $bot) => [
                'id' => $bot->id,
                'name' => $bot->name,
                'public_key' => $bot->public_key,
                'widget_config' => $bot->resolvedWidgetConfig(),
            ]),
            'widgetUrl' => url('/widget.js'),
            'defaults' => WidgetConfig::DEFAULTS,
            'positions' => WidgetConfig::positions(),
            'icons' => WidgetConfig::icons(),
        ]);
    }

    public function update(Request $request, Bot $bot, TenantAccess $access): RedirectResponse
    {
        $access->ensureCanAccess(auth()->user(), $bot);

        $data = $request->validate([
            ...WidgetConfig::validationRules(),
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $existingConfig = $bot->resolvedWidgetConfig();
        $config = WidgetConfig::resolve(Arr::except($data, ['avatar', 'avatar_path']));
        $config['avatar_path'] = $existingConfig['avatar_path'];
        $config['avatar_url'] = $existingConfig['avatar_url'];

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->storePublicly("tenants/{$bot->tenant_id}/widget-avatars", 'public');
            $config['avatar_path'] = $path;
            $config['avatar_url'] = Storage::disk('public')->url($path);
        }

        $bot->update(['widget_config' => $config]);

        if ($request->hasFile('avatar') && filled($existingConfig['avatar_path'])) {
            Storage::disk('public')->delete($existingConfig['avatar_path']);
        }

        return back()->with('success', 'Widget appearance saved.');
    }
}
