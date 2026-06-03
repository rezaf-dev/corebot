<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBotIntegrationRequest;
use App\Http\Requests\Admin\UpdateBotIntegrationRequest;
use App\Models\Bot;
use App\Models\BotIntegration;
use App\Services\Integrations\IntegrationExecutor;
use App\Support\TenantAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BotIntegrationController extends Controller
{
    public function store(
        StoreBotIntegrationRequest $request,
        Bot $bot,
        TenantAccess $access,
    ): RedirectResponse {
        $access->ensureCanAccess($request->user(), $bot);
        abort_unless($request->user()->isTenantAdmin() || $request->user()->isSuperAdmin(), 403);

        $bot->integrations()->create([
            'tenant_id' => $bot->tenant_id,
            ...$request->integrationAttributes(),
        ]);

        return redirect()
            ->route('bots.edit', $bot)
            ->with('success', 'Integration added.');
    }

    public function update(
        UpdateBotIntegrationRequest $request,
        Bot $bot,
        BotIntegration $integration,
        TenantAccess $access,
    ): RedirectResponse {
        $access->ensureCanAccess($request->user(), $bot);
        abort_unless($integration->bot_id === $bot->id, 404);
        abort_unless($request->user()->isTenantAdmin() || $request->user()->isSuperAdmin(), 403);

        $attributes = $request->integrationAttributes();

        if (! filled($request->input('credentials.bearer_token'))) {
            unset($attributes['credentials']);
        }

        $integration->update($attributes);

        return redirect()
            ->route('bots.edit', $bot)
            ->with('success', 'Integration updated.');
    }

    public function destroy(
        Request $request,
        Bot $bot,
        BotIntegration $integration,
        TenantAccess $access,
    ): RedirectResponse {
        $access->ensureCanAccess($request->user(), $bot);
        abort_unless($integration->bot_id === $bot->id, 404);
        abort_unless($request->user()->isTenantAdmin() || $request->user()->isSuperAdmin(), 403);

        $integration->delete();

        return redirect()
            ->route('bots.edit', $bot)
            ->with('success', 'Integration removed.');
    }

    public function test(
        Request $request,
        Bot $bot,
        BotIntegration $integration,
        TenantAccess $access,
        IntegrationExecutor $executor,
    ): RedirectResponse {
        $access->ensureCanAccess($request->user(), $bot);
        abort_unless($integration->bot_id === $bot->id, 404);
        abort_unless($request->user()->isTenantAdmin() || $request->user()->isSuperAdmin(), 403);

        try {
            $result = $executor->test($integration, $bot);

            return redirect()
                ->route('bots.edit', $bot)
                ->with('success', $result['message'] ?? 'Connection test succeeded.');
        } catch (\Throwable $e) {
            return redirect()
                ->route('bots.edit', $bot)
                ->withErrors(['integration_test' => $e->getMessage()]);
        }
    }
}
