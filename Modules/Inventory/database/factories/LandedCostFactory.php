<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\LandedCost;

/**
 * @extends Factory<LandedCost>
 */
class LandedCostFactory extends Factory
{
    protected $model = LandedCost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'split_method' => 'by_quantity',
            'status' => 'draft',
        ];
    }
}
