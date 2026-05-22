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

        return Inertia::render('Dashboard', [
            'stats' => [
                'tenants' => $user->isSuperAdmin() ? Tenant::count() : null,
                'bots' => $access->scope(Bot::query(), $user)->count(),
                'knowledge_sources' => $access->scope(KnowledgeSource::query(), $user)->count(),
                'conversations' => $access->scope(ChatConversation::query(), $user)->count(),
                'usage_logs' => $access->scope(AiUsageLog::query(), $user)->latest()->limit(10)->get(),
            ],
        ]);
    }
}
