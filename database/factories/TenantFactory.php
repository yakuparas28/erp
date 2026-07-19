<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'accounting_mode' => 'continental',
        ];
    }

    public function angloSaxon(): static
    {
        return $this->state(fn (array $attributes): array => [
            'accounting_mode' => 'anglo_saxon',
        ]);
    }
}
