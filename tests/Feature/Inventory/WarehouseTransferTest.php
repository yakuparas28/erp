<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseTransfer;
use Modules\Inventory\Models\WarehouseTransferLine;
use Modules\Inventory\Services\StockMoveService;
use Modules\Inventory\Services\WarehouseTransferService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class WarehouseTransferTest extends TenantTestCase
{
    private Location $source;

    private Location $destination;

    private Product $product;

    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->source = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $this->destination = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);

        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $this->product,
            fromLocationId: null, toLocationId: $this->source->id,
            qty: '10', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 990,
        );
    }

    private function makeTransfer(string $qty): WarehouseTransfer
    {
        $transfer = WarehouseTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'from_location_id' => $this->source->id,
            'to_location_id' => $this->destination->id,
            'created_by' => $this->tenantAdmin->id,
        ]);

        WarehouseTransferLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'qty' => $qty,
        ]);

        return $transfer;
    }

    public function test_completing_a_transfer_creates_linked_move_pair(): void
    {
        $transfer = $this->makeTransfer('4');

        app(WarehouseTransferService::class)->complete($transfer);

        $this->assertSame('completed', $transfer->fresh()->status);

        $moves = StockMove::where('reference_type', 'warehouse_transfer')->where('reference_id', $transfer->id)->orderBy('qty')->get();
        $this->assertCount(2, $moves);
        $this->assertSame('-4.0000', $moves[0]->qty);
        $this->assertSame('4.0000', $moves[1]->qty);

        $this->assertSame('6.0000', StockQuant::where('location_id', $this->source->id)->firstOrFail()->qty);
        $this->assertSame('4.0000', StockQuant::where('location_id', $this->destination->id)->firstOrFail()->qty);
    }

    public function test_insufficient_stock_rolls_back_whole_transfer(): void
    {
        $transfer = $this->makeTransfer('15');

        try {
            app(WarehouseTransferService::class)->complete($transfer);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame('draft', $transfer->fresh()->status);
        $this->assertCount(0, StockMove::where('reference_type', 'warehouse_transfer')->get());
        $this->assertSame('10.0000', StockQuant::where('location_id', $this->source->id)->firstOrFail()->qty);
    }

    public function test_locked_source_location_rejects_transfer(): void
    {
        $this->source->update(['counting_lock' => true]);
        $transfer = $this->makeTransfer('2');

        try {
            app(WarehouseTransferService::class)->complete($transfer);
            $this->fail('409 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_completed_transfer_cannot_run_twice(): void
    {
        $transfer = $this->makeTransfer('2');
        app(WarehouseTransferService::class)->complete($transfer);

        try {
            app(WarehouseTransferService::class)->complete($transfer->fresh());
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertCount(2, StockMove::where('reference_type', 'warehouse_transfer')->where('reference_id', $transfer->id)->get());
    }
}
