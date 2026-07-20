<?php

namespace Tests\Feature\Purchase;

use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Tests\TenantTestCase;

class PurchaseOrderModelTest extends TenantTestCase
{
    public function test_purchase_order_has_partner_creator_and_lines(): void
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id, 'partner_id' => $supplier->id, 'created_by' => $this->tenantAdmin->id,
        ]);
        PurchaseOrderLine::factory()->create(['tenant_id' => $this->tenant->id, 'purchase_order_id' => $po->id]);

        $this->assertTrue($po->partner->is($supplier));
        $this->assertTrue($po->creator->is($this->tenantAdmin));
        $this->assertCount(1, $po->lines);
    }

    public function test_received_qty_is_zero_without_any_receipt_moves(): void
    {
        $line = PurchaseOrderLine::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertSame('0.0000', $line->receivedQty());
    }

    public function test_received_qty_sums_moves_referencing_the_line(): void
    {
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id]);
        $line = PurchaseOrderLine::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id]);

        foreach (['3', '4'] as $qty) {
            $move = new StockMove([
                'product_id' => $product->id, 'from_location_id' => null, 'to_location_id' => null,
                'uom_id' => $unit->id, 'qty' => $qty,
                'reference_type' => 'purchase_order_line', 'reference_id' => $line->id,
            ]);
            $move->tenant_id = $this->tenant->id;
            $move->save();
        }

        $this->assertSame('7.0000', $line->receivedQty());
    }
}
