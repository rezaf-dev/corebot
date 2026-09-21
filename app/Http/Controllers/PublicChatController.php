<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Services\GeoIp\MaxMindGeoIpService;
use App\Services\Leads\LeadCaptureService;
use App\Services\Rag\ChatAnswerService;
use App\Support\BotContactConfig;
use Generator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicChatController extends Controller
{
    public function start(Request $request, MaxMindGeoIpService $geoIp): JsonResponse
    {
        $data = $request->validate([
            'bot_public_key' => ['required', 'string'],
            'visitor_id' => ['nullable', 'string', 'max:100'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'referrer_url' => ['nullable', 'url', 'max:2000'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
        ]);

        $bot = $this->activeBot($data['bot_public_key']);
        $this->ensureAllowedDomain($bot, $data['source_url'] ?? $request->headers->get('referer'));

        $priorContact = $this->priorVisitorContact($bot, $data['visitor_id'] ?? null);
        $geo = $geoIp->lookup($request->ip());

        $conversationToken = Str::random(64);

        $conversation = ChatConversation::create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'visitor_id' => $data['visitor_id'] ?? null,
            'public_session_token' => hash('sha256', $conversationToken),
            'visitor_name' => $priorContact['visitor_name'] ?? null,
            'visitor_email' => $priorContact['visitor_email'] ?? null,
            'visitor_phone' => $priorContact['visitor_phone'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer_url' => $data['referrer_url'] ?? null,
            'country_code' => $geo['country_code'],
            'country_name' => $geo['country_name'],
            'city' => $geo['city'],
            'timezone' => $data['timezone'] ?? null,
            'language' => $this->preferredLanguage($request),
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'status' => 'open',
        ]);

        return response()->json(array_merge(
            $this->contactConfigPayload($bot, $conversation),
            [
                'conversation_id' => $conversation->id,
                'conversation_token' => $conversationToken,
                'welcome_message' => $bot->welcome_message,
                'collect_contact_on_start' => (bool) $bot->collect_contact_on_start,
                'widget' => $bot->resolvedWidgetConfig(),
            ],
        ));
    }

    public function widgetConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bot_public_key' => ['required', 'string'],
        ]);

        $bot = $this->activeBot($data['bot_public_key']);

        return response()->json([
            'widget' => array_merge($bot->resolvedWidgetConfig(), [
                'welcome_message' => $bot->welcome_message,
            ]),
            'contact_fields' => BotContactConfig::fields($bot),
            'contact_required' => BotContactConfig::required($bot),
            'collect_contact_on_start' => (bool) $bot->collect_contact_on_start,
        ]);
    }

    public function message(Request $request, ChatAnswerService $answerService): JsonResponse
    {
        [$bot, $conversation, $message, $pageContext] = $this->messageContext($request);

        return response()->json($answerService->answer($bot->loadMissing('integrations'), $conversation, $message, $pageContext));
    }

    public function stream(Request $request, ChatAnswerService $answerService): StreamedResponse
    {
        [$bot, $conversation, $message, $pageContext] = $this->messageContext($request);

        return response()->stream(
            function () use ($answerService, $bot, $conversation, $message, $pageContext): Generator {
                yield from $answerService->stream($bot->loadMissing('integrations'), $conversation, $message, $pageContext);
            },
            headers: [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-transform',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    public function contact(Request $request, LeadCaptureService $leadCapture): JsonResponse
    {
        $data = $request->validate([
            'bot_public_key' => ['required', 'string'],
            'conversation_id' => ['required', 'integer'],
            'conversation_token' => ['required', 'string', 'size:64'],
            'visitor_name' => ['nullable', 'string', 'max:255'],
            'visitor_email' => ['nullable', 'email', 'max:255'],
            'visitor_phone' => ['nullable', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        $bot = $this->activeBot($data['bot_public_key']);
        $conversation = $this->publicConversation($bot, $data['conversation_id'], $data['conversation_token']);

        $leadCapture->capture($bot, $conversation, $data);

        if (isset($data['country_code'])) {
            $conversation->update([
                'country_code' => strtoupper($data['country_code']),
                'country_name' => null,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function manualMessages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bot_public_key' => ['required', 'string'],
            'conversation_id' => ['required', 'integer'],
            'conversation_token' => ['required', 'string', 'size:64'],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $bot = $this->activeBot($data['bot_public_key']);
        $conversation = $this->publicConversation($bot, $data['conversation_id'], $data['conversation_token']);

        return response()->json([
            'messages' => $conversation->messages()
                ->where('role', 'admin')
                ->where('id', '>', $data['after_id'] ?? 0)
                ->orderBy('id')
                ->get(['id', 'content', 'created_at']),
        ]);
    }

    private function activeBot(string $key, bool $withAiSettings = false): Bot
    {
        $tenantRelation = $withAiSettings ? 'tenant.aiSetting' : 'tenant';
        $bot = Bot::query()->with($tenantRelation)->where('public_key', $key)->firstOrFail();

        abort_unless($bot->isActive() && $bot->tenant->isActive(), 404);

        return $bot;
    }

    private function messageContext(Request $request): array
    {
        $data = $request->validate([
            'bot_public_key' => ['required', 'string'],
            'conversation_id' => ['required', 'integer'],
            'conversation_token' => ['required', 'string', 'size:64'],
            'message' => ['required', 'string', 'max:2000'],
            'page.url' => ['nullable', 'url:http,https', 'max:2000'],
            'page.title' => ['nullable', 'string', 'max:255'],
        ]);

        $bot = $this->activeBot($data['bot_public_key'], withAiSettings: true);
        $conversation = $this->publicConversation($bot, $data['conversation_id'], $data['conversation_token']);

        $pageContext = array_filter([
            'url' => $data['page']['url'] ?? null,
            'title' => $data['page']['title'] ?? null,
        ]);

        if (isset($pageContext['url'])) {
            $this->ensureAllowedDomain($bot, $pageContext['url']);
            $conversation->update(['source_url' => $pageContext['url']]);
        }

        return [$bot, $conversation, $data['message'], $pageContext];
    }

    private function ensureAllowedDomain(Bot $bot, ?string $sourceUrl): void
    {
        $domains = $bot->allowed_domains ?? [];

        if ($domains === []) {
            return;
        }

        $host = strtolower(parse_url((string) $sourceUrl, PHP_URL_HOST) ?: '');
        abort_unless($host && in_array($host, $domains, true), 403);
    }

    private function publicConversation(Bot $bot, int $conversationId, string $conversationToken): ChatConversation
    {
        return ChatConversation::query()
            ->whereKey($conversationId)
            ->where('tenant_id', $bot->tenant_id)
            ->where('bot_id', $bot->id)
            ->where('public_session_token', hash('sha256', $conversationToken))
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function contactConfigPayload(Bot $bot, ChatConversation $conversation): array
    {
        return [
            'contact_fields' => BotContactConfig::fields($bot),
            'contact_required' => BotContactConfig::required($bot),
            'has_contact' => BotContactConfig::hasCompleteContact($bot, $conversation),
            'contact' => BotContactConfig::widgetContact($conversation),
            'country_code' => $conversation->country_code,
        ];
    }

    /**
     * @return array{visitor_name?: string, visitor_email?: string, visitor_phone?: string}
     */
    private function priorVisitorContact(Bot $bot, ?string $visitorId): array
    {
        if ($visitorId === null || $visitorId === '') {
            return [];
        }

        $prior = ChatConversation::query()
            ->where('bot_id', $bot->id)
            ->where('visitor_id', $visitorId)
            ->where(function ($query): void {
                $query->whereNotNull('visitor_name')
                    ->orWhereNotNull('visitor_email')
                    ->orWhereNotNull('visitor_phone');
            })
            ->latest()
            ->first();

        if ($prior === null) {
            return [];
        }

        return BotContactConfig::contactPayload($prior);
    }

    private function preferredLanguage(Request $request): ?string
    {
        $header = $request->header('Accept-Language');

        if (! is_string($header) || $header === '') {
            return null;
        }

        $primary = explode(',', $header)[0] ?? '';

        return substr(trim($primary), 0, 32) ?: null;
    }
}
