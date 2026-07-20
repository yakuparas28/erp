<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\LandedCost;
use Modules\Inventory\Models\LandedCostLine;

/**
 * @extends Factory<LandedCostLine>
 */
class LandedCostLineFactory extends Factory
{
    protected $model = LandedCostLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'landed_cost_id' => LandedCost::factory(),
            'description' => 'Navlun',
            'amount' => '100.0000',
        ];
    }
}
