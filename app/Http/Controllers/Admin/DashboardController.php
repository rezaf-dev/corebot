<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\Bot;
use App\Models\ChatConversation;
use App\Models\KnowledgeSource;
use App\Models\Tenant;
use App\Support\TenantAccess;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(TenantAccess $access): Response
    {
        $user = auth()->user();
        $usageQuery = $access->scope(AiUsageLog::query(), $user);

        $botTokenUsage = $access->scope(Bot::query(), $user)
            ->withSum('aiUsageLogs as total_tokens', 'total_tokens')
            ->withSum('aiUsageLogs as input_tokens', 'input_tokens')
            ->withSum('aiUsageLogs as output_tokens', 'output_tokens')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Bot $bot) => [
                'id' => $bot->id,
                'name' => $bot->name,
                'total_tokens' => (int) ($bot->total_tokens ?? 0),
                'input_tokens' => (int) ($bot->input_tokens ?? 0),
                'output_tokens' => (int) ($bot->output_tokens ?? 0),
            ])
            ->sortByDesc('total_tokens')
            ->values();

        return Inertia::render('Dashboard', [
            'stats' => [
                'tenants' => $user->isSuperAdmin() ? Tenant::count() : null,
                'bots' => $access->scope(Bot::query(), $user)->count(),
                'knowledge_sources' => $access->scope(KnowledgeSource::query(), $user)->count(),
                'conversations' => $access->scope(ChatConversation::query(), $user)->count(),
                'total_tokens' => (int) $usageQuery->sum('total_tokens'),
                'bot_token_usage' => $botTokenUsage,
                'usage_logs' => $usageQuery
                    ->with('bot:id,name')
                    ->latest()
                    ->limit(10)
                    ->get(['id', 'bot_id', 'type', 'model', 'input_tokens', 'output_tokens', 'total_tokens', 'error_message', 'created_at']),
            ],
        ]);
    }
}
