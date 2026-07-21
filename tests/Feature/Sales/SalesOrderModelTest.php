<?php

namespace Tests\Feature\Sales;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Tests\TenantTestCase;

class SalesOrderModelTest extends TenantTestCase
{
    public function test_sales_order_has_partner_location_creator_and_lines(): void
    {
        $customer = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = Location::factory()->create(['tenant_id' => $this->tenant->id]);
        $so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $customer->id,
            'location_id' => $location->id,
            'created_by' => $this->tenantAdmin->id,
        ]);
        SalesOrderLine::factory()->create(['tenant_id' => $this->tenant->id, 'sales_order_id' => $so->id]);

        $this->assertTrue($so->partner->is($customer));
        $this->assertTrue($so->location->is($location));
        $this->assertTrue($so->creator->is($this->tenantAdmin));
        $this->assertCount(1, $so->lines);
    }

    public function test_sales_order_line_has_sales_order_product_and_uom(): void
    {
        $so = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id]);
        $line = SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'product_id' => $product->id,
            'uom_id' => $unit->id,
        ]);

        $this->assertTrue($line->salesOrder->is($so));
        $this->assertTrue($line->product->is($product));
        $this->assertTrue($line->uom->is($unit));
    }

    public function test_sales_order_line_delivered_qty_defaults_to_zero(): void
    {
        $line = SalesOrderLine::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertSame('0.0000', $line->delivered_qty);
    }
}
