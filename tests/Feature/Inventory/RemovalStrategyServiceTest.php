<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductLot;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\RemovalStrategyService;
use Modules\Inventory\Services\StockMoveService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class RemovalStrategyServiceTest extends TenantTestCase
{
    private Location $location;

    private Product $product;

    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'track_by' => 'lot']);
    }

    private function receive(string $lotNumber, ?string $expiryDate, string $qty): ProductLot
    {
        $lot = ProductLot::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'lot_number' => $lotNumber, 'expiry_date' => $expiryDate,
        ]);

        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $this->product,
            fromLocationId: null, toLocationId: $this->location->id,
            qty: $qty, uom: $this->unit, lotId: $lot->id,
            referenceType: 'inventory_adjustment', referenceId: 1,
        );

        return $lot;
    }

    public function test_fefo_selects_the_lot_with_the_nearest_expiry_date(): void
    {
        $this->location->update(['removal_strategy' => 'fefo']);

        $this->receive('LOT-FAR', now()->addDays(60)->toDateString(), '10');
        $lotNear = $this->receive('LOT-NEAR', now()->addDays(5)->toDateString(), '10');

        $selected = app(RemovalStrategyService::class)->selectLot($this->product, $this->location->id);

        $this->assertSame($lotNear->id, $selected);
    }

    public function test_lifo_selects_the_most_recently_received_lot(): void
    {
        $this->location->update(['removal_strategy' => 'lifo']);

        $this->receive('LOT-OLD', null, '10');
        $lotNew = $this->receive('LOT-NEW', null, '10');

        $this->assertSame($lotNew->id, app(RemovalStrategyService::class)->selectLot($this->product, $this->location->id));
    }

    public function test_fifo_selects_the_earliest_received_lot(): void
    {
        $this->location->update(['removal_strategy' => 'fifo']);

        $lotOld = $this->receive('LOT-OLD', null, '10');
        $this->receive('LOT-NEW', null, '10');

        $this->assertSame($lotOld->id, app(RemovalStrategyService::class)->selectLot($this->product, $this->location->id));
    }

    public function test_returns_null_when_no_stock_available(): void
    {
        $this->assertNull(app(RemovalStrategyService::class)->selectLot($this->product, $this->location->id));
    }

    public function test_outbound_move_without_lot_auto_resolves_via_removal_strategy(): void
    {
        $this->location->update(['removal_strategy' => 'fefo']);
        $lotNear = $this->receive('LOT-NEAR', now()->addDays(3)->toDateString(), '10');
        $this->receive('LOT-FAR', now()->addDays(90)->toDateString(), '10');

        $move = app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $this->product,
            fromLocationId: $this->location->id, toLocationId: null,
            qty: '-4', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 2,
        );

        $this->assertSame($lotNear->id, $move->lot_id);
        $this->assertSame('6.0000', StockQuant::where('lot_id', $lotNear->id)->firstOrFail()->qty);
    }

    public function test_inbound_still_requires_explicit_lot_regression(): void
    {
        try {
            app(StockMoveService::class)->move(
                tenantId: $this->tenant->id, product: $this->product,
                fromLocationId: null, toLocationId: $this->location->id,
                qty: '1', uom: $this->unit,
                referenceType: 'inventory_adjustment', referenceId: 3,
            );
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_outbound_without_any_stock_and_no_lot_still_aborts_422(): void
    {
        try {
            app(StockMoveService::class)->move(
                tenantId: $this->tenant->id, product: $this->product,
                fromLocationId: $this->location->id, toLocationId: null,
                qty: '-1', uom: $this->unit,
                referenceType: 'inventory_adjustment', referenceId: 4,
            );
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
