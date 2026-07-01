<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardOutput;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardOutput>
 */
class CardOutputFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory()->state(['type' => CardType::Output]),
            'command' => 'uptime',
            'last_output' => null,
            'last_exit_code' => null,
            'last_run_at' => null,
        ];
    }
}
