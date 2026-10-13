<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualConversationMessageRequest;
use App\Http\Requests\UpdateConversationRequest;
use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request, TenantAccess $access): Response
    {
        return Inertia::render('Conversations/Index', $this->inbox($request, $access));
    }

    public function show(Request $request, ChatConversation $conversation, TenantAccess $access): Response
    {
        Gate::authorize('view', $conversation);
        $limit = min(1000, max(50, (int) $request->query('message_limit', 50)));
        $conversation->load(['bot:id,name', 'assignee:id,name']);
        $messages = $conversation->messages()->latest('id')->limit($limit)->get(['id', 'conversation_id', 'role', 'content', 'metadata', 'created_at'])->reverse()->values();
        $data = $conversation->only(['id', 'tenant_id', 'bot_id', 'visitor_id', 'visitor_name', 'visitor_email', 'visitor_phone', 'source_url', 'status', 'support_mode', 'assigned_to', 'handoff_reason', 'country_name', 'city', 'language', 'created_at', 'updated_at']);
        $data['bot'] = $conversation->bot;
        $data['assignee'] = $conversation->assignee;
        $data['messages'] = $messages->map(fn ($message) => [
            ...$message->only(['id', 'role', 'content', 'created_at']),
            'metadata' => Arr::only($message->metadata ?? [], ['admin_name']),
        ]);
        $data['has_older_messages'] = $conversation->messages()->count() > $messages->count();
        $data['message_limit'] = $limit;
        $data['can_manage'] = $request->user()->can('update', $conversation);
        $data['can_delete'] = $request->user()->can('delete', $conversation);
        $data['history'] = filled($conversation->visitor_id)
            ? ChatConversation::query()->where('tenant_id', $conversation->tenant_id)->where('bot_id', $conversation->bot_id)->where('visitor_id', $conversation->visitor_id)->whereKeyNot($conversation->id)->latest('updated_at')->limit(10)->get(['id', 'status', 'created_at'])
            : [];
        if ($request->user()->isAdmin()) {
            $data['retrieval_logs'] = $conversation->retrievalLogs()->latest()->limit(20)->get();
            $data['integration_action_logs'] = $conversation->integrationActionLogs()->with('integration:id,name,type')->latest()->limit(20)->get();
        }

        return Inertia::render('Conversations/Index', [
            ...$this->inbox($request, $access),
            'conversation' => $data,
            'staff' => User::query()->where('is_active', true)->where(function (Builder $query) use ($conversation): void {
                $query->where('tenant_id', $conversation->tenant_id)->whereIn('role', ['tenant_admin', 'agent'])
                    ->orWhere('role', 'super_admin');
            })->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $data = $request->validate(['before_id' => ['required', 'integer', 'min:1']]);
        $messages = $conversation->messages()->where('id', '<', $data['before_id'])->latest('id')->limit(51)->get(['id', 'role', 'content', 'metadata', 'created_at']);

        return response()->json([
            'has_more' => $messages->count() > 50,
            'messages' => $messages->take(50)->reverse()->values()->map(fn ($message) => [
                ...$message->only(['id', 'role', 'content', 'created_at']),
                'metadata' => Arr::only($message->metadata ?? [], ['admin_name']),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function inbox(Request $request, TenantAccess $access): array
    {
        $filters = $request->validate([
            'tab' => ['sometimes', 'in:needs_reply,mine,unassigned,all,resolved,leads,escalated'],
            'search' => ['nullable', 'string', 'max:200'],
            'bot_id' => ['nullable', 'integer'],
            'tenant_id' => ['nullable', 'integer'],
        ]);
        $tab = $filters['tab'] ?? 'needs_reply';
        $base = $access->scope(ChatConversation::query(), $request->user())
            ->when($filters['bot_id'] ?? null, fn ($query, $id) => $query->where('bot_id', $id))
            ->when($request->user()->isSuperAdmin() && ($filters['tenant_id'] ?? null), fn ($query) => $query->where('tenant_id', $filters['tenant_id']))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    foreach (['visitor_name', 'visitor_email', 'visitor_phone'] as $column) {
                        $query->orWhere($column, 'like', '%'.$search.'%');
                    }
                    if (ctype_digit($search)) {
                        $query->orWhereKey((int) $search);
                    }
                    $query->orWhereHas('messages', fn ($query) => $query->where('content', 'like', '%'.$search.'%'));
                });
            });
        $queues = ['needs_reply', 'mine', 'unassigned', 'all', 'resolved', 'leads'];
        $counts = [];
        foreach ($queues as $queue) {
            $counts[$queue] = $this->queue(clone $base, $queue, $request->user())->count();
        }

        return [
            'filters' => [...$filters, 'tab' => $tab],
            'counts' => $counts,
            'conversations' => $this->queue(clone $base, $tab, $request->user())
                ->with(['bot:id,name', 'assignee:id,name', 'latestMessage:chat_messages.id,chat_messages.conversation_id,chat_messages.role,chat_messages.content,chat_messages.created_at'])
                ->withExists(['messages as needs_reply' => fn ($query) => $query->where('role', 'user')->whereRaw("chat_messages.id > COALESCE((SELECT MAX(replies.id) FROM chat_messages replies WHERE replies.conversation_id = chat_messages.conversation_id AND replies.role = 'admin'), 0)")])
                ->orderByDesc('updated_at')->orderByDesc('id')->paginate(30, ['id', 'tenant_id', 'bot_id', 'visitor_name', 'visitor_email', 'visitor_phone', 'visitor_id', 'status', 'support_mode', 'assigned_to', 'updated_at'])->withQueryString(),
            'bots' => $access->scope(Bot::query(), $request->user())->orderBy('name')->get(['id', 'name']),
            'tenants' => $request->user()->isSuperAdmin() ? Tenant::query()->orderBy('name')->get(['id', 'name']) : [],
            'conversation' => null,
            'staff' => [],
        ];
    }

    private function queue(Builder $query, string $tab, User $user): Builder
    {
        return match ($tab) {
            'needs_reply' => $query->needsReply(),
            'mine' => $query->where('assigned_to', $user->id)->where('status', '!=', 'resolved'),
            'unassigned' => $query->whereNull('assigned_to')->where('support_mode', 'human')->where('status', '!=', 'resolved'),
            'resolved' => $query->where('status', 'resolved'),
            'escalated' => $query->where('status', 'escalated'),
            'leads' => $query->where(fn ($query) => $query->whereNotNull('visitor_name')->orWhereNotNull('visitor_email')->orWhereNotNull('visitor_phone')),
            default => $query,
        };
    }

    public function storeMessage(StoreManualConversationMessageRequest $request, ChatConversation $conversation): RedirectResponse
    {
        DB::transaction(function () use ($request, $conversation): void {
            $conversation = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            Gate::authorize('update', $conversation);
            if ($conversation->status === 'resolved') {
                throw ValidationException::withMessages(['message' => 'Reopen to send a reply.']);
            }
            $conversation->messages()->create([
                'tenant_id' => $conversation->tenant_id,
                'bot_id' => $conversation->bot_id,
                'role' => 'admin',
                'content' => $request->validated('message'),
                'metadata' => ['admin_id' => $request->user()->id, 'admin_name' => $request->user()->name],
            ]);
            $conversation->update(['status' => 'open', 'support_mode' => 'human', 'resolved_at' => null, 'manual_handoff_notified_at' => null]);
        });

        return back();
    }

    public function update(UpdateConversationRequest $request, ChatConversation $conversation): RedirectResponse
    {
        $conflict = DB::transaction(function () use ($request, $conversation): bool {
            $conversation = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $action = $request->validated('action');
            Gate::authorize($action === 'claim' ? 'claim' : 'update', $conversation);
            if ($action === 'claim' && $conversation->assigned_to !== null && $conversation->assigned_to !== $request->user()->id) {
                return true;
            }
            if ($action === 'assign') {
                $target = User::query()->lockForUpdate()->find($request->validated('assigned_to'));
                if (! $target || ! $target->hasWorkspaceAccess() || (! $target->isSuperAdmin() && $target->tenant_id !== $conversation->tenant_id)) {
                    throw ValidationException::withMessages(['assigned_to' => 'Choose an active teammate in this workspace.']);
                }
                $conversation->update(['assigned_to' => $target->id, 'support_mode' => 'human']);
            } elseif ($action === 'claim') {
                $conversation->update(['assigned_to' => $request->user()->id, 'support_mode' => 'human']);
            } elseif ($action === 'resolve') {
                $conversation->update(['status' => 'resolved', 'resolved_at' => now()]);
            } elseif ($action === 'reopen') {
                $conversation->update(['status' => 'open', 'resolved_at' => null]);
            } else {
                $conversation->update(['status' => 'open', 'support_mode' => 'ai', 'assigned_to' => null, 'resolved_at' => null, 'handoff_reason' => null, 'manual_handoff_notified_at' => null]);
            }

            return false;
        }, 3);

        return $conflict ? back()->with('error', 'This conversation was claimed by another teammate. Ownership has been refreshed.') : back();
    }

    public function destroy(ChatConversation $conversation): RedirectResponse
    {
        Gate::authorize('delete', $conversation);
        $conversation->delete();

        return redirect()->route('conversations.index')->with('success', 'Conversation deleted.');
    }
}
