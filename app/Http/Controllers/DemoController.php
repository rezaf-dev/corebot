<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DemoController extends Controller
{
    public function __invoke(): View
    {
        return view('demo', [
            'appName' => config('app.name'),
            'botPublicKey' => config('corebot.demo_bot_public_key'),
            'widgetUrl' => url('/widget.js'),
        ]);
    }
}
