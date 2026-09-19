<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTenantAdmin() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $settings = $this->user()?->tenant?->aiSetting;
        $providerChanged = $settings && $settings->provider !== $this->string('provider')->toString();

        return [
            'provider' => ['required', Rule::in(['openai', 'openrouter'])],
            'api_key' => [
                Rule::requiredIf(! $settings?->api_key || $providerChanged),
                'nullable',
                'string',
                'max:500',
            ],
            'base_url' => [
                'required',
                'url:http,https',
                'max:255',
                Rule::when(
                    $this->string('provider')->toString() === 'openrouter',
                    [Rule::in(['https://openrouter.ai/api/v1'])],
                ),
            ],
            'chat_model' => ['required', 'string', 'max:255'],
            'embedding_model' => ['required', 'string', 'max:255'],
        ];
    }
}
