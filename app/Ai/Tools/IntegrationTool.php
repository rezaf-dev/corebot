<?php

namespace App\Ai\Tools;

use App\Enums\IntegrationType;
use App\Models\Bot;
use App\Models\BotIntegration;
use App\Models\ChatConversation;
use App\Services\Integrations\IntegrationExecutor;
use App\Services\Integrations\IntegrationUrlBuilder;
use App\Support\BotContactConfig;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class IntegrationTool implements Tool
{
    public function __construct(
        private readonly BotIntegration $integration,
        private readonly Bot $bot,
        private readonly ChatConversation $conversation,
        private readonly IntegrationExecutor $executor,
    ) {}

    public function name(): string
    {
        return $this->integration->toolName();
    }

    public function description(): Stringable|string
    {
        return $this->integration->description ?: $this->integration->name;
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->executor->run(
            $this->bot,
            $this->conversation,
            $this->integration,
            $request->toArray(),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return match ($this->integration->integrationType()) {
            IntegrationType::CreateLead => $this->createLeadSchema($schema),
            IntegrationType::SendEmail => [
                'subject' => $schema->string()->description('Email subject line.'),
                'body' => $schema->string()->description('Email body text.')->required(),
            ],
            IntegrationType::HttpGet => $this->httpGetSchema($schema),
            IntegrationType::Webhook => [
                'payload' => $schema->object()->description('Optional extra fields to include in the webhook payload.'),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function createLeadSchema(JsonSchema $schema): array
    {
        $fields = [];

        if (in_array('name', BotContactConfig::fields($this->bot), true)) {
            $fields['visitor_name'] = $schema->string()->description('Visitor full name.');
        }

        if (in_array('email', BotContactConfig::fields($this->bot), true)) {
            $fields['visitor_email'] = $schema->string()->description('Visitor email address.');
        }

        if (in_array('phone', BotContactConfig::fields($this->bot), true)) {
            $fields['visitor_phone'] = $schema->string()->description('Visitor phone number.');
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function httpGetSchema(JsonSchema $schema): array
    {
        $fields = [];
        $urlBuilder = app(IntegrationUrlBuilder::class);

        foreach ($urlBuilder->pathParameterNames((string) ($this->integration->resolvedConfig()['url'] ?? '')) as $name) {
            $fields[$name] = $schema->string()
                ->description(str_replace('_', ' ', $name).' for the API request.')
                ->required();
        }

        $fields['query'] = $schema->object()
            ->description('Optional query string parameters as key-value pairs.');

        return $fields;
    }
}
