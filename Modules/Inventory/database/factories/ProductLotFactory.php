<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductLot;

/**
 * @extends Factory<ProductLot>
 */
class ProductLotFactory extends Factory
{
    protected $model = ProductLot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'lot_number' => strtoupper(fake()->unique()->bothify('LOT-####')),
            'expiry_date' => null,
        ];
    }
}
