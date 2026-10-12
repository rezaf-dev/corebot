<?php

use App\Models\ApplicationSetting;
use App\Models\User;
use App\Support\Localization;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

it('exposes the configured language defaults to guests without creating settings', function () {
    $this->get(route('home'))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('localization.locale', 'en')
            ->where('localization.direction', 'ltr')
            ->where('localization.enabled_locales', ['en', 'fa', 'ar', 'it', 'es', 'tr'])
            ->has('localization.available_languages', 6));

    expect(ApplicationSetting::query()->count())->toBe(0);
});

it('lets only super admins view and save global language settings', function () {
    $user = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($user)->get(route('language-settings.edit'))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/LanguageSettings/Edit', false)
            ->where('languageSettings.default_locale', 'en')
            ->has('availableLanguages', 6));

    $this->actingAs($user)->put(route('language-settings.update'), [
        'enabled_locales' => ['en', 'fa', 'ar'],
        'default_locale' => 'fa',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $settings = ApplicationSetting::query()->sole();
    expect($settings->default_locale)->toBe('fa')
        ->and($settings->enabled_locales)->toBe(['en', 'fa', 'ar']);

    $this->put(route('language-settings.update'), [
        'enabled_locales' => ['en', 'ar'],
        'default_locale' => 'ar',
    ])->assertSessionHasNoErrors();

    expect(ApplicationSetting::query()->count())->toBe(1)
        ->and($settings->fresh()->default_locale)->toBe('ar');
});

it('forbids other roles from reading or modifying global languages', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->get(route('language-settings.edit'))->assertForbidden();
    $this->put(route('language-settings.update'), [
        'enabled_locales' => ['en', 'fa'], 'default_locale' => 'fa',
    ])->assertForbidden();

    expect(ApplicationSetting::query()->count())->toBe(0);
})->with(['tenant_admin', 'agent']);

it('redirects guests away from global language settings', function () {
    $this->get(route('language-settings.edit'))->assertRedirect(route('login'));
    $this->put(route('language-settings.update'), [])->assertRedirect(route('login'));
});

it('rejects invalid language configurations', function (array $payload, array $errors) {
    $this->actingAs(User::factory()->create(['role' => 'super_admin']))
        ->put(route('language-settings.update'), $payload)->assertSessionHasErrors($errors);

    expect(ApplicationSetting::query()->count())->toBe(0);
})->with([
    [['enabled_locales' => [], 'default_locale' => 'en'], ['enabled_locales']],
    [['enabled_locales' => ['en'], 'default_locale' => 'fa'], ['default_locale']],
    [['enabled_locales' => ['xx'], 'default_locale' => 'xx'], ['enabled_locales.0', 'default_locale']],
    [['enabled_locales' => ['en', 'en'], 'default_locale' => 'en'], ['enabled_locales.0']],
]);

it('uses the global default and renders rtl on the initial html response', function () {
    ApplicationSetting::factory()->create(['default_locale' => 'fa']);

    $this->get(route('home'))->assertSuccessful()->assertSee('<html lang="fa" dir="rtl">', false)
        ->assertInertia(fn (Assert $page) => $page->where('localization.locale', 'fa')->where('localization.direction', 'rtl'));
});

it('lets guests switch to enabled languages and preserves their preference', function (string $locale, string $direction) {
    $this->from(route('home'))->post(route('locale.update'), ['locale' => $locale])
        ->assertRedirect(route('home'))->assertSessionHas('locale', $locale);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('localization.locale', $locale)->where('localization.direction', $direction));
})->with(['Persian' => ['fa', 'rtl'], 'Arabic' => ['ar', 'rtl'], 'Italian' => ['it', 'ltr'], 'Spanish' => ['es', 'ltr'], 'Turkish' => ['tr', 'ltr']]);

it('rejects disabled languages and falls back when a saved preference is disabled', function () {
    ApplicationSetting::factory()->create(['enabled_locales' => ['en', 'ar'], 'default_locale' => 'ar']);

    $this->withSession(['locale' => 'fa'])->post(route('locale.update'), ['locale' => 'fa'])
        ->assertSessionHasErrors('locale');

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('localization.locale', 'ar')->where('localization.direction', 'rtl'));
});

it('loads the selected catalog and uses English for missing translation keys', function () {
    File::partialMock()->shouldReceive('exists')->with(lang_path('en.json'))->once()->andReturnTrue();
    File::shouldReceive('exists')->with(lang_path('fa.json'))->once()->andReturnTrue();
    File::shouldReceive('json')->with(lang_path('en.json'))->once()->andReturn([
        'Dashboard' => 'Dashboard', 'Profile' => 'Profile',
    ]);
    File::shouldReceive('json')->with(lang_path('fa.json'))->once()->andReturn(['Dashboard' => 'داشبورد']);

    expect(app(Localization::class)->translations('fa'))->toBe([
        'Dashboard' => 'داشبورد', 'Profile' => 'Profile',
    ]);
});

it('ships complete translation catalogs for every supported language', function (string $locale) {
    $english = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
    $catalog = json_decode(file_get_contents(lang_path($locale.'.json')), true, flags: JSON_THROW_ON_ERROR);
    $expectedKeys = array_keys($english);
    $actualKeys = array_keys($catalog);
    sort($expectedKeys);
    sort($actualKeys);

    expect($actualKeys)->toBe($expectedKeys);
    foreach ($catalog as $key => $translation) {
        expect($translation)->toBeString()->not->toBeEmpty();
        preg_match_all('/:[a-z_]+/', $key, $expectedParameters);
        preg_match_all('/:[a-z_]+/', $translation, $actualParameters);
        sort($expectedParameters[0]);
        sort($actualParameters[0]);
        expect($actualParameters[0])->toBe($expectedParameters[0]);
    }
})->with(['en', 'fa', 'ar', 'it', 'es', 'tr']);

it('localizes framework authentication password and validation messages', function (string $locale) {
    app()->setLocale($locale);

    expect(__('auth.failed'))->not->toBe(__('auth.failed', locale: 'en'))
        ->and(__('passwords.sent'))->not->toBe(__('passwords.sent', locale: 'en'))
        ->and(__('validation.required', ['attribute' => 'email']))->not->toBe(__('validation.required', ['attribute' => 'email'], 'en'));

    $english = require lang_path('en/validation.php');
    $catalog = require lang_path($locale.'/validation.php');
    expect(array_diff(array_keys($english), array_keys($catalog)))->toBe([]);
})->with(['fa', 'ar', 'it', 'es', 'tr']);
