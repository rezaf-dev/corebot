<?php

use App\Models\ApplicationSetting;
use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\OpenAIService;
use App\Services\Rag\ChatAnswerService;
use App\Services\Rag\SearchResult;
use App\Services\Rag\SemanticSearchService;
use App\Support\Localization;
use App\Support\WidgetConfig;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Demo', 'slug' => 'widget-localization', 'status' => 'active']);
    $this->bot = Bot::create(['tenant_id' => $this->tenant->id, 'name' => 'Bot']);
});

it('saves per bot language settings and localized content from multipart json', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant_admin']);
    $payload = array_replace(WidgetConfig::DEFAULTS, [
        'enabled_locales' => json_encode(['en', 'fa']),
        'default_locale' => 'fa',
        'localized_content' => json_encode(['fa' => ['title' => 'پشتیبانی', 'welcome_message' => 'سلام', 'suggested_prompts' => ['کمک']]]),
    ]);
    $this->actingAs($user)->put(route('widget.install.update', $this->bot), $payload)->assertRedirect()->assertSessionHasNoErrors();
    expect($this->bot->fresh()->widget_config)->default_locale->toBe('fa')
        ->enabled_locales->toBe(['en', 'fa'])
        ->localized_content->toBe(['fa' => ['title' => 'پشتیبانی', 'welcome_message' => 'سلام', 'suggested_prompts' => ['کمک']]]);

    $legacyPayload = WidgetConfig::DEFAULTS;
    unset($legacyPayload['default_locale'], $legacyPayload['enabled_locales'], $legacyPayload['localized_content']);
    $this->actingAs($user)->put(route('widget.install.update', $this->bot), $legacyPayload)->assertSessionHasNoErrors();
    expect($this->bot->fresh()->widget_config['default_locale'])->toBe('fa');

    $payload['enabled_locales'] = '[]';
    $payload['localized_content'] = '{}';
    $payload['default_locale'] = '';
    $this->actingAs($user)->put(route('widget.install.update', $this->bot), $payload)->assertSessionHasNoErrors();
    expect($this->bot->fresh()->widget_config)->default_locale->toBe('')->enabled_locales->toBe([])->localized_content->toBe([]);
});

it('rejects disabled unsupported and inconsistent widget languages', function (array $changes, string $error) {
    ApplicationSetting::factory()->create(['enabled_locales' => ['en', 'fa'], 'default_locale' => 'en']);
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant_admin']);
    $this->actingAs($user)->putJson(route('widget.install.update', $this->bot), array_replace(WidgetConfig::DEFAULTS, $changes))
        ->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'globally disabled' => [['enabled_locales' => ['ar']], 'enabled_locales.0'],
    'unsupported' => [['default_locale' => 'xx'], 'default_locale'],
    'default excluded' => [['enabled_locales' => ['en'], 'default_locale' => 'fa'], 'default_locale'],
    'unsupported content' => [['localized_content' => ['xx' => ['title' => 'Title']]], 'localized_content'],
    'unknown field' => [['localized_content' => ['fa' => ['system_prompt' => 'Unsafe']]], 'localized_content.fa'],
    'malformed json' => [['localized_content' => '{oops'], 'localized_content'],
]);

it('does not let an admin modify another tenant widget languages', function () {
    $user = User::factory()->create(['role' => 'tenant_admin', 'tenant_id' => Tenant::create(['name' => 'Other', 'slug' => 'other-widget', 'status' => 'active'])->id]);
    $this->actingAs($user)->putJson(route('widget.install.update', $this->bot), array_replace(WidgetConfig::DEFAULTS, ['default_locale' => 'fa']))->assertForbidden();
});

it('resolves bot enabled languages against current global settings and fallback defaults', function () {
    ApplicationSetting::factory()->create(['enabled_locales' => ['en', 'fa', 'ar'], 'default_locale' => 'ar']);
    $this->bot->update(['widget_config' => ['enabled_locales' => ['fa', 'it'], 'default_locale' => 'it']]);
    $resolved = app(Localization::class)->widgetLocalization('it', $this->bot);
    expect($resolved)->locale->toBe('fa')->default_locale->toBe('fa')->enabled_locales->toBe(['fa'])
        ->direction->toBe('rtl');
    expect(array_column($resolved['available_languages'], 'code'))->toBe(['fa']);
    $this->bot->update(['widget_config' => ['enabled_locales' => ['it'], 'default_locale' => 'it']]);
    expect(app(Localization::class)->widgetLocalization(bot: $this->bot))->locale->toBe('ar')->enabled_locales->toBe(['en', 'fa', 'ar']);
});

it('returns localized bot content and persists the selected visitor language', function () {
    $this->bot->update(['widget_config' => [
        'enabled_locales' => ['en', 'fa'], 'default_locale' => 'fa',
        'localized_content' => ['fa' => ['title' => 'پشتیبانی', 'welcome_message' => 'سلام', 'fallback_message' => 'پاسخی ندارم', 'suggested_prompts' => ['کمک']]],
    ]]);
    $this->getJson('/api/public/chat/widget-config?bot_public_key='.$this->bot->public_key.'&locale=ar')
        ->assertSuccessful()->assertJsonPath('localization.locale', 'fa')->assertJsonPath('localization.enabled_locales', ['en', 'fa'])
        ->assertJsonPath('widget.title', 'پشتیبانی')->assertJsonPath('widget.fallback_message', 'پاسخی ندارم')->assertJsonPath('widget.suggested_prompts', ['کمک']);
    $response = $this->postJson('/api/public/chat/start', ['bot_public_key' => $this->bot->public_key])
        ->assertSuccessful()->assertJsonPath('welcome_message', 'سلام');
    expect(ChatConversation::find($response->json('conversation_id'))->language)->toBe('fa');
});

it('translates built in messages while preserving custom base content', function () {
    $translations = app(Localization::class)->translations('fa');
    $widget = app(Localization::class)->widgetConfig($this->bot, 'fa');
    expect($widget['title'])->toBe($translations['Support'])
        ->and($widget['welcome_message'])->toBe($translations[Bot::DEFAULT_WELCOME_MESSAGE])
        ->and($widget['fallback_message'])->toBe($translations[Bot::DEFAULT_FALLBACK_MESSAGE]);
    $this->bot->update(['welcome_message' => 'Custom welcome', 'fallback_message' => 'Custom fallback', 'widget_config' => ['title' => 'Custom title']]);
    expect(app(Localization::class)->widgetConfig($this->bot, 'fa'))->title->toBe('Custom title')->welcome_message->toBe('Custom welcome')->fallback_message->toBe('Custom fallback');
});

it('changes conversation language only after authenticating the public session', function () {
    $conversation = ChatConversation::create(['tenant_id' => $this->tenant->id, 'bot_id' => $this->bot->id, 'language' => 'en', 'public_session_token' => hash('sha256', $token = Str::random(64))]);
    $payload = ['bot_public_key' => $this->bot->public_key, 'conversation_id' => $conversation->id, 'conversation_token' => Str::random(64), 'message' => 'Hello', 'locale' => 'fa'];
    $this->postJson('/api/public/chat/message', $payload)->assertNotFound();
    expect($conversation->fresh()->language)->toBe('en');
    $this->mock(ChatAnswerService::class)->shouldReceive('answer')->once()->withArgs(fn ($bot, $received) => $received->language === 'fa')->andReturn(['message' => 'سلام']);
    $payload['conversation_token'] = $token;
    $this->postJson('/api/public/chat/message', $payload)->assertSuccessful();
    expect($conversation->fresh()->language)->toBe('fa');
});

it('instructs the model to answer in the selected language and uses localized fallbacks', function () {
    $conversation = ChatConversation::create(['tenant_id' => $this->tenant->id, 'bot_id' => $this->bot->id, 'language' => 'fa']);
    $this->bot->update(['widget_config' => ['localized_content' => ['fa' => ['fallback_message' => 'پاسخی ندارم']]]]);
    $this->mock(SemanticSearchService::class)->shouldReceive('searchWithMeta')->once()->andReturn(new SearchResult(collect(), confident: false));
    $this->mock(OpenAIService::class)->shouldReceive('createChatCompletion')->once()
        ->withArgs(fn ($tenant, $messages) => str_contains($messages[0]['content'], 'Visitor language: Persian (fa). Respond in this language'))
        ->andReturn(['choices' => [['message' => ['content' => '']]]]);
    expect(app(ChatAnswerService::class)->answer($this->bot->load('tenant'), $conversation, 'Hello')['message'])->toBe('پاسخی ندارم');
});

it('uses translated default fallback on retrieval failures', function () {
    $conversation = ChatConversation::create(['tenant_id' => $this->tenant->id, 'bot_id' => $this->bot->id, 'language' => 'fa']);
    $this->mock(SemanticSearchService::class)->shouldReceive('searchWithMeta')->once()->andThrow(new RuntimeException('Unavailable'));
    $response = app(ChatAnswerService::class)->answer($this->bot->load('tenant'), $conversation, 'Hello');
    expect($response['message'])->toBe(app(Localization::class)->translations('fa')[Bot::DEFAULT_FALLBACK_MESSAGE])->and($response['fallback'])->toBeTrue();
});

it('uses the selected locale for streamed requests and fallbacks', function () {
    $conversation = ChatConversation::create(['tenant_id' => $this->tenant->id, 'bot_id' => $this->bot->id, 'language' => 'en', 'public_session_token' => hash('sha256', $token = Str::random(64))]);
    $this->bot->update(['widget_config' => ['localized_content' => ['fa' => ['fallback_message' => 'پاسخی ندارم']]]]);
    $this->mock(SemanticSearchService::class)->shouldReceive('searchWithMeta')->once()->andThrow(new RuntimeException('Unavailable'));
    $response = $this->postJson('/api/public/chat/message/stream', ['bot_public_key' => $this->bot->public_key, 'conversation_id' => $conversation->id, 'conversation_token' => $token, 'message' => 'Help', 'locale' => 'fa']);
    $response->assertStreamed();
    $response->streamedContent();
    expect($conversation->fresh()->language)->toBe('fa')
        ->and($conversation->messages()->where('role', 'assistant')->sole()->content)->toBe('پاسخی ندارم');
});

it('embeds bot localization in static widget bootstrap', function () {
    $this->bot->update(['widget_config' => ['default_locale' => 'fa', 'enabled_locales' => ['en', 'fa'], 'localized_content' => ['fa' => ['title' => 'پشتیبانی']]]]);
    $asset = file_get_contents(config('corebot.widget_assets.path').'/'.$this->bot->public_key.'.js');
    expect($asset)->toContain('"default_locale":"fa"')->toContain('"direction":"rtl"')->toContain('"title":"پشتیبانی"')->toContain('"available_languages":');
});

it('uses base custom content when localized text and prompts are blank', function () {
    $this->bot->update(['widget_config' => ['title' => 'Custom title', 'suggested_prompts' => ['Custom prompt']]]);
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant_admin']);
    $payload = array_replace($this->bot->resolvedWidgetConfig(), ['localized_content' => json_encode(['fa' => ['title' => '   ', 'suggested_prompts' => ['', '   ']]])]);
    $this->actingAs($user)->put(route('widget.install.update', $this->bot), $payload)->assertSessionHasNoErrors();
    $config = app(Localization::class)->widgetConfig($this->bot->fresh(), 'fa');
    expect($config)->title->toBe('Custom title')->suggested_prompts->toBe(['Custom prompt']);
});

it('only sends compact language metadata per bot on the installation page', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant_admin']);
    $this->actingAs($user)->get(route('widget.install'))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('bots.0.localization.available_languages')->missing('bots.0.localization.translations')
        ->where('language_settings.default_locale', 'en')->has('languages', 6)
        ->where('preview_translations.fa.Support', 'پشتیبانی')
        ->where('bots.0.welcome_message', Bot::DEFAULT_WELCOME_MESSAGE)
        ->has('preview_translations.fa'));
});

it('saves and serves custom conversation starters in every supported language', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $questions = [
        'en' => 'How do I get started?',
        'fa' => 'چطور می‌توانم شروع کنم؟',
        'ar' => 'كيف يمكنني البدء؟',
        'it' => 'Come posso iniziare?',
        'es' => '¿Cómo puedo empezar?',
        'tr' => 'Nasıl başlayabilirim?',
    ];
    $localizedContent = collect($questions)->map(fn (string $question): array => ['title' => 'Title: '.$question, 'subtitle' => $question, 'suggested_prompts' => [$question]])->all();
    $payload = array_replace(WidgetConfig::DEFAULTS, [
        '_method' => 'put',
        'suggested_prompts' => ['Base question'],
        'localized_content' => json_encode($localizedContent, JSON_UNESCAPED_UNICODE),
        'enabled_locales' => json_encode(array_keys($questions)),
    ]);

    $this->actingAs($user)->post(route('widget.install.update', $this->bot), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($this->bot->fresh()->widget_config['suggested_prompts'])->toBe(['Base question'])
        ->and($this->bot->fresh()->widget_config['localized_content'])->toBe($localizedContent);

    foreach ($questions as $locale => $question) {
        $this->getJson('/api/public/chat/widget-config?bot_public_key='.$this->bot->public_key.'&locale='.$locale)
            ->assertSuccessful()
            ->assertJsonPath('localization.locale', $locale)
            ->assertJsonPath('widget.suggested_prompts', [$question])
            ->assertJsonPath('widget.title', 'Title: '.$question)
            ->assertJsonPath('widget.subtitle', $question);
    }
});
