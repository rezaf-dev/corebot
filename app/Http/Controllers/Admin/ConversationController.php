<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Support\TenantAccess;
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
                ->latest()
                ->get(),
        ]);
    }

    public function show(ChatConversation $conversation, TenantAccess $access): Response
    {
        $access->ensureCanAccess(auth()->user(), $conversation);

        return Inertia::render('Conversations/Show', [
            'conversation' => $conversation->load(['bot:id,name', 'messages', 'retrievalLogs']),
        ]);
    }
}
