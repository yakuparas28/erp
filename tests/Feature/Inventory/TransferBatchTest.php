<?php

namespace Tests\Feature\Inventory;

use App\Models\Tenant;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\TransferBatch;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseTransfer;
use Modules\Inventory\Models\WarehouseTransferLine;
use Modules\Inventory\Services\InventoryDefaultsService;
use Modules\Inventory\Services\TransferBatchService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class TransferBatchTest extends TenantTestCase
{
    private Location $source;

    private Location $destination;

    private Product $product;

    private Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        app(InventoryDefaultsService::class)->provision($this->tenant);

        $warehouse = Warehouse::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
        $this->source = Location::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('name', 'Stok')->firstOrFail();
        $this->destination = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);

        $this->uom = Uom::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('is_reference', true)->firstOrFail();

        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->uom->id,
        ]);

        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'location_id' => $this->source->id,
            'qty' => '20',
        ]);

        setPermissionsTeamId($this->tenant->id);
        $this->tenantAdmin->givePermissionTo('manage warehouse transfers');
    }

    public function test_batch_can_be_created_and_transfers_added(): void
    {
        $batch = app(TransferBatchService::class)->create($this->tenant->id, 'Sabah dağıtımı');

        $transfer = $this->newDraftTransfer();
        app(TransferBatchService::class)->addTransfer($batch, $transfer);

        $this->assertSame($batch->id, $transfer->fresh()->batch_id);
    }

    public function test_batch_completion_completes_all_draft_transfers_atomically(): void
    {
        $batch = app(TransferBatchService::class)->create($this->tenant->id, 'X');
        $t1 = $this->newDraftTransfer('5');
        $t2 = $this->newDraftTransfer('7');
        app(TransferBatchService::class)->addTransfer($batch, $t1);
        app(TransferBatchService::class)->addTransfer($batch, $t2);

        app(TransferBatchService::class)->complete($batch->fresh()->load('transfers'), $this->tenantAdmin);

        $this->assertSame('done', $batch->fresh()->status);
        $this->assertSame('completed', $t1->fresh()->status);
        $this->assertSame('completed', $t2->fresh()->status);
    }

    public function test_batch_cannot_be_completed_when_empty(): void
    {
        $batch = app(TransferBatchService::class)->create($this->tenant->id, 'Empty');

        $this->expectException(HttpException::class);

        app(TransferBatchService::class)->complete($batch->load('transfers'), $this->tenantAdmin);
    }

    public function test_cancel_frees_draft_transfers(): void
    {
        $batch = app(TransferBatchService::class)->create($this->tenant->id, 'X');
        $transfer = $this->newDraftTransfer();
        app(TransferBatchService::class)->addTransfer($batch, $transfer);

        app(TransferBatchService::class)->cancel($batch->fresh()->load('transfers'));

        $this->assertNull($transfer->fresh()->batch_id);
        $this->assertSame('cancelled', $batch->fresh()->status);
    }

    public function test_index_lists_batches(): void
    {
        TransferBatch::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sabah Turu']);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/transfer-batches')
            ->assertOk()
            ->assertSee('Sabah Turu');
    }

    public function test_transfer_from_another_tenant_cannot_be_added_to_a_batch(): void
    {
        $batch = app(TransferBatchService::class)->create($this->tenant->id, 'X');

        $otherTenant = Tenant::factory()->create();
        $otherTransfer = WarehouseTransfer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'from_location_id' => $this->source->id,
            'to_location_id' => $this->destination->id,
        ]);

        $this->expectException(HttpException::class);

        app(TransferBatchService::class)->addTransfer($batch, $otherTransfer);
    }

    private function newDraftTransfer(string $qty = '5'): WarehouseTransfer
    {
        $transfer = WarehouseTransfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'from_location_id' => $this->source->id,
            'to_location_id' => $this->destination->id,
            'created_by' => $this->tenantAdmin->id,
            'status' => 'draft',
        ]);
        WarehouseTransferLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'uom_id' => $this->uom->id,
            'qty' => $qty,
        ]);

        return $transfer;
    }
}
