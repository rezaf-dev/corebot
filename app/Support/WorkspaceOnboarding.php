<?php

namespace App\Support;

use App\Models\User;

class WorkspaceOnboarding
{
    /** @return array{steps: list<array{key: string, title: string, description: string, url: string, complete: bool, available: bool}>, completed: int, total: int}|null */
    public function forUser(?User $user): ?array
    {
        if (! $user?->isTenantAdmin() || ! $user->hasWorkspaceAccess()) {
            return null;
        }

        $tenant = $user->tenant;
        $aiReady = $tenant->aiSetting()->first()?->canUseAi() ?? false;
        $bot = $tenant->bots()->where('status', 'active')->orderBy('id')->first(['id', 'widget_config']);
        $knowledgeReady = $bot?->knowledgeSources()->where('status', 'ready')->exists() ?? false;
        $widgetReady = filled($bot?->widget_config);

        $steps = [
            ['key' => 'ai', 'title' => 'Connect AI', 'description' => 'Save your API key, then test and activate AI.', 'url' => route('ai-settings.edit'), 'complete' => $aiReady, 'available' => true],
            ['key' => 'bot', 'title' => 'Create a bot', 'description' => 'Name and activate your bot. Default messages are included.', 'url' => $bot ? route('bots.edit', $bot->id) : route('bots.create'), 'complete' => $bot !== null, 'available' => true],
            ['key' => 'knowledge', 'title' => 'Add knowledge', 'description' => 'Add knowledge to your bot and wait until it is ready.', 'url' => route('knowledge-sources.index'), 'complete' => $knowledgeReady, 'available' => $aiReady && $bot !== null],
            ['key' => 'widget', 'title' => 'Set up your widget', 'description' => 'Save widget settings and embed it on your website.', 'url' => route('widget.install'), 'complete' => $widgetReady, 'available' => $bot !== null],
        ];
        $completed = count(array_filter($steps, fn (array $step): bool => $step['complete']));

        return $completed === count($steps) ? null : ['steps' => $steps, 'completed' => $completed, 'total' => count($steps)];
    }
}
