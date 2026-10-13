<?php

namespace Database\Factories;

use App\Models\Bot;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bot>
 */
class BotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(), 'name' => fake()->word(), 'status' => 'active',
        ];
    }
}
