<?php

namespace Tests\Feature\Purchase;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Services\InventoryDefaultsService;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Purchase\Services\PurchaseOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class PurchaseReturnTest extends TenantTestCase
{
    public function test_received_qty_can_be_returned_to_the_supplier(): void
    {
        app(InventoryDefaultsService::class)->provision($this->tenant);
        $stock = Location::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Stok')->firstOrFail();
        $uom = Uom::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('is_reference', true)->firstOrFail();
        $partner = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id]);

        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $partner->id,
            'status' => 'confirmed',
        ]);
        $line = PurchaseOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'qty' => '10',
            'unit_price' => '5.0000',
        ]);

        app(PurchaseOrderService::class)->receive($line, '10', $stock->id);
        $this->assertSame('10.0000', $product->fresh()->current_stock);

        app(PurchaseOrderService::class)->returnReceipt($line, '3', $stock->id);

        $this->assertSame('7.0000', $product->fresh()->current_stock);
        $this->assertSame(1, StockMove::where('reference_type', 'purchase_order_line_return')->count());
    }

    public function test_return_cannot_exceed_received_qty(): void
    {
        app(InventoryDefaultsService::class)->provision($this->tenant);
        $stock = Location::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Stok')->firstOrFail();
        $uom = Uom::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('is_reference', true)->firstOrFail();
        $partner = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id]);

        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $partner->id,
            'status' => 'confirmed',
        ]);
        $line = PurchaseOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'qty' => '10',
            'unit_price' => '5.0000',
        ]);

        app(PurchaseOrderService::class)->receive($line, '5', $stock->id);

        $this->expectException(HttpException::class);

        app(PurchaseOrderService::class)->returnReceipt($line, '6', $stock->id);
    }
}
