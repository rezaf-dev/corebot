<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

class TenantChatAgent implements Agent, Conversational
{
    use Promptable;

    /**
     * @param  Message[]  $messages
     */
    public function __construct(
        private readonly string $instructions,
        private readonly array $messages = [],
        private readonly ?float $temperature = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return $this->instructions;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    /**
     * Get the sampling temperature for the agent.
     */
    public function temperature(): ?float
    {
        return $this->temperature;
    }
}
