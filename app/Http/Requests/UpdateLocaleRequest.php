<?php

namespace App\Http\Requests;

use App\Support\Localization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(Localization $localization): array
    {
        return ['locale' => ['required', 'string', Rule::in($localization->settings()['enabled_locales'])]];
    }
}
