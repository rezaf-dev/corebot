<?php

namespace App\Http\Controllers;

use App\Support\WidgetAsset;
use Illuminate\View\View;

class DemoController extends Controller
{
    public function __invoke(WidgetAsset $widgetAsset): View
    {
        return view('demo', [
            'appName' => config('app.name'),
            'botPublicKey' => config('corebot.demo_bot_public_key'),
            'widgetUrl' => filled(config('corebot.demo_bot_public_key'))
                ? $widgetAsset->urlForKey(config('corebot.demo_bot_public_key'))
                : null,
        ]);
    }
}
