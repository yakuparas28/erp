<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\InventoryAdjustmentLine;
use Modules\Inventory\Models\Product;

/**
 * @extends Factory<InventoryAdjustmentLine>
 */
class InventoryAdjustmentLineFactory extends Factory
{
    protected $model = InventoryAdjustmentLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'inventory_adjustment_id' => InventoryAdjustment::factory(),
            'product_id' => Product::factory(),
            'lot_id' => null,
            'counted_qty' => '0.0000',
            'theoretical_qty' => null,
        ];
    }
}
