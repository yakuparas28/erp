<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductKitComponent;

/**
 * @extends Factory<ProductKitComponent>
 */
class ProductKitComponentFactory extends Factory
{
    protected $model = ProductKitComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'kit_product_id' => Product::factory(),
            'component_product_id' => Product::factory(),
            'qty' => '1.0000',
        ];
    }
}
