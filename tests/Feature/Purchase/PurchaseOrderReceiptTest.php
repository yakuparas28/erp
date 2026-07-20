<?php

namespace Tests\Feature\Purchase;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\PutawayRule;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\StockValuationLayer;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Services\PurchaseOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class PurchaseOrderReceiptTest extends TenantTestCase
{
    private Partner $supplier;

    private Product $product;

    private Uom $unit;

    private Location $dock;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $this->supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'name' => 'Mal Kabul']);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);

        setPermissionsTeamId($this->tenant->id);
        $this->officer = User::factory()->for($this->tenant)->create();
        $this->officer->assignRole('Purchasing Officer');
    }

    private function service(): PurchaseOrderService
    {
        return app(PurchaseOrderService::class);
    }

    private function confirmedOrder(string $qty, string $unitPrice): PurchaseOrder
    {
        $po = $this->service()->create($this->tenant->id, $this->supplier->id, $this->officer);
        $this->service()->addLine($po, $this->product->id, $this->unit->id, $qty, $unitPrice);
        $this->service()->sendRfq($po);
        $this->service()->confirm($po->fresh(), $this->tenantAdmin);

        return $po->fresh();
    }

    public function test_receiving_creates_stock_move_and_valuation_layer_at_line_price(): void
    {
        $po = $this->confirmedOrder('10', '7.5000');
        $line = $po->lines()->firstOrFail();

        $this->service()->receive($line, '10', $this->dock->id);

        $this->assertSame('10.0000', StockQuant::where('location_id', $this->dock->id)->firstOrFail()->qty);
        $layer = StockValuationLayer::where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame('7.5000', $layer->unit_cost);
        $this->assertSame('10.0000', $line->receivedQty());
    }

    public function test_receiving_applies_putaway_destination_when_it_differs(): void
    {
        $shelf = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $this->dock->warehouse_id, 'name' => 'Raf B']);
        PutawayRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'source_location_id' => $this->dock->id, 'dest_location_id' => $shelf->id, 'sequence' => 1,
        ]);

        $po = $this->confirmedOrder('6', '4.0000');
        $line = $po->lines()->firstOrFail();

        $this->service()->receive($line, '6', $this->dock->id);

        $this->assertSame(0, StockQuant::where('location_id', $this->dock->id)->where('qty', '>', 0)->count());
        $this->assertSame('6.0000', StockQuant::where('location_id', $shelf->id)->firstOrFail()->qty);

        // Maliyet yalnızca bir kez kaydedilir (transfer değer yaratmaz).
        $this->assertSame(1, StockValuationLayer::where('product_id', $this->product->id)->count());
    }

    public function test_partial_receipts_accumulate_received_qty(): void
    {
        $po = $this->confirmedOrder('10', '2.0000');
        $line = $po->lines()->firstOrFail();

        $this->service()->receive($line, '4', $this->dock->id);
        $this->service()->receive($line, '6', $this->dock->id);

        $this->assertSame('10.0000', $line->receivedQty());
        $this->assertSame(2, StockValuationLayer::where('product_id', $this->product->id)->count());
    }

    public function test_cannot_receive_against_a_non_confirmed_order(): void
    {
        $po = $this->service()->create($this->tenant->id, $this->supplier->id, $this->officer);
        $this->service()->addLine($po, $this->product->id, $this->unit->id, '5', '2.0000');
        $line = $po->lines()->firstOrFail();

        try {
            $this->service()->receive($line, '5', $this->dock->id);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
