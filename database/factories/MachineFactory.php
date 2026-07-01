<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Machine>
 */
class MachineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->domainWord()),
            'host' => fake()->domainWord().'.lan',
            'description' => fake()->optional()->sentence(),
        ];
    }
}
