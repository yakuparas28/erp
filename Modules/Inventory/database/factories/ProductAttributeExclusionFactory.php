<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\ProductAttributeExclusion;

/**
 * @extends Factory<ProductAttributeExclusion>
 */
class ProductAttributeExclusionFactory extends Factory
{
    protected $model = ProductAttributeExclusion::class;

    public function definition(): array
    {
        return [
            'product_template_id' => null,
        ];
    }
}
