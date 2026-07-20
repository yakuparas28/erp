<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\Uom;

/**
 * @extends Factory<StockMove>
 */
class StockMoveFactory extends Factory
{
    protected $model = StockMove::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'from_location_id' => null,
            'to_location_id' => Location::factory(),
            'uom_id' => Uom::factory(),
            'lot_id' => null,
            'qty' => '1.0000',
            'reference_type' => 'inventory_adjustment',
            'reference_id' => 1,
        ];
    }
}
