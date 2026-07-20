<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Partner;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'tax_number' => fake()->numerify('##########'),
            'is_customer' => false,
            'is_supplier' => true,
            'payment_term_days' => 30,
        ];
    }

    public function customer(): static
    {
        return $this->state(fn () => ['is_customer' => true, 'is_supplier' => false]);
    }

    public function supplier(): static
    {
        return $this->state(fn () => ['is_customer' => false, 'is_supplier' => true]);
    }
}
