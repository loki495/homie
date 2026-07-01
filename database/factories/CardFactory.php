<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CardType;
use App\Models\Card;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => null,
            'name' => ucfirst(fake()->word()),
            'type' => CardType::Link,
            'icon' => null,
            'color' => null,
            'url' => fake()->url(),
            'sort_order' => 0,
        ];
    }

    public function output(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CardType::Output,
            'url' => null,
        ]);
    }

    public function api(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CardType::Api,
        ]);
    }
}
