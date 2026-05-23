<?php

use App\Enums\KnowledgeSourceStatus;
use App\Jobs\ProcessKnowledgeSourceJob;
use App\Models\Bot;
use App\Models\KnowledgeSource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('renders the knowledge index for tenant admins', function () {
    [$tenant, $user] = tenantAdmin();
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'status' => 'active']);

    KnowledgeSource::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'type' => 'text',
        'title' => 'Policy',
        'status' => KnowledgeSourceStatus::Ready->value,
        'raw_text' => 'Returns are accepted within thirty days of purchase.',
        'chunks_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('knowledge-sources.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Knowledge/Index')
            ->has('sources.data', 1)
            ->where('sources.data.0.title', 'Policy')
            ->where('stats.total', 1));
});

it('filters knowledge sources by search term', function () {
    [$tenant, $user] = tenantAdmin();
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support Bot', 'status' => 'active']);

    KnowledgeSource::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'type' => 'text',
        'title' => 'Returns policy',
        'status' => KnowledgeSourceStatus::Ready->value,
        'raw_text' => str_repeat('Returns are accepted within thirty days. ', 5),
        'chunks_count' => 1,
    ]);

    KnowledgeSource::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'type' => 'text',
        'title' => 'Shipping guide',
        'status' => KnowledgeSourceStatus::Ready->value,
        'raw_text' => str_repeat('We ship worldwide within five business days. ', 5),
        'chunks_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('knowledge-sources.index', ['search' => 'returns']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sources.data', 1)
            ->where('sources.data.0.title', 'Returns policy')
            ->where('filters.search', 'returns'));

    $this->actingAs($user)
        ->get(route('knowledge-sources.index', ['search' => 'Support']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sources.data', 2)
            ->where('filters.search', 'Support'));
});

it('paginates knowledge sources', function () {
    [$tenant, $user] = tenantAdmin();
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'status' => 'active']);

    foreach (range(1, 12) as $index) {
        KnowledgeSource::create([
            'tenant_id' => $tenant->id,
            'bot_id' => $bot->id,
            'type' => 'text',
            'title' => "Source {$index}",
            'status' => KnowledgeSourceStatus::Ready->value,
            'raw_text' => str_repeat("Content for source {$index}. ", 5),
            'chunks_count' => 1,
        ]);
    }

    $this->actingAs($user)
        ->get(route('knowledge-sources.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sources.data', 10)
            ->where('sources.total', 12)
            ->where('sources.last_page', 2));

    $this->actingAs($user)
        ->get(route('knowledge-sources.index', ['page' => 2]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sources.data', 2)
            ->where('sources.current_page', 2));
});

it('queues a text source with queued status on create', function () {
    Queue::fake();

    [$tenant, $user] = tenantAdmin();
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'status' => 'active']);

    $this->actingAs($user)->post(route('knowledge-sources.store'), [
        'bot_id' => $bot->id,
        'type' => 'text',
        'title' => 'Shipping',
        'raw_text' => str_repeat('We ship worldwide within five business days. ', 5),
    ])->assertRedirect();

    $source = KnowledgeSource::query()->first();

    expect($source)->not->toBeNull()
        ->and($source->status)->toBe(KnowledgeSourceStatus::Queued->value);

    Queue::assertPushed(ProcessKnowledgeSourceJob::class, fn (ProcessKnowledgeSourceJob $job) => $job->knowledgeSourceId === $source->id);
});

it('prevents reprocessing while a source is queued', function () {
    Queue::fake();

    [$tenant, $user] = tenantAdmin();
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'status' => 'active']);

    $source = KnowledgeSource::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'type' => 'text',
        'title' => 'Policy',
        'status' => KnowledgeSourceStatus::Queued->value,
        'raw_text' => str_repeat('Returns are accepted within thirty days. ', 5),
    ]);

    $this->actingAs($user)
        ->post(route('knowledge-sources.reprocess', $source))
        ->assertRedirect()
        ->assertSessionHas('error', 'This source is already queued or processing.');

    Queue::assertNothingPushed();
});

it('cancels a queued source', function () {
    [$tenant, $user] = tenantAdmin();
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'status' => 'active']);

    $source = KnowledgeSource::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'type' => 'text',
        'title' => 'Policy',
        'status' => KnowledgeSourceStatus::Queued->value,
        'raw_text' => str_repeat('Returns are accepted within thirty days. ', 5),
    ]);

    $this->actingAs($user)
        ->post(route('knowledge-sources.cancel', $source))
        ->assertRedirect()
        ->assertSessionHas('success', 'Processing cancelled.');

    expect($source->fresh()->status)->toBe(KnowledgeSourceStatus::Cancelled->value);
});

it('updates editable text sources and requeues them', function () {
    Queue::fake();

    [$tenant, $user] = tenantAdmin();
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'status' => 'active']);

    $source = KnowledgeSource::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'type' => 'text',
        'title' => 'Policy',
        'status' => KnowledgeSourceStatus::Ready->value,
        'raw_text' => str_repeat('Old policy text for customers. ', 5),
        'chunks_count' => 2,
    ]);

    $this->actingAs($user)->put(route('knowledge-sources.update', $source), [
        'title' => 'Updated policy',
        'raw_text' => str_repeat('New policy text for customers worldwide. ', 5),
    ])->assertRedirect();

    $source->refresh();

    expect($source->title)->toBe('Updated policy')
        ->and($source->status)->toBe(KnowledgeSourceStatus::Queued->value);

    Queue::assertPushed(ProcessKnowledgeSourceJob::class);
});

it('rejects edits while a source is processing', function () {
    [$tenant, $user] = tenantAdmin();
    $bot = Bot::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'status' => 'active']);

    $source = KnowledgeSource::create([
        'tenant_id' => $tenant->id,
        'bot_id' => $bot->id,
        'type' => 'text',
        'title' => 'Policy',
        'status' => KnowledgeSourceStatus::Processing->value,
        'raw_text' => str_repeat('Returns are accepted within thirty days. ', 5),
    ]);

    $this->actingAs($user)
        ->put(route('knowledge-sources.update', $source), [
            'title' => 'Blocked',
            'raw_text' => str_repeat('Should not save while processing. ', 5),
        ])
        ->assertSessionHasErrors('title');
});

/**
 * @return array{0: Tenant, 1: User}
 */
function tenantAdmin(): array
{
    $tenant = Tenant::create(['name' => 'Demo', 'slug' => 'demo-'.uniqid(), 'status' => 'active']);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);

    return [$tenant, $user];
}
