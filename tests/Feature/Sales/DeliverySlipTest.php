<?php

namespace Tests\Feature\Sales;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Services\InventoryDefaultsService;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Tests\TenantTestCase;

class DeliverySlipTest extends TenantTestCase
{
    public function test_delivery_slip_renders_customer_and_items(): void
    {
        app(InventoryDefaultsService::class)->provision($this->tenant);
        $stock = Location::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Stok')->firstOrFail();
        $uom = Uom::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('is_reference', true)->firstOrFail();
        $partner = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id, 'name' => 'ABC Ltd.']);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id, 'name' => 'Test Ürünü']);

        $so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $partner->id,
            'location_id' => $stock->id,
            'status' => 'confirmed',
            'created_by' => $this->tenantAdmin->id,
        ]);
        SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'qty' => '5',
            'delivered_qty' => '5',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get(route('app.sales.orders.delivery-slip', $so))
            ->assertOk()
            ->assertSee('ABC Ltd.')
            ->assertSee('Test Ürünü')
            ->assertSee('SO-'.$so->id);
    }
}
