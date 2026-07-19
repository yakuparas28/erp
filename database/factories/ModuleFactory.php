<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(1),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_core' => false,
        ];
    }

    public function core(): static
    {
        return $this->state(fn (array $attributes): array => ['is_core' => true]);
    }
}
