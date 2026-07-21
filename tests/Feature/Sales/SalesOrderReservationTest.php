<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Services\CostingService;
use Modules\Inventory\Services\StockMoveService;
use Modules\Sales\Services\SalesOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class SalesOrderReservationTest extends TenantTestCase
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
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id]);
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

    public function test_confirming_reserves_stock_for_trackable_product(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'lot_id' => null,
            'qty' => '50.0000',
            'reserved_qty' => '0.0000',
        ]);

        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
        $line = $this->service()->addLine($so, $product->id, $this->unit->id, '20', '5.0000');
        $this->service()->sendQuotation($so);

        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $quant = StockQuant::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertSame('20.0000', $quant->reserved_qty);
    }

    public function test_confirming_fails_when_stock_is_insufficient_and_reservation_is_rolled_back(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'lot_id' => null,
            'qty' => '5.0000',
            'reserved_qty' => '0.0000',
        ]);

        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
        $this->service()->addLine($so, $product->id, $this->unit->id, '20', '5.0000');
        $this->service()->sendQuotation($so);

        try {
            $this->service()->confirm($so->fresh(), $this->tenantAdmin);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertNotSame('confirmed', $so->fresh()->status);

        $quant = StockQuant::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertSame('0.0000', $quant->reserved_qty);
    }

    public function test_confirming_does_not_reserve_kit_products(): void
    {
        $kit = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->unit->id,
            'is_kit' => true,
        ]);

        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
        $this->service()->addLine($so, $kit->id, $this->unit->id, '5', '10.0000');
        $this->service()->sendQuotation($so);

        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $this->assertSame('confirmed', $so->fresh()->status);

        $quant = StockQuant::withoutGlobalScopes()
            ->where('product_id', $kit->id)
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertNull($quant);
    }

    public function test_confirming_does_not_reserve_service_products(): void
    {
        $service = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->unit->id,
            'product_type' => 'service',
            'cost_method' => 'standard',
        ]);

        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
        $this->service()->addLine($so, $service->id, $this->unit->id, '3', '15.0000');
        $this->service()->sendQuotation($so);

        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $this->assertSame('confirmed', $so->fresh()->status);

        $quant = StockQuant::withoutGlobalScopes()
            ->where('product_id', $service->id)
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertNull($quant);
    }

    public function test_cancelling_a_confirmed_order_releases_the_reservation(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'lot_id' => null,
            'qty' => '50.0000',
            'reserved_qty' => '0.0000',
        ]);

        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
        $this->service()->addLine($so, $product->id, $this->unit->id, '20', '5.0000');
        $this->service()->sendQuotation($so);
        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $this->service()->cancel($so->fresh());

        $this->assertSame('cancelled', $so->fresh()->status);

        $quant = StockQuant::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('location_id', $this->location->id)
            ->first();

        $this->assertSame('0.0000', $quant->reserved_qty);
    }

    public function test_cancelling_after_a_partial_delivery_releases_only_the_remaining_reservation(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'cost_method' => 'fifo']);
        $this->stockIn($product, '50', '5.0000');

        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
        $line = $this->service()->addLine($so, $product->id, $this->unit->id, '20', '7.0000');
        $this->service()->sendQuotation($so);
        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $quant = StockQuant::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('location_id', $this->location->id)
            ->firstOrFail();
        $this->assertSame('20.0000', $quant->reserved_qty);

        $this->service()->deliver($line, '8');

        $this->assertSame('12.0000', $quant->fresh()->reserved_qty);
        $this->assertSame('8.0000', $line->fresh()->delivered_qty);

        $this->service()->cancel($so->fresh());

        $this->assertSame('cancelled', $so->fresh()->status);
        $this->assertSame('0.0000', $quant->fresh()->reserved_qty);
        $this->assertSame('42.0000', $quant->fresh()->qty);
        $this->assertSame('8.0000', $line->fresh()->delivered_qty);
    }
}
