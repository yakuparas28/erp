<?php

namespace Tests\Feature\Sales;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Services\InventoryDefaultsService;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Services\SalesOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class SalesReturnTest extends TenantTestCase
{
    public function test_delivered_qty_can_be_returned_from_customer(): void
    {
        app(InventoryDefaultsService::class)->provision($this->tenant);
        $stock = Location::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Stok')->firstOrFail();
        $uom = Uom::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('is_reference', true)->firstOrFail();
        $partner = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id]);

        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'location_id' => $stock->id,
            'qty' => '20',
        ]);

        $so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $partner->id,
            'location_id' => $stock->id,
            'status' => 'confirmed',
            'created_by' => $this->tenantAdmin->id,
        ]);
        $line = SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'qty' => '10',
            'unit_price' => '5.0000',
            'delivered_qty' => '0',
        ]);

        app(SalesOrderService::class)->deliver($line, '10');
        $so->refresh();
        $this->assertSame('done', $so->status);
        $this->assertSame('10.0000', $product->fresh()->current_stock);

        app(SalesOrderService::class)->returnDelivery($line->fresh(), '4', $stock->id);

        $this->assertSame('14.0000', $product->fresh()->current_stock);
        $this->assertSame('6.0000', $line->fresh()->delivered_qty);
        $this->assertSame('confirmed', $so->fresh()->status);
    }

    public function test_return_cannot_exceed_delivered_qty(): void
    {
        app(InventoryDefaultsService::class)->provision($this->tenant);
        $stock = Location::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Stok')->firstOrFail();
        $uom = Uom::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('is_reference', true)->firstOrFail();
        $partner = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id]);

        $so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $partner->id,
            'location_id' => $stock->id,
            'status' => 'confirmed',
            'created_by' => $this->tenantAdmin->id,
        ]);
        $line = SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'qty' => '10',
            'delivered_qty' => '3',
        ]);

        $this->expectException(HttpException::class);

        app(SalesOrderService::class)->returnDelivery($line, '5', $stock->id);
    }
}
