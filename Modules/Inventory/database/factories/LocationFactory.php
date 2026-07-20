<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Warehouse;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'warehouse_id' => Warehouse::factory(),
            'parent_id' => null,
            'name' => 'Raf '.fake()->unique()->numberBetween(1, 9999),
            'type' => 'internal',
            'counting_lock' => false,
            'removal_strategy' => 'fifo',
        ];
    }
}
