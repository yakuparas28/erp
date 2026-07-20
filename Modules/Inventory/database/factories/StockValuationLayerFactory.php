<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockValuationLayer;

/**
 * @extends Factory<StockValuationLayer>
 */
class StockValuationLayerFactory extends Factory
{
    protected $model = StockValuationLayer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'stock_move_id' => StockMove::factory(),
            'qty' => '1.0000',
            'unit_cost' => '10.0000',
            'remaining_value' => '10.0000',
        ];
    }
}
