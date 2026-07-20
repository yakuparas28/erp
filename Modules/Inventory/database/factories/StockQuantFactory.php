<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\StockQuant;

/**
 * @extends Factory<StockQuant>
 */
class StockQuantFactory extends Factory
{
    protected $model = StockQuant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'location_id' => Location::factory(),
            'lot_id' => null,
            'qty' => '0.0000',
            'reserved_qty' => '0.0000',
        ];
    }
}
