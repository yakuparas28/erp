<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeValue;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Services\VariantGeneratorService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class VariantGeneratorTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id, 'is_reference' => true]);
    }

    private function service(): VariantGeneratorService
    {
        return app(VariantGeneratorService::class);
    }

    public function test_instant_attribute_generates_all_combinations(): void
    {
        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Pamuklu Tişört', 'base_price' => '100']);
        $color = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Renk', 'creation_mode' => 'instant']);
        $red = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $color->id, 'value' => 'Kırmızı', 'price_extra' => '3']);
        $blue = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $color->id, 'value' => 'Mavi']);
        $size = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beden', 'creation_mode' => 'instant']);
        $sizeS = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $size->id, 'value' => 'S']);
        $sizeM = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $size->id, 'value' => 'M']);
        $sizeL = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $size->id, 'value' => 'L']);

        $this->service()->attachAttribute($template, $color);
        $this->service()->attachAttribute($template, $size);

        $variants = Product::where('product_template_id', $template->id)->get();
        $this->assertCount(6, $variants); // 2 renk x 3 beden

        $redM = $variants->first(fn (Product $p) => $p->name === 'Pamuklu Tişört Kırmızı / M');
        $this->assertNotNull($redM);
        $this->assertSame('103.0000', (string) $this->service()->recommendedPrice($template, [$red->id, $sizeM->id]));

        $blueS = $variants->first(fn (Product $p) => $p->name === 'Pamuklu Tişört Mavi / S');
        $this->assertSame('100.0000', (string) $this->service()->recommendedPrice($template, [$blue->id, $sizeS->id]));
    }

    public function test_dynamic_attribute_does_not_auto_generate(): void
    {
        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'creation_mode' => 'dynamic']);
        ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id]);

        $this->service()->attachAttribute($template, $attribute);

        $this->assertSame(0, Product::where('product_template_id', $template->id)->count());
    }

    public function test_dynamic_variant_is_created_lazily_on_first_selection(): void
    {
        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'creation_mode' => 'dynamic']);
        $value = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id]);
        $this->service()->attachAttribute($template, $attribute);

        $variant = $this->service()->resolveOrCreateDynamic($template, [$value->id]);
        $again = $this->service()->resolveOrCreateDynamic($template, [$value->id]);

        $this->assertTrue($variant->is($again));
        $this->assertSame(1, Product::where('product_template_id', $template->id)->count());
    }

    public function test_creation_mode_is_locked_after_attaching_to_a_template(): void
    {
        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'creation_mode' => 'instant']);
        $this->service()->attachAttribute($template, $attribute);

        try {
            $this->service()->updateCreationMode($attribute, 'dynamic');
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame('instant', $attribute->fresh()->creation_mode);
    }

    public function test_creation_mode_can_change_before_attaching(): void
    {
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'creation_mode' => 'instant']);

        $this->service()->updateCreationMode($attribute, 'never');

        $this->assertSame('never', $attribute->fresh()->creation_mode);
    }
}
