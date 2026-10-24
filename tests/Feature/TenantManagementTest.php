<?php

use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\KnowledgeSource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('lists active and disabled tenants for platform admins', function () {
    Tenant::factory()->create();
    Tenant::factory()->create(['status' => 'inactive']);

    $this->actingAs(User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]))
        ->get(route('tenants.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tenants/Index')->has('tenants', 2));
});

it('edits a tenant without changing its slug or another tenant', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();

    $this->actingAs(User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]))
        ->from(route('tenants.index'))
        ->put(route('tenants.update', $tenant), ['name' => 'Renamed workspace', 'status' => 'inactive', 'slug' => 'unexpected'])
        ->assertRedirect(route('tenants.index'))
        ->assertSessionHas('success');

    expect($tenant->fresh())->name->toBe('Renamed workspace')->status->toBe('inactive')->slug->toBe($tenant->slug);
    expect($other->fresh()->status)->toBe('active');
});

it('validates tenant changes', function (array $data, string $field) {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]))
        ->put(route('tenants.update', $tenant), $data)
        ->assertSessionHasErrors($field);

    expect($tenant->fresh()->name)->toBe($tenant->name);
    expect($tenant->fresh()->status)->toBe('active');
})->with([
    'missing name' => [['name' => '', 'status' => 'active'], 'name'],
    'long name' => [['name' => str_repeat('a', 256), 'status' => 'active'], 'name'],
    'invalid status' => [['name' => 'Workspace', 'status' => 'unknown'], 'status'],
    'missing status' => [['name' => 'Workspace'], 'status'],
]);

it('restricts tenant management to platform admins', function (string $role) {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);

    $this->actingAs($user)->get(route('tenants.index'))->assertForbidden();
    $this->put(route('tenants.update', $tenant), ['name' => 'Changed', 'status' => 'inactive'])->assertForbidden();
    $this->delete(route('tenants.destroy', $tenant))->assertForbidden();
    expect($tenant->fresh())->name->toBe($tenant->name)->status->toBe('active');
})->with(['tenant_admin', 'agent']);

it('blocks disabled tenants staff and public chat and restores access on enabling', function (string $role) {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
    $admin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);
    $bot = Bot::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

    $this->actingAs($admin)->put(route('tenants.update', $tenant), ['name' => $tenant->name, 'status' => 'inactive'])->assertRedirect();
    $this->actingAs($user->fresh())->get(route('conversations.index'))->assertForbidden();
    $this->postJson('/api/public/chat/start', ['bot_public_key' => $bot->public_key, 'visitor_id' => 'visitor-1'])->assertNotFound();

    expect($user->fresh()->is_active)->toBeTrue();
    expect($bot->fresh()->status)->toBe('active');
    $this->actingAs($admin)->get(route('tenants.index'))->assertOk();
    $this->put(route('tenants.update', $tenant), ['name' => $tenant->name, 'status' => 'active'])->assertRedirect();
    $this->actingAs($user->fresh())->get(route('conversations.index'))->assertOk();
    $this->postJson('/api/public/chat/start', ['bot_public_key' => $bot->public_key, 'visitor_id' => 'visitor-1'])->assertSuccessful();
})->with(['tenant_admin', 'agent']);

it('deletes tenant data and uploaded files while deactivating its staff only', function () {
    Storage::fake();
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant_admin']);
    $otherStaff = User::factory()->create(['tenant_id' => $other->id]);
    $bot = Bot::factory()->create(['tenant_id' => $tenant->id]);
    $otherBot = Bot::factory()->create(['tenant_id' => $other->id]);
    $conversation = ChatConversation::create(['tenant_id' => $tenant->id, 'bot_id' => $bot->id, 'visitor_id' => 'delete-visitor', 'status' => 'open']);
    $source = KnowledgeSource::create(['tenant_id' => $tenant->id, 'bot_id' => $bot->id, 'type' => 'text', 'title' => 'Knowledge', 'status' => 'ready']);
    Storage::put("tenants/{$tenant->id}/knowledge/file.txt", 'private knowledge');
    Storage::put("tenants/{$other->id}/knowledge/file.txt", 'other knowledge');

    $this->actingAs(User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]))
        ->from(route('tenants.index'))->delete(route('tenants.destroy', $tenant))
        ->assertRedirect(route('tenants.index'))->assertSessionHas('success');

    $this->assertModelMissing($tenant);
    $this->assertModelMissing($bot);
    $this->assertModelMissing($conversation);
    $this->assertModelMissing($source);
    expect($staff->fresh())->is_active->toBeFalse()->tenant_id->toBeNull();
    expect($otherStaff->fresh()->is_active)->toBeTrue();
    $this->assertModelExists($other);
    $this->assertModelExists($otherBot);
    expect(File::exists(config('corebot.widget_assets.path').'/'.$bot->public_key.'.js'))->toBeFalse();
    Storage::assertMissing("tenants/{$tenant->id}/knowledge/file.txt");
    Storage::assertExists("tenants/{$other->id}/knowledge/file.txt");
});

it('requires authentication and handles missing tenants', function () {
    $tenant = Tenant::factory()->create();
    $this->put(route('tenants.update', $tenant), [])->assertRedirect(route('login'));
    $this->delete(route('tenants.destroy', $tenant))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]))
        ->delete(route('tenants.destroy', 999999))->assertNotFound();
});
