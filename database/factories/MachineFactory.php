<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DiscoveryMethod;
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
            'discovery_method' => DiscoveryMethod::Docker,
            'ssh_user' => null,
            'ssh_port' => null,
            'ssh_private_key' => null,
        ];
    }

    public function ssh(): static
    {
        return $this->state(fn (array $attributes): array => [
            'discovery_method' => DiscoveryMethod::Ssh,
            'ssh_user' => 'root',
        ]);
    }
}
