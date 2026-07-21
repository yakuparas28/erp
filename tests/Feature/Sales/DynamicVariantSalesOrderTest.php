<?php

namespace Tests\Feature\Sales;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeValue;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Services\VariantGeneratorService;
use Modules\Sales\Services\SalesOrderService;
use Tests\TenantTestCase;

class DynamicVariantSalesOrderTest extends TenantTestCase
{
    private Partner $customer;

    private Location $location;

    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $this->customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id, 'is_reference' => true]);
    }

    private function variantGenerator(): VariantGeneratorService
    {
        return app(VariantGeneratorService::class);
    }

    private function salesOrders(): SalesOrderService
    {
        return app(SalesOrderService::class);
    }

    public function test_dynamic_variant_is_resolved_lazily_and_used_on_a_sales_order_line(): void
    {
        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Deri Kemer', 'base_price' => '50']);
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Renk', 'creation_mode' => 'dynamic']);
        $value = ProductAttributeValue::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_attribute_id' => $attribute->id,
            'value' => 'Kahverengi',
            'price_extra' => '4',
        ]);
        $this->variantGenerator()->attachAttribute($template, $attribute);

        // Attach etmek henüz hiçbir varyant üretmez (dynamic = lazy).
        $this->assertSame(0, Product::where('product_template_id', $template->id)->count());

        $variant = $this->variantGenerator()->resolveOrCreateDynamic($template, [$value->id]);
        $price = $this->variantGenerator()->recommendedPrice($template, [$value->id]);

        $this->assertSame('54.0000', $price);
        $this->assertSame($template->id, $variant->product_template_id);

        $so = $this->salesOrders()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->tenantAdmin);
        $line = $this->salesOrders()->addLine($so, $variant->id, $this->unit->id, '2', $price);

        $this->assertSame($variant->id, $line->fresh()->product_id);
        $this->assertSame('54.0000', $line->fresh()->unit_price);

        // İkinci kez aynı attributeValueIds ile çağrıldığında yeni bir ürün
        // oluşturulmaz (idempotency regresyonu, bkz. VariantGeneratorTest).
        $again = $this->variantGenerator()->resolveOrCreateDynamic($template, [$value->id]);
        $this->assertTrue($variant->is($again));
        $this->assertSame(1, Product::where('product_template_id', $template->id)->count());
    }
}
