<?php

namespace Tests\Feature\Purchase;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ReorderingRule;
use Modules\Inventory\Models\ReplenishmentSuggestion;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\ReorderingService;
use Modules\Inventory\Services\StockMoveService;
use Modules\Purchase\Models\PurchaseOrder;
use Tests\TenantTestCase;

class ReplenishmentToDraftPurchaseOrderTest extends TenantTestCase
{
    private Location $location;

    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
    }

    public function test_acknowledging_a_suggestion_creates_a_draft_po_for_the_default_supplier(): void
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'default_supplier_id' => $supplier->id,
        ]);
        ReorderingRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $this->location->id,
            'min_qty' => '10', 'max_qty' => '50', 'trigger_type' => 'auto',
        ]);

        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $product,
            fromLocationId: null, toLocationId: $this->location->id,
            qty: '2', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 1,
        );

        $suggestion = ReplenishmentSuggestion::firstOrFail();
        app(ReorderingService::class)->acknowledge($suggestion, $this->tenantAdmin);

        $po = PurchaseOrder::where('partner_id', $supplier->id)->firstOrFail();
        $this->assertSame('draft', $po->status);
        $this->assertSame($this->tenantAdmin->id, $po->created_by);

        $line = $po->lines()->firstOrFail();
        $this->assertSame($product->id, $line->product_id);
        // max(50) - forecast(2) = 48
        $this->assertSame('48.0000', $line->qty);
    }

    public function test_no_draft_po_is_created_when_product_has_no_default_supplier(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        ReorderingRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $this->location->id,
            'min_qty' => '10', 'max_qty' => '50', 'trigger_type' => 'auto',
        ]);

        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $product,
            fromLocationId: null, toLocationId: $this->location->id,
            qty: '1', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 1,
        );

        $suggestion = ReplenishmentSuggestion::firstOrFail();
        app(ReorderingService::class)->acknowledge($suggestion, $this->tenantAdmin);

        $this->assertSame(0, PurchaseOrder::count());
    }
}
