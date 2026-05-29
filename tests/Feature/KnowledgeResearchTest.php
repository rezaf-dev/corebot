<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\UrlSafety;
use Illuminate\Support\Facades\Http;

it('requires authentication for knowledge research', function () {
    $this->postJson(route('knowledge-sources.research'), [
        'mode' => 'url',
        'query' => 'https://example.com/page',
    ])->assertUnauthorized();
});

it('fetches readable content from a url for tenant admins', function () {
    [, $user] = tenantAdmin();

    app()->instance(UrlSafety::class, new UrlSafety(verifyDns: false));

    Http::fake([
        'https://example.com/docs' => Http::response(
            '<html><head><title>Getting Started</title></head><body><main><p>Returns are accepted within thirty days.</p></main></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('knowledge-sources.research'), [
            'mode' => 'url',
            'query' => 'https://example.com/docs',
        ])
        ->assertSuccessful()
        ->assertJson([
            'title' => 'Getting Started',
            'sources' => [
                [
                    'url' => 'https://example.com/docs',
                    'title' => 'Getting Started',
                ],
            ],
        ])
        ->assertJsonPath('content', fn (string $content) => str_contains($content, 'Returns are accepted within thirty days.'));
});

it('rejects private urls during knowledge research', function () {
    [, $user] = tenantAdmin();

    $this->actingAs($user)
        ->postJson(route('knowledge-sources.research'), [
            'mode' => 'url',
            'query' => 'http://127.0.0.1/private',
        ])
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'This URL is not allowed.',
        ]);
});

it('searches the web and combines extracted content', function () {
    [, $user] = tenantAdmin();

    app()->instance(UrlSafety::class, new UrlSafety(verifyDns: false));

    config()->set('corebot.knowledge_research.tavily_api_key', 'test-key');
    config()->set('corebot.knowledge_research.search_max_results', 1);

    Http::fake([
        'https://api.tavily.com/search' => Http::response([
            'results' => [
                [
                    'title' => 'Queue workers',
                    'url' => 'https://example.com/queues',
                    'content' => 'Run php artisan horizon to process queued jobs.',
                ],
            ],
        ], 200),
        'https://example.com/queues' => Http::response(
            '<html><head><title>Queue workers</title></head><body><p>Run php artisan horizon to process queued jobs.</p></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('knowledge-sources.research'), [
            'mode' => 'search',
            'query' => 'laravel queue worker',
        ])
        ->assertSuccessful()
        ->assertJson([
            'title' => 'Research: laravel queue worker',
        ])
        ->assertJsonPath('sources.0.url', 'https://example.com/queues')
        ->assertJsonPath('content', fn (string $content) => str_contains($content, 'php artisan horizon'));
});

it('validates knowledge research mode and query', function () {
    [, $user] = tenantAdmin();

    $this->actingAs($user)
        ->postJson(route('knowledge-sources.research'), [
            'mode' => 'invalid',
            'query' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mode', 'query']);
});

if (! function_exists('tenantAdmin')) {
    /**
     * @return array{0: Tenant, 1: User}
     */
    function tenantAdmin(): array
    {
        $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-'.uniqid(), 'status' => 'active']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);

        return [$tenant, $user];
    }
}
