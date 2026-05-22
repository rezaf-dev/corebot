<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Support\TenantAccess;
use App\Support\WidgetConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $data = $request->validate(WidgetConfig::validationRules());
        $bot->update(['widget_config' => WidgetConfig::resolve($data)]);

        return back()->with('success', 'Widget appearance saved.');
    }
}
