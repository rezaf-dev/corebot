<?php

namespace App\Http\Controllers;

use App\Mail\LeadCapturedMail;
use App\Models\Bot;
use App\Models\ChatConversation;
use App\Services\GeoIp\MaxMindGeoIpService;
use App\Services\Rag\ChatAnswerService;
use App\Support\BotContactConfig;
use Generator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

        $conversation = ChatConversation::create([
            'tenant_id' => $bot->tenant_id,
            'bot_id' => $bot->id,
            'visitor_id' => $data['visitor_id'] ?? null,
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
        [$bot, $conversation, $message] = $this->messageContext($request);

        return response()->json($answerService->answer($bot->load('tenant.aiSetting'), $conversation, $message));
    }

    public function stream(Request $request, ChatAnswerService $answerService): StreamedResponse
    {
        [$bot, $conversation, $message] = $this->messageContext($request);

        return response()->stream(
            function () use ($answerService, $bot, $conversation, $message): Generator {
                yield from $answerService->stream($bot->load('tenant.aiSetting'), $conversation, $message);
            },
            headers: [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bot_public_key' => ['required', 'string'],
            'conversation_id' => ['required', 'integer'],
            'visitor_name' => ['nullable', 'string', 'max:255'],
            'visitor_email' => ['nullable', 'email', 'max:255'],
            'visitor_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $bot = $this->activeBot($data['bot_public_key']);
        $conversation = ChatConversation::query()
            ->where('id', $data['conversation_id'])
            ->where('tenant_id', $bot->tenant_id)
            ->where('bot_id', $bot->id)
            ->firstOrFail();

        $this->validateContactFields($bot, $data);

        $conversation->update(collect($data)->only(['visitor_name', 'visitor_email', 'visitor_phone'])->all());

        $this->notifyLeadCaptured($bot, $conversation->fresh());

        return response()->json(['ok' => true]);
    }

    private function activeBot(string $key): Bot
    {
        $bot = Bot::query()->with('tenant.aiSetting')->where('public_key', $key)->firstOrFail();

        abort_unless($bot->isActive() && $bot->tenant->isActive(), 404);

        return $bot;
    }

    private function messageContext(Request $request): array
    {
        $data = $request->validate([
            'bot_public_key' => ['required', 'string'],
            'conversation_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $bot = $this->activeBot($data['bot_public_key']);
        $conversation = ChatConversation::query()
            ->where('id', $data['conversation_id'])
            ->where('tenant_id', $bot->tenant_id)
            ->where('bot_id', $bot->id)
            ->firstOrFail();

        return [$bot, $conversation, $data['message']];
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateContactFields(Bot $bot, array $data): void
    {
        $rules = [];

        foreach (BotContactConfig::required($bot) as $field) {
            $column = BotContactConfig::FIELD_MAP[$field];
            $rules[$column] = ['required', 'string', 'max:255'];
        }

        if ($rules !== []) {
            validator($data, $rules)->validate();
        }

        $hasValue = collect(BotContactConfig::fields($bot))
            ->contains(fn (string $field): bool => filled($data[BotContactConfig::FIELD_MAP[$field]] ?? null));

        abort_unless($hasValue, 422, 'At least one contact field is required.');
    }

    private function notifyLeadCaptured(Bot $bot, ChatConversation $conversation): void
    {
        if ($conversation->contact_notified_at !== null) {
            return;
        }

        $recipient = $bot->notification_email;

        if (! is_string($recipient) || $recipient === '') {
            return;
        }

        $lastUserMessage = $conversation->messages()
            ->where('role', 'user')
            ->latest()
            ->value('content');

        Mail::to($recipient)->queue(new LeadCapturedMail($bot, $conversation, $lastUserMessage));

        $conversation->update(['contact_notified_at' => now()]);
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
