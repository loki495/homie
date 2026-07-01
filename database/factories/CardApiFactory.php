<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardApi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardApi>
 */
class CardApiFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory()->state(['type' => CardType::Api]),
            'provider' => ApiProvider::Generic,
            'base_url' => fake()->url(),
            'auth_type' => 'api_key',
            'api_key' => fake()->uuid(),
            'username' => null,
            'password' => null,
            'cached_data' => null,
            'last_fetched_at' => null,
        ];
    }
}
