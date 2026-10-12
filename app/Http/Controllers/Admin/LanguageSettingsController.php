<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLanguageSettingsRequest;
use App\Models\ApplicationSetting;
use App\Support\Localization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LanguageSettingsController extends Controller
{
    public function edit(Request $request, Localization $localization): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return Inertia::render('Admin/LanguageSettings/Edit', [
            'languageSettings' => $localization->settings(),
            'availableLanguages' => $localization->availableLanguages(),
        ]);
    }

    public function update(UpdateLanguageSettingsRequest $request): RedirectResponse
    {
        ApplicationSetting::query()->updateOrCreate(['key' => 'localization'], $request->validated());

        return back()->with('success', __('Language settings saved.'));
    }
}
