<?php

namespace App\Http\Requests\Admin;

use App\Enums\IntegrationType;
use App\Services\Integrations\IntegrationUrlGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBotIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'type' => ['required', Rule::in(IntegrationType::values())],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['boolean'],
            'allowed_domains' => ['nullable', 'string'],
            'config' => ['nullable', 'array'],
            'config.url' => [Rule::requiredIf(in_array($type, ['webhook', 'http_get'], true)), 'nullable', 'url', 'max:2000'],
            'config.method' => ['nullable', Rule::in(['POST', 'PUT', 'PATCH'])],
            'config.to_email' => ['nullable', 'email', 'max:255'],
            'config.subject' => ['nullable', 'string', 'max:255'],
            'config.headers' => ['nullable', 'array'],
            'credentials' => ['nullable', 'array'],
            'credentials.bearer_token' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function integrationAttributes(): array
    {
        $allowedDomains = collect(preg_split('/[\s,]+/', $this->input('allowed_domains', ''), flags: PREG_SPLIT_NO_EMPTY))
            ->map(fn ($domain) => strtolower(trim($domain)))
            ->filter()
            ->values()
            ->all();

        $config = $this->input('config', []);

        if (in_array($this->input('type'), ['webhook', 'http_get'], true) && empty($allowedDomains) && ! empty($config['url'])) {
            $allowedDomains = app(IntegrationUrlGuard::class)->domainsFromUrl((string) $config['url']);
        }

        return [
            'type' => $this->input('type'),
            'name' => $this->input('name'),
            'description' => $this->input('description'),
            'enabled' => $this->boolean('enabled', true),
            'config' => $config,
            'credentials' => array_filter($this->input('credentials', [])),
            'allowed_domains' => $allowedDomains,
        ];
    }
}
