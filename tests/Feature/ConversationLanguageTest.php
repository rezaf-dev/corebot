<?php

use App\Ai\Agents\TenantChatAgent;
use App\Models\ApplicationSetting;
use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Services\AI\OpenAIService;
use App\Services\Rag\ChatAnswerService;
use App\Services\Rag\SearchResult;
use App\Services\Rag\SemanticSearchService;
use App\Support\Localization;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;

beforeEach(function () {
    $this->bot = Bot::factory()->create();
    $this->conversation = ChatConversation::factory()->create([
        'bot_id' => $this->bot->id,
        'language' => 'en',
        'public_session_token' => hash('sha256', $this->token = Str::random(64)),
    ]);
});

it('preserves language for city names and short answers and switches for clear language changes', function (bool $streaming) {
    config(['rag.history_message_limit' => 0]);
    ApplicationSetting::factory()->create(['enabled_locales' => ['en'], 'default_locale' => 'en']);
    $this->bot->update(['widget_config' => ['enabled_locales' => ['en'], 'default_locale' => 'en']]);
    $ai = $this->mock(OpenAIService::class);
    $this->mock(SemanticSearchService::class)->shouldReceive('searchWithMeta')->times(12)
        ->andReturn(new SearchResult(collect(), confident: false));

    foreach ([
        ['Guten Tag, können Sie mir helfen?', null, 'de', 'de'],
        ['OK', 'de', null, 'de'],
        ['Istanbul', 'de', null, 'de'],
        ['تهران', 'de', null, 'de'],
        ['yes', 'de', null, 'de'],
        ['merci', 'de', null, 'de'],
        ['Translate this into English: Guten Tag', 'de', null, 'de'],
        ['Could you explain how I can change my account details?', 'de', 'en', 'en'],
        ['Berlin', 'en', null, 'en'],
        ['Please reply in Japanese from now on.', 'en', 'ja', 'ja'],
        ['Thanks', 'ja', null, 'ja'],
        ['Tokyo', 'ja', null, 'ja'],
    ] as [$message, $current, $detected, $expected]) {
        $ai->shouldReceive('detectConversationLanguage')->once()
            ->withArgs(fn ($tenant, $text, $language) => $text === $message && $language === $current)
            ->andReturn($detected);
        $completion = $ai->shouldReceive($streaming ? 'streamChatCompletion' : 'createChatCompletion')->once()
            ->withArgs(function ($tenant, array $messages) use ($expected): bool {
                expect($messages[0]['content'])->toContain("({$expected}). Respond in this language", 'Use the conversation language selected above for this turn');

                return true;
            });
        if ($streaming) {
            $completion->andReturn(new StreamableAgentResponse('test', function () {
                yield new TextDelta('1', '1', 'Reply', time());
            }, new Meta('openai', 'test')));
        } else {
            $completion->andReturn(['choices' => [['message' => ['content' => 'Reply']]]]);
        }

        $response = $this->postJson('/api/public/chat/message'.($streaming ? '/stream' : ''), [
            'bot_public_key' => $this->bot->public_key,
            'conversation_id' => $this->conversation->id,
            'conversation_token' => $this->token,
            'locale' => 'en',
            'message' => $message,
        ]);
        $response->assertSuccessful();
        if ($streaming) {
            $response->streamedContent();
        }
        expect($this->conversation->fresh()->language)->toBe($expected)
            ->and($this->conversation->fresh()->language_detected)->toBeTrue();
    }
})->with([false, true]);

it('retries ambiguous initial detection and localizes fallback responses after detection', function () {
    $ai = $this->mock(OpenAIService::class);
    $ai->shouldReceive('detectConversationLanguage')->once()->withArgs(fn ($tenant, $text, $language) => $text === '123' && $language === null)->andReturnNull();
    $ai->shouldReceive('detectConversationLanguage')->once()->withArgs(fn ($tenant, $text, $language) => $language === null)->andReturn('fa');
    $search = $this->mock(SemanticSearchService::class);
    $search->shouldReceive('searchWithMeta')->once()->andReturn(new SearchResult(collect(), confident: false));
    $search->shouldReceive('searchWithMeta')->once()->andThrow(new RuntimeException('Unavailable'));
    $ai->shouldReceive('createChatCompletion')->once()->andReturn(['choices' => [['message' => ['content' => 'Reply']]]]);

    app(ChatAnswerService::class)->answer($this->bot, $this->conversation, '123');
    expect($this->conversation->fresh()->language_detected)->toBeFalse();
    $response = app(ChatAnswerService::class)->answer($this->bot, $this->conversation->fresh(), 'سلام، کمک می‌خواهم');
    expect($this->conversation->fresh()->language)->toBe('fa')
        ->and($response['message'])->toBe(app(Localization::class)->widgetConfig($this->bot, 'fa')['fallback_message']);
});

it('preserves language when detection fails or returns a malformed language tag', function (bool $fails) {
    $this->conversation->update(['language' => 'fa', 'language_detected' => true]);
    $detection = $this->mock(OpenAIService::class)->shouldReceive('detectConversationLanguage')->once();
    if ($fails) {
        $detection->andThrow(new RuntimeException('Unavailable'));
    } else {
        $detection->andReturn('../invalid');
    }
    $this->mock(SemanticSearchService::class)->shouldReceive('searchWithMeta')->once()->andThrow(new RuntimeException('Unavailable'));
    app(ChatAnswerService::class)->answer($this->bot, $this->conversation, 'Hello');
    expect($this->conversation->fresh()->language)->toBe('fa')
        ->and($this->conversation->fresh()->language_detected)->toBeTrue();
})->with([false, true]);

it('keeps conversation language independent of widget UI locale selections', function (string $locale, bool $explicit, string $expected) {
    ApplicationSetting::factory()->create(['enabled_locales' => ['en', 'fa'], 'default_locale' => 'en']);
    $this->conversation->update(['language' => 'fa', 'language_detected' => true]);
    $this->mock(ChatAnswerService::class)->shouldReceive('answer')->once()
        ->withArgs(fn ($bot, $conversation) => $conversation->language === $expected)
        ->andReturn(['message' => 'Reply']);
    $this->postJson('/api/public/chat/message', [
        'bot_public_key' => $this->bot->public_key, 'conversation_id' => $this->conversation->id,
        'conversation_token' => $this->token, 'message' => 'Hello', 'locale' => $locale, 'language_changed' => $explicit,
    ])->assertSuccessful();
    expect($this->conversation->fresh()->language)->toBe($expected);
})->with([
    'passive locale' => ['en', false, 'fa'],
    'explicit selector' => ['en', true, 'fa'],
    'disabled selector' => ['tr', true, 'fa'],
]);

it('validates classifier outputs and sends explicit language persistence instructions', function (string $output, ?string $expected) {
    TenantChatAgent::fake([$output])->preventStrayPrompts();
    $tenant = Tenant::factory()->create();
    $tenant->aiSetting()->create([
        'provider' => 'openai', 'api_key_encrypted' => Crypt::encryptString('sk-test'), 'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini', 'embedding_model' => 'text-embedding-3-small', 'is_active' => true,
    ]);
    expect(app(OpenAIService::class)->detectConversationLanguage($tenant, 'Please speak Turkish', 'fa'))->toBe($expected);
    TenantChatAgent::assertPrompted(function ($prompt): bool {
        expect($prompt->agent->instructions())->toContain('established conversation language is fa', 'writes a clear, substantive message of their own in another language', 'city or place name', 'Short acknowledgements', 'Explicit language requests take priority even when short', 'After switching, preserve the new language', 'translation tasks', 'negated requests');

        return $prompt->prompt === 'Please speak Turkish';
    });
})->with([
    'valid code' => [' tr ', 'tr'], 'no change' => ['keep', null], 'outside UI catalogs' => ['de', 'de'], 'script variant' => ['zh-hant', 'zh-hant'], 'malformed tag' => ['../../en', null],
    'malformed output' => ['The language is Turkish', null],
]);

it('keeps the UI locale provisional regardless of widget selection at conversation start', function (bool $explicit, string $locale, bool $locked) {
    $response = $this->postJson('/api/public/chat/start', [
        'bot_public_key' => $this->bot->public_key, 'locale' => $locale, 'language_changed' => $explicit,
    ])->assertSuccessful();

    expect(ChatConversation::findOrFail($response->json('conversation_id'))->language_detected)->toBe($locked);
})->with([
    'initial locale' => [false, 'fa', false],
    'selected locale' => [true, 'fa', false],
    'invalid locale' => [true, 'xx', false],
]);

it('translates and caches fallbacks for conversation languages without UI catalogs', function (bool $streaming) {
    ApplicationSetting::factory()->create(['enabled_locales' => ['en'], 'default_locale' => 'en']);
    $ai = $this->mock(OpenAIService::class);
    $ai->shouldReceive('detectConversationLanguage')->twice()->andReturn('ja');
    $ai->shouldReceive('createChatCompletion')->once()->withArgs(function ($tenant, array $messages): bool {
        expect($messages[0]['content'])->toContain('Translate the supplied support message into language ja');

        return true;
    })->andReturn(['choices' => [['message' => ['content' => '申し訳ありませんが、サポートにお問い合わせください。']]]]);
    $this->mock(SemanticSearchService::class)->shouldReceive('searchWithMeta')->twice()->andThrow(new RuntimeException('Retrieval unavailable'));

    foreach (range(1, 2) as $attempt) {
        $conversation = ChatConversation::factory()->create(['bot_id' => $this->bot->id, 'language' => 'en']);
        if ($streaming) {
            $events = implode('', iterator_to_array(app(ChatAnswerService::class)->stream($this->bot, $conversation, 'こんにちは')));
            expect($events)->toContain(json_encode('申し訳ありませんが、サポートにお問い合わせください。'));
        } else {
            $response = app(ChatAnswerService::class)->answer($this->bot, $conversation, 'こんにちは');
            expect($response['message'])->toBe('申し訳ありませんが、サポートにお問い合わせください。');
        }
        expect($conversation->fresh()->language)->toBe('ja');
    }
})->with([false, true]);

it('retains the conversation preference when fallback translation is unavailable', function () {
    $ai = $this->mock(OpenAIService::class);
    $ai->shouldReceive('detectConversationLanguage')->once()->andReturn('de');
    $ai->shouldReceive('createChatCompletion')->once()->andThrow(new RuntimeException('Provider unavailable'));
    $this->mock(SemanticSearchService::class)->shouldReceive('searchWithMeta')->once()->andThrow(new RuntimeException('Unavailable'));

    $response = app(ChatAnswerService::class)->answer($this->bot, $this->conversation, 'Guten Tag');
    expect($response['fallback'])->toBeTrue()
        ->and($response['message'])->toBeString()->not->toBeEmpty()
        ->and($this->conversation->fresh()->language)->toBe('de');
});
