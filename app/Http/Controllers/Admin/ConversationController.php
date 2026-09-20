<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualConversationMessageRequest;
use App\Models\ChatConversation;
use App\Support\TenantAccess;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(TenantAccess $access): Response
    {
        return Inertia::render('Conversations/Index', [
            'conversations' => $access->scope(ChatConversation::query(), auth()->user())
                ->with('bot:id,name')
                ->withCount('messages')
                ->orderByRaw("case when status = 'escalated' then 0 else 1 end")
                ->latest('updated_at')
                ->get(),
        ]);
    }

    public function show(ChatConversation $conversation, TenantAccess $access): Response
    {
        $access->ensureCanAccess(auth()->user(), $conversation);

        return Inertia::render('Conversations/Show', [
            'conversation' => $conversation->load([
                'bot:id,name',
                'messages' => fn ($query) => $query->orderBy('created_at'),
                'retrievalLogs' => fn ($query) => $query->latest(),
                'integrationActionLogs' => fn ($query) => $query->with('integration:id,name,type')->latest(),
            ]),
        ]);
    }

    public function storeMessage(
        StoreManualConversationMessageRequest $request,
        ChatConversation $conversation,
        TenantAccess $access,
    ): RedirectResponse {
        $access->ensureCanAccess($request->user(), $conversation);

        $conversation->messages()->create([
            'tenant_id' => $conversation->tenant_id,
            'bot_id' => $conversation->bot_id,
            'role' => 'admin',
            'content' => $request->validated('message'),
            'metadata' => [
                'admin_id' => $request->user()->id,
                'admin_name' => $request->user()->name,
            ],
        ]);

        $conversation->update([
            'status' => 'open',
            'manual_handoff_notified_at' => null,
        ]);

        return back();
    }

    public function destroy(ChatConversation $conversation, TenantAccess $access): RedirectResponse
    {
        $access->ensureCanAccess(auth()->user(), $conversation);

        $conversation->delete();

        return redirect()->route('conversations.index')->with('success', 'Conversation deleted.');
    }
}
