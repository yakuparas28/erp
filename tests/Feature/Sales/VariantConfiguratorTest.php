<?php

namespace Tests\Feature\Sales;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeValue;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Sales\Models\SalesOrder;
use Tests\TenantTestCase;

class VariantConfiguratorTest extends TenantTestCase
{
    public function test_configurator_creates_dynamic_variant_and_adds_it_to_the_order(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $this->tenantAdmin->givePermissionTo('create sales orders');

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true, 'factor' => '1']);

        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'creation_mode' => 'dynamic']);
        $value = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id, 'is_custom' => true]);

        $partner = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $location = Location::factory()->create(['tenant_id' => $this->tenant->id]);
        $so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $partner->id,
            'location_id' => $location->id,
            'status' => 'draft',
            'created_by' => $this->tenantAdmin->id,
        ]);

        $this->actingAs($this->tenantAdmin)->post(route('app.sales.orders.lines.configure', $so), [
            'product_template_id' => $template->id,
            'attribute_value_ids' => [$value->id],
            'uom_id' => $uom->id,
            'qty' => '2',
            'unit_price' => '150',
            'custom_values' => [$value->id => 'Adı: Ahmet'],
        ])->assertRedirect();

        $so->refresh();
        $this->assertCount(1, $so->lines);

        $line = $so->lines->first();
        $this->assertSame(['Adı: Ahmet'], array_values($line->custom_values));
        $this->assertSame($template->id, $line->product->product_template_id);
    }
}
