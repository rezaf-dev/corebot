<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

class TenantChatAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  Message[]  $messages
     * @param  Tool[]  $tools
     */
    public function __construct(
        private readonly string $instructions,
        private readonly array $messages = [],
        private readonly array $tools = [],
        private readonly ?float $temperature = null,
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return $this->tools;
    }

    public function temperature(): ?float
    {
        return $this->temperature;
    }
}
