<?php

namespace Modules\Hr\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hr\Models\ConsumptionRule;

class ConsumptionRuleFactory extends Factory
{
    protected $model = ConsumptionRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => 'BR-IT-'.fake()->numberBetween(1, 99),
            'category' => fake()->randomElement(['consumption', 'accrual']),
            'name' => fake()->sentence(4),
            'is_active' => true,
        ];
    }
}
