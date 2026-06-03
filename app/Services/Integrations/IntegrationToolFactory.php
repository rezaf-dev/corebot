<?php

namespace App\Services\Integrations;

use App\Ai\Tools\IntegrationTool;
use App\Models\Bot;
use App\Models\ChatConversation;
use Laravel\Ai\Contracts\Tool;

class IntegrationToolFactory
{
    public function __construct(private readonly IntegrationExecutor $executor) {}

    /**
     * @return list<Tool>
     */
    public function forConversation(Bot $bot, ChatConversation $conversation): array
    {
        $bot->loadMissing('integrations');

        return $bot->integrations
            ->where('enabled', true)
            ->map(fn ($integration) => new IntegrationTool(
                $integration,
                $bot,
                $conversation,
                $this->executor,
            ))
            ->values()
            ->all();
    }
}
