<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Support\TenantAccess;
use Inertia\Inertia;
use Inertia\Response;

class WidgetInstallController extends Controller
{
    public function __invoke(TenantAccess $access): Response
    {
        return Inertia::render('Widget/Install', [
            'bots' => $access->scope(Bot::query(), auth()->user())->where('status', 'active')->orderBy('name')->get(['id', 'name', 'public_key']),
            'widgetUrl' => url('/widget.js'),
        ]);
    }
}
