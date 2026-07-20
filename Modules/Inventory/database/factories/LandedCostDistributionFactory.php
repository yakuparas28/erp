<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\LandedCost;
use Modules\Inventory\Models\LandedCostDistribution;
use Modules\Inventory\Models\StockMove;

/**
 * @extends Factory<LandedCostDistribution>
 */
class LandedCostDistributionFactory extends Factory
{
    protected $model = LandedCostDistribution::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'landed_cost_id' => LandedCost::factory(),
            'stock_move_id' => StockMove::factory(),
            'allocated_amount' => '10.0000',
        ];
    }
}
