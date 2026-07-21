<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductKitComponent;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\StockValuationLayer;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\CostingService;
use Modules\Inventory\Services\StockMoveService;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class SalesOrderDeliveryTest extends TenantTestCase
{
    private Partner $customer;

    private Location $location;

    private Uom $unit;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $this->customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);

        setPermissionsTeamId($this->tenant->id);
        $this->rep = User::factory()->for($this->tenant)->create();
        $this->rep->assignRole('Sales Representative');
    }

    private function service(): SalesOrderService
    {
        return app(SalesOrderService::class);
    }

    private function stockIn(Product $product, string $qty, string $unitCost): void
    {
        $move = app(StockMoveService::class)->move(
            tenantId: $this->tenant->id,
            product: $product,
            fromLocationId: null,
            toLocationId: $this->location->id,
            qty: $qty,
            uom: $this->unit,
            referenceType: 'inventory_adjustment',
            referenceId: 1,
        );

        app(CostingService::class)->recordInbound($product, $move, $qty, $unitCost);
    }

    private function newOrder(): SalesOrder
    {
        return $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
    }

    public function test_delivering_a_normal_product_creates_outbound_move_cogs_and_releases_reservation(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $this->stockIn($product, '50', '5.0000');

        $so = $this->newOrder();
        $line = $this->service()->addLine($so, $product->id, $this->unit->id, '20', '7.0000');
        $this->service()->sendQuotation($so);
        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $this->service()->deliver($line, '20');

        $move = StockMove::where('reference_type', 'sales_order_line')->where('reference_id', $line->id)->firstOrFail();
        $this->assertSame('-20.0000', $move->qty);

        $layer = StockValuationLayer::where('product_id', $product->id)->firstOrFail();
        $this->assertSame('150.0000', $layer->remaining_value);

        $quant = StockQuant::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('location_id', $this->location->id)
            ->firstOrFail();
        $this->assertSame('0.0000', $quant->reserved_qty);

        $this->assertSame('20.0000', $line->fresh()->delivered_qty);
        $this->assertSame('done', $so->fresh()->status);
    }

    public function test_delivering_a_kit_line_explodes_into_components_without_moving_the_kit_itself(): void
    {
        $componentA = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $componentB = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $kit = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'is_kit' => true]);

        ProductKitComponent::factory()->create([
            'tenant_id' => $this->tenant->id, 'kit_product_id' => $kit->id,
            'component_product_id' => $componentA->id, 'qty' => '1',
        ]);
        ProductKitComponent::factory()->create([
            'tenant_id' => $this->tenant->id, 'kit_product_id' => $kit->id,
            'component_product_id' => $componentB->id, 'qty' => '2',
        ]);

        $this->stockIn($componentA, '10', '3.0000');
        $this->stockIn($componentB, '10', '4.0000');

        $so = $this->newOrder();
        $line = $this->service()->addLine($so, $kit->id, $this->unit->id, '3', '20.0000');
        $this->service()->sendQuotation($so);
        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $this->service()->deliver($line, '3');

        $this->assertSame(0, StockMove::where('product_id', $kit->id)->count());

        $moveA = StockMove::where('product_id', $componentA->id)
            ->where('reference_type', 'sales_order_line')->where('reference_id', $line->id)->firstOrFail();
        $this->assertSame('-3.0000', $moveA->qty);

        $moveB = StockMove::where('product_id', $componentB->id)
            ->where('reference_type', 'sales_order_line')->where('reference_id', $line->id)->firstOrFail();
        $this->assertSame('-6.0000', $moveB->qty);

        $layerA = StockValuationLayer::where('product_id', $componentA->id)->firstOrFail();
        $this->assertSame('21.0000', $layerA->remaining_value); // (10-3) * 3

        $layerB = StockValuationLayer::where('product_id', $componentB->id)->firstOrFail();
        $this->assertSame('16.0000', $layerB->remaining_value); // (10-6) * 4

        $this->assertSame('3.0000', $line->fresh()->delivered_qty);
        $this->assertSame('done', $so->fresh()->status);
    }

    public function test_delivering_a_service_line_produces_no_stock_moves(): void
    {
        $service = Product::factory()->create([
            'tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id,
            'product_type' => 'service', 'cost_method' => 'standard',
        ]);

        $so = $this->newOrder();
        $line = $this->service()->addLine($so, $service->id, $this->unit->id, '3', '15.0000');
        $this->service()->sendQuotation($so);
        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $this->service()->deliver($line, '3');

        $this->assertSame(0, StockMove::where('product_id', $service->id)->count());
        $this->assertSame('3.0000', $line->fresh()->delivered_qty);
        $this->assertSame('done', $so->fresh()->status);
    }

    public function test_sales_order_only_becomes_done_once_every_line_is_fully_delivered(): void
    {
        $productA = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $productB = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $this->stockIn($productA, '50', '5.0000');
        $this->stockIn($productB, '50', '5.0000');

        $so = $this->newOrder();
        $lineA = $this->service()->addLine($so, $productA->id, $this->unit->id, '10', '7.0000');
        $lineB = $this->service()->addLine($so, $productB->id, $this->unit->id, '10', '7.0000');
        $this->service()->sendQuotation($so);
        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $this->service()->deliver($lineA, '10');

        $this->assertNotSame('done', $so->fresh()->status);

        $this->service()->deliver($lineB, '10');

        $this->assertSame('done', $so->fresh()->status);
    }

    public function test_delivering_more_than_the_remaining_ordered_quantity_fails(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $this->stockIn($product, '50', '5.0000');

        $so = $this->newOrder();
        $line = $this->service()->addLine($so, $product->id, $this->unit->id, '10', '7.0000');
        $this->service()->sendQuotation($so);
        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $this->service()->deliver($line, '6');

        try {
            $this->service()->deliver($line, '5');
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame('6.0000', $line->fresh()->delivered_qty);
    }

    public function test_delivering_against_a_non_confirmed_sales_order_fails(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $this->stockIn($product, '50', '5.0000');

        $so = $this->newOrder();
        $line = $this->service()->addLine($so, $product->id, $this->unit->id, '10', '7.0000');
        $this->service()->sendQuotation($so);

        try {
            $this->service()->deliver($line, '5');
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
