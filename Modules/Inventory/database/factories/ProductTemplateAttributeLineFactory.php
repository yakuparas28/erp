<?php

namespace Modules\Inventory\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\ProductTemplateAttributeLine;

/**
 * @extends Factory<ProductTemplateAttributeLine>
 */
class ProductTemplateAttributeLineFactory extends Factory
{
    protected $model = ProductTemplateAttributeLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_template_id' => ProductTemplate::factory(),
            'product_attribute_id' => ProductAttribute::factory(),
        ];
    }
}
