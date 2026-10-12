<?php

namespace Database\Factories;

use App\Models\ApplicationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApplicationSetting> */
class ApplicationSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'localization',
            'default_locale' => config('localization.default_locale'),
            'enabled_locales' => config('localization.enabled_locales'),
        ];
    }
}
