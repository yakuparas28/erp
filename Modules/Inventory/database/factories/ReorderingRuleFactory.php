<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ReorderingRule;

/**
 * @extends Factory<ReorderingRule>
 */
class ReorderingRuleFactory extends Factory
{
    protected $model = ReorderingRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'location_id' => Location::factory(),
            'min_qty' => '10.0000',
            'max_qty' => '50.0000',
            'trigger_type' => 'auto',
        ];
    }
}
