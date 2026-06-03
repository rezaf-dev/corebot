<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationType;
use App\Models\Bot;
use App\Models\BotIntegration;
use App\Models\ChatConversation;
use App\Models\IntegrationActionLog;
use App\Services\Leads\LeadCaptureService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class IntegrationExecutor
{
    public function __construct(
        private readonly IntegrationUrlGuard $urlGuard,
        private readonly IntegrationUrlBuilder $urlBuilder,
        private readonly LeadCaptureService $leadCapture,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function run(
        Bot $bot,
        ChatConversation $conversation,
        BotIntegration $integration,
        array $arguments,
    ): string {
        $log = IntegrationActionLog::create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'bot_integration_id' => $integration->id,
            'conversation_id' => $conversation->id,
            'tool_name' => $integration->toolName(),
            'request_payload' => $this->redactPayload($arguments),
        ]);

        try {
            $result = match ($integration->integrationType()) {
                IntegrationType::Webhook => $this->runWebhook($bot, $conversation, $integration, $arguments),
                IntegrationType::CreateLead => $this->runCreateLead($bot, $conversation, $arguments),
                IntegrationType::SendEmail => $this->runSendEmail($bot, $conversation, $integration, $arguments),
                IntegrationType::HttpGet => $this->runHttpGet($integration, $arguments),
            };

            $log->update([
                'response_status' => $result['status'] ?? null,
                'response_body' => isset($result['body']) ? str($result['body'])->limit(4000)->toString() : null,
            ]);

            return json_encode($this->toolResponse($result), JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $log->update(['error' => str($e->getMessage())->limit(1000)->toString()]);

            return json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function toolResponse(array $result): array
    {
        $response = [
            'success' => true,
            'message' => $result['message'] ?? 'Action completed.',
        ];

        if (isset($result['body'])) {
            $body = (string) $result['body'];
            $decoded = json_decode($body, true);

            $response['data'] = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : $body;
        }

        return $response;
    }

    public function test(BotIntegration $integration, Bot $bot): array
    {
        $conversation = $bot->conversations()->make([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'status' => 'open',
            'visitor_name' => 'Test Visitor',
            'visitor_email' => 'test@example.com',
        ]);

        return match ($integration->integrationType()) {
            IntegrationType::Webhook => $this->testWebhook($bot, $conversation, $integration),
            IntegrationType::CreateLead => ['ok' => true, 'message' => 'Create lead integration is configured.'],
            IntegrationType::SendEmail => $this->testSendEmail($bot, $integration),
            IntegrationType::HttpGet => $this->testHttpGet($integration),
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{status: int, body: string, message: string}
     */
    private function runWebhook(
        Bot $bot,
        ChatConversation $conversation,
        BotIntegration $integration,
        array $arguments,
    ): array {
        $config = $integration->resolvedConfig();
        $url = (string) ($config['url'] ?? '');

        if ($url === '') {
            throw new InvalidArgumentException('Webhook URL is not configured.');
        }

        $allowed = $integration->resolvedAllowedDomains();

        if ($allowed === []) {
            $allowed = $this->urlGuard->domainsFromUrl($url);
        }

        $this->urlGuard->assertAllowed($url, $allowed);

        $method = strtoupper((string) ($config['method'] ?? 'POST'));

        if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            throw new InvalidArgumentException('Webhook method must be POST, PUT, or PATCH.');
        }

        $payload = [
            'event' => 'chatbot.integration',
            'integration' => [
                'id' => $integration->id,
                'name' => $integration->name,
                'type' => $integration->type->value,
            ],
            'bot' => ['id' => $bot->id, 'name' => $bot->name],
            'conversation' => [
                'id' => $conversation->id,
                'visitor_name' => $conversation->visitor_name,
                'visitor_email' => $conversation->visitor_email,
                'visitor_phone' => $conversation->visitor_phone,
            ],
            'arguments' => $arguments,
        ];

        $request = Http::timeout(15)
            ->withHeaders($this->httpHeaders($integration));

        $response = match ($method) {
            'POST' => $request->post($url, $payload),
            'PUT' => $request->put($url, $payload),
            'PATCH' => $request->patch($url, $payload),
            default => throw new InvalidArgumentException('Unsupported webhook method.'),
        };

        if (! $response->successful()) {
            throw new InvalidArgumentException('Webhook returned HTTP '.$response->status().'.');
        }

        return [
            'status' => $response->status(),
            'body' => $response->body(),
            'message' => 'Webhook delivered successfully.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{message: string}
     */
    private function runCreateLead(Bot $bot, ChatConversation $conversation, array $arguments): array
    {
        $result = $this->leadCapture->capture($bot, $conversation, $arguments);

        return ['message' => $result['message']];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{message: string}
     */
    private function runSendEmail(
        Bot $bot,
        ChatConversation $conversation,
        BotIntegration $integration,
        array $arguments,
    ): array {
        $config = $integration->resolvedConfig();
        $recipient = (string) ($config['to_email'] ?? $bot->notification_email ?? '');

        if ($recipient === '') {
            throw new InvalidArgumentException('No recipient email configured.');
        }

        $subject = (string) ($arguments['subject'] ?? $config['subject'] ?? 'Chatbot notification');
        $body = (string) ($arguments['body'] ?? $arguments['message'] ?? '');

        if ($body === '') {
            throw new InvalidArgumentException('Email body is required.');
        }

        Mail::raw($body, function ($message) use ($recipient, $subject, $conversation): void {
            $message->to($recipient)
                ->subject($subject)
                ->replyTo($conversation->visitor_email ?: config('mail.from.address'));
        });

        return ['message' => 'Email sent to '.$recipient.'.'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{status: int, body: string, message: string}
     */
    private function runHttpGet(BotIntegration $integration, array $arguments): array
    {
        $config = $integration->resolvedConfig();
        $urlTemplate = (string) ($config['url'] ?? '');

        if ($urlTemplate === '') {
            throw new InvalidArgumentException('HTTP GET URL is not configured.');
        }

        $url = $this->urlBuilder->build($urlTemplate, $arguments);

        $allowed = $integration->resolvedAllowedDomains();

        if ($allowed === []) {
            $allowed = $this->urlBuilder->domainsForAllowlist($urlTemplate);
        }

        $this->urlGuard->assertAllowed($url, $allowed);

        $query = is_array($arguments['query'] ?? null) ? $arguments['query'] : [];

        $response = Http::timeout(15)
            ->withHeaders($this->httpHeaders($integration))
            ->get($url, $query);

        if (! $response->successful()) {
            throw new InvalidArgumentException('HTTP GET returned '.$response->status().'.');
        }

        return [
            'status' => $response->status(),
            'body' => str($response->body())->limit(8000)->toString(),
            'message' => 'HTTP GET completed.',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function testWebhook(Bot $bot, ChatConversation $conversation, BotIntegration $integration): array
    {
        $this->runWebhook($bot, $conversation, $integration, ['test' => true]);

        return ['ok' => true, 'message' => 'Webhook test request succeeded.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function testSendEmail(Bot $bot, BotIntegration $integration): array
    {
        $config = $integration->resolvedConfig();
        $recipient = (string) ($config['to_email'] ?? $bot->notification_email ?? '');

        if ($recipient === '') {
            throw new InvalidArgumentException('No recipient email configured.');
        }

        return ['ok' => true, 'message' => 'Send email integration is configured for '.$recipient.'.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function testHttpGet(BotIntegration $integration): array
    {
        $this->runHttpGet($integration, ['query' => ['test' => '1']]);

        return ['ok' => true, 'message' => 'HTTP GET test request succeeded.'];
    }

    /**
     * @return array<string, string>
     */
    private function httpHeaders(BotIntegration $integration): array
    {
        $headers = [];
        $credentials = $integration->resolvedCredentials();
        $token = (string) ($credentials['bearer_token'] ?? $credentials['api_token'] ?? '');

        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        $configHeaders = $integration->resolvedConfig()['headers'] ?? [];

        if (is_array($configHeaders)) {
            foreach ($configHeaders as $key => $value) {
                if (is_string($key) && (is_string($value) || is_numeric($value))) {
                    $headers[$key] = (string) $value;
                }
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        $redacted = $payload;

        foreach (['password', 'token', 'api_key', 'secret'] as $key) {
            if (array_key_exists($key, $redacted)) {
                $redacted[$key] = '[redacted]';
            }
        }

        return $redacted;
    }
}
