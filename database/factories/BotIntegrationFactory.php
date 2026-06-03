<?php

namespace Database\Factories;

use App\Enums\IntegrationType;
use App\Models\Bot;
use App\Models\BotIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BotIntegration>
 */
class BotIntegrationFactory extends Factory
{
    protected $model = BotIntegration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => IntegrationType::CreateLead,
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'config' => [],
            'credentials' => [],
            'allowed_domains' => [],
            'enabled' => true,
        ];
    }

    public function webhook(string $url = 'https://hooks.example.com/lead'): static
    {
        return $this->state(fn () => [
            'type' => IntegrationType::Webhook,
            'config' => ['url' => $url, 'method' => 'POST'],
            'allowed_domains' => ['hooks.example.com'],
        ]);
    }
}
