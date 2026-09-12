<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Tests\TenantTestCase;

class ConsignmentTest extends TenantTestCase
{
    public function test_stock_quant_can_have_an_owner_partner(): void
    {
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $partner = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id, 'name' => 'Konsinye Tedarikçi']);

        $quant = StockQuant::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'owner_partner_id' => $partner->id,
            'qty' => '25',
            'reserved_qty' => '0',
        ]);

        $this->assertSame($partner->id, $quant->ownerPartner->id);
    }

    public function test_consignment_report_groups_stock_by_partner(): void
    {
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id, 'name' => 'Konsinye Ürün']);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'name' => 'Ana Depo']);
        $partner = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id, 'name' => 'Konsinye Tedarikçi']);

        StockQuant::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'owner_partner_id' => $partner->id,
            'qty' => '15',
            'reserved_qty' => '0',
        ]);

        setPermissionsTeamId($this->tenant->id);
        $this->tenantAdmin->givePermissionTo('view stock');

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/reports/consignment')
            ->assertOk()
            ->assertSee('Konsinye Tedarikçi')
            ->assertSee('Konsinye Ürün')
            ->assertSee('15');
    }

    public function test_consignment_report_ignores_non_consignment_stock(): void
    {
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id, 'name' => 'Bizim Ürün']);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);

        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'owner_partner_id' => null,
            'qty' => '99',
        ]);

        setPermissionsTeamId($this->tenant->id);
        $this->tenantAdmin->givePermissionTo('view stock');

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/reports/consignment')
            ->assertOk()
            ->assertDontSee('Bizim Ürün');
    }
}
