<?php

use App\Models\AiUsageLog;
use App\Models\Bot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Embeddings;

it('shows token usage per bot for tenant admins', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-dashboard', 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);

    $supportBot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support Bot']);
    $salesBot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Sales Bot']);

    AiUsageLog::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $supportBot->id,
        'type' => 'chat',
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'input_tokens' => 100,
        'output_tokens' => 50,
        'total_tokens' => 150,
    ]);

    AiUsageLog::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $salesBot->id,
        'type' => 'embedding',
        'provider' => 'openai',
        'model' => 'text-embedding-3-small',
        'input_tokens' => null,
        'output_tokens' => null,
        'total_tokens' => 25,
    ]);

    $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other-dashboard', 'status' => 'active']);
    $otherBot = Bot::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Bot']);

    AiUsageLog::create([
        'tenant_id' => $otherTenant->id,
        'bot_id' => $otherBot->id,
        'type' => 'chat',
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'total_tokens' => 999,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.total_tokens', 175)
            ->has('stats.bot_token_usage', 2)
            ->where('stats.bot_token_usage.0.name', 'Support Bot')
            ->where('stats.bot_token_usage.0.total_tokens', 150)
            ->where('stats.bot_token_usage.0.input_tokens', 100)
            ->where('stats.bot_token_usage.0.output_tokens', 50)
            ->where('stats.bot_token_usage.1.name', 'Sales Bot')
            ->where('stats.bot_token_usage.1.total_tokens', 25)
            ->has('stats.usage_logs', 2)
            ->where('stats.usage_logs', fn ($logs) => collect($logs->toArray())
                ->pluck('bot.name')
                ->sort()
                ->values()
                ->all() === ['Sales Bot', 'Support Bot'])
        );
});

it('logs token usage with bot id when generating embeddings', function () {
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-embed-log', 'status' => 'active']);
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support Bot']);

    tenantWithAiSettingsFor($tenant);

    Embeddings::fake([
        [[0.1, 0.2, 0.3]],
    ])->preventStrayEmbeddings();

    app(OpenAIService::class)->createEmbedding($tenant, 'Hello', ['bot_id' => $bot->id]);

    $log = AiUsageLog::query()->where('bot_id', $bot->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->type)->toBe('embedding')
        ->and($log->tenant_id)->toBe($tenant->id);
});

function tenantWithAiSettingsFor(Tenant $tenant): void
{
    $tenant->aiSetting()->create([
        'provider' => 'openai',
        'api_key_encrypted' => Crypt::encryptString('sk-test'),
        'base_url' => 'https://api.openai.com/v1',
        'chat_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
        'is_active' => true,
    ]);
}
