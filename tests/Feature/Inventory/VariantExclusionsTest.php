<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeExclusion;
use Modules\Inventory\Models\ProductAttributeValue;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Services\VariantGeneratorService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class VariantExclusionsTest extends TenantTestCase
{
    private ProductTemplate $template;

    private ProductAttributeValue $red;

    private ProductAttributeValue $xl;

    protected function setUp(): void
    {
        parent::setUp();

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true, 'factor' => '1']);

        $this->template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $color = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Renk', 'creation_mode' => 'dynamic']);
        $size = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beden', 'creation_mode' => 'dynamic']);

        $this->red = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $color->id, 'value' => 'Kırmızı']);
        $this->xl = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $size->id, 'value' => 'XL']);
    }

    public function test_dynamic_variant_creation_is_blocked_when_combination_is_excluded(): void
    {
        ProductAttributeExclusion::create([
            'tenant_id' => $this->tenant->id,
            'product_template_id' => $this->template->id,
            'product_attribute_value_id' => $this->red->id,
            'excluded_value_id' => $this->xl->id,
        ]);

        $this->expectException(HttpException::class);

        app(VariantGeneratorService::class)->resolveOrCreateDynamic($this->template, [$this->red->id, $this->xl->id]);
    }

    public function test_allowed_combination_creates_a_variant_normally(): void
    {
        $variant = app(VariantGeneratorService::class)->resolveOrCreateDynamic($this->template, [$this->red->id, $this->xl->id]);

        $this->assertSame($this->template->id, $variant->product_template_id);
    }

    public function test_template_scoped_exclusion_does_not_affect_other_templates(): void
    {
        $otherTemplate = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        ProductAttributeExclusion::create([
            'tenant_id' => $this->tenant->id,
            'product_template_id' => $this->template->id,
            'product_attribute_value_id' => $this->red->id,
            'excluded_value_id' => $this->xl->id,
        ]);

        $variant = app(VariantGeneratorService::class)->resolveOrCreateDynamic($otherTemplate, [$this->red->id, $this->xl->id]);
        $this->assertSame($otherTemplate->id, $variant->product_template_id);
    }

    public function test_global_exclusion_blocks_all_templates(): void
    {
        ProductAttributeExclusion::create([
            'tenant_id' => $this->tenant->id,
            'product_template_id' => null,
            'product_attribute_value_id' => $this->red->id,
            'excluded_value_id' => $this->xl->id,
        ]);

        $this->expectException(HttpException::class);

        app(VariantGeneratorService::class)->resolveOrCreateDynamic($this->template, [$this->red->id, $this->xl->id]);
    }

    public function test_exclusion_can_be_added_from_template_screen(): void
    {
        $this->actingAs($this->tenantAdmin)->post(
            route('app.inventory.templates.exclusions.store', $this->template),
            [
                'product_attribute_value_id' => $this->red->id,
                'excluded_value_id' => $this->xl->id,
            ],
        )->assertRedirect();

        $this->assertDatabaseHas('product_attribute_exclusions', [
            'tenant_id' => $this->tenant->id,
            'product_template_id' => $this->template->id,
            'product_attribute_value_id' => $this->red->id,
            'excluded_value_id' => $this->xl->id,
        ]);
    }

    public function test_exclusion_cannot_reference_itself(): void
    {
        $this->actingAs($this->tenantAdmin)->post(
            route('app.inventory.templates.exclusions.store', $this->template),
            [
                'product_attribute_value_id' => $this->red->id,
                'excluded_value_id' => $this->red->id,
            ],
        )->assertSessionHasErrors('excluded_value_id');
    }
}
