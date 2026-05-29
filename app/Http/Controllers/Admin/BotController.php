<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Support\TenantAccess;
use App\Support\WidgetConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotController extends Controller
{
    public function index(TenantAccess $access): Response
    {
        $bots = $access->scope(Bot::query(), auth()->user())->withCount(['knowledgeSources', 'conversations'])->latest()->get();

        return Inertia::render('Bots/Index', ['bots' => $bots]);
    }

    public function create(): Response
    {
        return Inertia::render('Bots/Form', ['bot' => null]);
    }

    public function store(Request $request, TenantAccess $access): RedirectResponse
    {
        $tenant = $access->ensureTenantAdmin(auth()->user());
        $tenant->bots()->create($this->validated($request));

        return redirect()->route('bots.index')->with('success', 'Bot created.');
    }

    public function edit(Bot $bot, TenantAccess $access): Response
    {
        $access->ensureCanAccess(auth()->user(), $bot);

        return Inertia::render('Bots/Form', [
            'bot' => $bot,
            'widgetUrl' => url('/widget.js'),
            'widgetSnippet' => WidgetConfig::embedSnippet(
                url('/widget.js'),
                $bot->public_key,
                $bot->resolvedWidgetConfig(),
            ),
        ]);
    }

    public function update(Request $request, Bot $bot, TenantAccess $access): RedirectResponse
    {
        $access->ensureCanAccess(auth()->user(), $bot);
        $bot->update($this->validated($request));

        return redirect()->route('bots.index')->with('success', 'Bot updated.');
    }

    public function destroy(Bot $bot, TenantAccess $access): RedirectResponse
    {
        $access->ensureCanAccess(auth()->user(), $bot);
        $bot->delete();

        return back()->with('success', 'Bot deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'welcome_message' => ['nullable', 'string'],
            'fallback_message' => ['nullable', 'string'],
            'system_prompt' => ['nullable', 'string'],
            'allowed_domains' => ['nullable', 'string'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:1'],
            'max_context_chunks' => ['required', 'integer', 'min:1', 'max:12'],
            'similarity_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
            'contact_fields' => ['nullable', 'array'],
            'contact_fields.*' => ['string', 'in:name,email,phone'],
            'contact_required' => ['nullable', 'array'],
            'contact_required.*' => ['string', 'in:name,email,phone'],
            'notification_email' => ['nullable', 'email', 'max:255'],
            'collect_contact_on_start' => ['boolean'],
        ]);

        $contactFields = $data['contact_fields'] ?? ['name', 'email'];
        $data['contact_fields'] = array_values(array_unique($contactFields ?: ['name', 'email']));
        $data['contact_required'] = array_values(array_intersect(
            $data['contact_required'] ?? ['email'],
            $data['contact_fields'],
        ));

        $data['allowed_domains'] = collect(preg_split('/[\s,]+/', $data['allowed_domains'] ?? '', flags: PREG_SPLIT_NO_EMPTY))
            ->map(fn ($domain) => strtolower(trim($domain)))
            ->values()
            ->all();

        return $data;
    }
}
