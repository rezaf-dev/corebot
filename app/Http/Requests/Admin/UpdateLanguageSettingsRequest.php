<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLanguageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $languages = array_keys(config('localization.languages'));
        $enabled = $this->input('enabled_locales');

        return [
            'enabled_locales' => ['required', 'array', 'list', 'min:1'],
            'enabled_locales.*' => ['required', 'string', 'distinct', Rule::in($languages)],
            'default_locale' => ['required', 'string', Rule::in($languages), Rule::in(is_array($enabled) ? $enabled : [])],
        ];
    }
}
