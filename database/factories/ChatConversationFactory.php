<?php

namespace Database\Factories;

use App\Models\Bot;
use App\Models\ChatConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bot_id' => Bot::factory(), 'tenant_id' => fn (array $attributes) => Bot::findOrFail($attributes['bot_id'])->tenant_id, 'status' => 'open', 'support_mode' => 'ai',
        ];
    }
}
