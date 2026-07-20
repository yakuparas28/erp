<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Jobs\GenerateInstantVariants;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeValue;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Services\VariantGeneratorService;
use Tests\TenantTestCase;

class TemplateAndAttributeDeletionTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);
    }

    public function test_empty_template_can_be_deleted(): void
    {
        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rewarderz']);

        $this->delete("/app/inventory/templates/{$template->id}")->assertRedirect();

        $this->assertDatabaseMissing('product_templates', ['id' => $template->id]);
    }

    public function test_template_with_variants_cannot_be_deleted(): void
    {
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id, 'is_reference' => true]);

        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'creation_mode' => 'instant']);
        ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id]);
        app(VariantGeneratorService::class)->attachAttribute($template, $attribute);
        (new GenerateInstantVariants($this->tenant->id, $template->id))->handle();

        $this->assertGreaterThan(0, Product::where('product_template_id', $template->id)->count());

        $this->from('/app/inventory/templates')
            ->delete("/app/inventory/templates/{$template->id}")
            ->assertRedirect('/app/inventory/templates')
            ->assertSessionHasErrors('template');

        $this->assertDatabaseHas('product_templates', ['id' => $template->id]);
    }

    public function test_unattached_attribute_can_be_deleted_with_its_values(): void
    {
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'sdsd']);
        $value = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id]);

        $this->delete("/app/inventory/attributes/{$attribute->id}")->assertRedirect();

        $this->assertDatabaseMissing('product_attributes', ['id' => $attribute->id]);
        $this->assertDatabaseMissing('product_attribute_values', ['id' => $value->id]);
    }

    public function test_unused_attribute_value_can_be_deleted(): void
    {
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id]);
        $value = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id, 'value' => 'gg']);

        $this->delete("/app/inventory/attribute-values/{$value->id}")->assertRedirect();

        $this->assertDatabaseMissing('product_attribute_values', ['id' => $value->id]);
    }

    public function test_attribute_value_used_by_a_variant_cannot_be_deleted(): void
    {
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id, 'is_reference' => true]);

        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'creation_mode' => 'instant']);
        $value = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id]);
        app(VariantGeneratorService::class)->attachAttribute($template, $attribute);
        (new GenerateInstantVariants($this->tenant->id, $template->id))->handle();

        $this->from('/app/inventory/templates')
            ->delete("/app/inventory/attribute-values/{$value->id}")
            ->assertRedirect('/app/inventory/templates')
            ->assertSessionHasErrors('value');

        $this->assertDatabaseHas('product_attribute_values', ['id' => $value->id]);
    }

    public function test_attribute_attached_to_a_template_cannot_be_deleted(): void
    {
        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id]);
        app(VariantGeneratorService::class)->attachAttribute($template, $attribute);

        $this->from('/app/inventory/templates')
            ->delete("/app/inventory/attributes/{$attribute->id}")
            ->assertRedirect('/app/inventory/templates')
            ->assertSessionHasErrors('attribute');

        $this->assertDatabaseHas('product_attributes', ['id' => $attribute->id]);
    }
}
