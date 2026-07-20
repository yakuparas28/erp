<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductLot;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\StockMoveService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class StockMoveServiceTest extends TenantTestCase
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
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
    }

    private function service(): StockMoveService
    {
        return app(StockMoveService::class);
    }

    public function test_inbound_move_increments_quant_and_summary(): void
    {
        $this->service()->move(
            tenantId: $this->tenant->id,
            product: $this->product,
            fromLocationId: null,
            toLocationId: $this->location->id,
            qty: '5',
            uom: $this->unit,
            referenceType: 'inventory_adjustment',
            referenceId: 999,
        );

        $quant = StockQuant::where('product_id', $this->product->id)->where('location_id', $this->location->id)->firstOrFail();
        $this->assertSame('5.0000', $quant->qty);
        $this->assertSame('5.0000', $this->product->fresh()->current_stock);
    }

    public function test_outbound_move_decrements_quant(): void
    {
        $this->seedStock('10');

        $this->service()->move(
            tenantId: $this->tenant->id,
            product: $this->product,
            fromLocationId: $this->location->id,
            toLocationId: null,
            qty: '-4',
            uom: $this->unit,
            referenceType: 'inventory_adjustment',
            referenceId: 999,
        );

        $this->assertSame('6.0000', StockQuant::where('location_id', $this->location->id)->firstOrFail()->qty);
    }

    public function test_outbound_exceeding_available_stock_is_rejected(): void
    {
        $this->seedStock('3');

        try {
            $this->service()->move(
                tenantId: $this->tenant->id,
                product: $this->product,
                fromLocationId: $this->location->id,
                toLocationId: null,
                qty: '-5',
                uom: $this->unit,
                referenceType: 'inventory_adjustment',
                referenceId: 999,
            );
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_locked_location_rejects_moves_with_409(): void
    {
        $this->location->update(['counting_lock' => true]);

        try {
            $this->service()->move(
                tenantId: $this->tenant->id,
                product: $this->product,
                fromLocationId: null,
                toLocationId: $this->location->id,
                qty: '1',
                uom: $this->unit,
                referenceType: 'warehouse_transfer',
                referenceId: 1,
            );
            $this->fail('409 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_service_products_cannot_have_stock_moves(): void
    {
        $service = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->unit->id,
            'product_type' => 'service',
            'cost_method' => 'standard',
        ]);

        try {
            $this->service()->move(
                tenantId: $this->tenant->id,
                product: $service,
                fromLocationId: null,
                toLocationId: $this->location->id,
                qty: '1',
                uom: $this->unit,
                referenceType: 'inventory_adjustment',
                referenceId: 1,
            );
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_derived_uom_is_stored_in_reference_unit(): void
    {
        $box = Uom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $this->unit->uom_category_id,
            'name' => 'Koli',
            'factor' => '12.000000',
            'is_reference' => false,
        ]);

        $this->service()->move(
            tenantId: $this->tenant->id,
            product: $this->product,
            fromLocationId: null,
            toLocationId: $this->location->id,
            qty: '2',
            uom: $box,
            referenceType: 'inventory_adjustment',
            referenceId: 1,
        );

        $this->assertSame('24.0000', StockQuant::where('location_id', $this->location->id)->firstOrFail()->qty);
    }

    public function test_lot_tracked_product_requires_lot_and_separates_quants(): void
    {
        $tracked = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'track_by' => 'lot']);
        $lotA = ProductLot::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $tracked->id]);
        $lotB = ProductLot::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $tracked->id]);

        try {
            $this->service()->move(
                tenantId: $this->tenant->id, product: $tracked,
                fromLocationId: null, toLocationId: $this->location->id,
                qty: '1', uom: $this->unit,
                referenceType: 'inventory_adjustment', referenceId: 1,
            );
            $this->fail('lot zorunluluğu 422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        foreach ([[$lotA, '3'], [$lotB, '7']] as [$lot, $qty]) {
            $this->service()->move(
                tenantId: $this->tenant->id, product: $tracked,
                fromLocationId: null, toLocationId: $this->location->id,
                qty: $qty, uom: $this->unit, lotId: $lot->id,
                referenceType: 'inventory_adjustment', referenceId: 1,
            );
        }

        $this->assertSame(2, StockQuant::where('product_id', $tracked->id)->count());
        $this->assertSame('10.0000', $tracked->fresh()->current_stock);
    }

    private function seedStock(string $qty): void
    {
        $this->service()->move(
            tenantId: $this->tenant->id,
            product: $this->product,
            fromLocationId: null,
            toLocationId: $this->location->id,
            qty: $qty,
            uom: $this->unit,
            referenceType: 'inventory_adjustment',
            referenceId: 998,
        );
    }
}
