<?php

namespace App\Http\Requests\Admin;

use App\Support\Localization;
use App\Support\TenantAccess;
use App\Support\WidgetConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWidgetConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(TenantAccess::class)->ensureCanAccess($this->user(), $this->route('bot'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['enabled_locales', 'localized_content'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->merge([$field => $decoded]);
                }
            }
        }

        $localizedContent = $this->input('localized_content');
        if (is_array($localizedContent)) {
            foreach ($localizedContent as $locale => $content) {
                if (! is_array($content)) {
                    continue;
                }
                foreach ($content as $field => $value) {
                    if ($field === 'suggested_prompts' && is_array($value)) {
                        $value = array_values(array_filter(array_map(fn ($prompt) => is_string($prompt) ? trim($prompt) : $prompt, $value), fn ($prompt) => $prompt !== null && $prompt !== ''));
                    } elseif (is_string($value)) {
                        $value = trim($value);
                    }
                    if ($value === null || $value === '' || ($field === 'suggested_prompts' && $value === [])) {
                        unset($localizedContent[$locale][$field]);
                    } else {
                        $localizedContent[$locale][$field] = $value;
                    }
                }
            }
            $this->merge(['localized_content' => $localizedContent]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $enabled = app(Localization::class)->settings()['enabled_locales'];

        return [
            ...WidgetConfig::validationRules(),
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'default_locale' => ['sometimes', 'nullable', 'string', Rule::in($enabled)],
            'enabled_locales' => ['sometimes', 'array', 'list'],
            'enabled_locales.*' => ['required', 'string', 'distinct', Rule::in($enabled)],
            'localized_content' => ['sometimes', 'array:'.implode(',', array_keys(config('localization.languages')))],
            'localized_content.*' => ['array:'.implode(',', WidgetConfig::LOCALIZED_FIELDS)],
            'localized_content.*.title' => ['nullable', 'string', 'max:80'],
            'localized_content.*.subtitle' => ['nullable', 'string', 'max:120'],
            'localized_content.*.launcher_label' => ['nullable', 'string', 'max:80'],
            'localized_content.*.send_button_label' => ['nullable', 'string', 'max:24'],
            'localized_content.*.input_placeholder' => ['nullable', 'string', 'max:120'],
            'localized_content.*.welcome_message' => ['nullable', 'string', 'max:2000'],
            'localized_content.*.fallback_message' => ['nullable', 'string', 'max:2000'],
            'localized_content.*.suggested_prompts' => ['sometimes', 'array', 'list', 'max:4'],
            'localized_content.*.suggested_prompts.*' => ['required', 'string', 'max:80'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $existing = $this->route('bot')->resolvedWidgetConfig();
            $requested = $this->input('enabled_locales', $existing['enabled_locales']);
            $global = app(Localization::class)->settings()['enabled_locales'];
            $enabled = is_array($requested) && $requested !== [] ? array_intersect($global, $requested) : $global;
            $default = $this->input('default_locale', $existing['default_locale']);
            if (filled($default) && ! in_array($default, $enabled, true)) {
                $validator->errors()->add('default_locale', 'The default language must be enabled for this widget.');
            }
        }];
    }
}
