<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductBarcode;
use Modules\Inventory\Models\SyncBatch;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryAdjustmentService;
use Tests\TenantTestCase;

class BulkPushSyncTest extends TenantTestCase
{
    private User $operator;

    private Uom $unit;

    private Uom $box;

    private Product $product;

    private InventoryAdjustment $adjustment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->operator = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $this->operator->assignRole('Warehouse Operator');

        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id, 'is_reference' => true]);
        $this->box = Uom::factory()->create([
            'tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id,
            'name' => 'Koli', 'factor' => '12.000000', 'is_reference' => false,
        ]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        ProductBarcode::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'barcode' => '1111111111111']);
        ProductBarcode::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'barcode' => '2222222222222', 'uom_id' => $this->box->id]);

        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $this->adjustment = app(InventoryAdjustmentService::class)->open($this->tenant->id, $location->id, $this->tenantAdmin);
        app(InventoryAdjustmentService::class)->startCounting($this->adjustment);
    }

    private function token(): string
    {
        return $this->operator->createToken('mobile')->plainTextToken;
    }

    private function push(array $overrides = []): TestResponse
    {
        return $this->withToken($this->token())->postJson('/api/inventory/sync/counts', array_merge([
            'batch_uuid' => (string) Str::uuid(),
            'inventory_adjustment_id' => $this->adjustment->id,
            'lines' => [
                ['barcode' => '1111111111111', 'qty' => '5'],
            ],
        ], $overrides));
    }

    public function test_push_resolves_barcode_and_adds_count_in_reference_unit(): void
    {
        $this->push()->assertOk();

        $this->assertSame(
            '5.0000',
            $this->adjustment->lines()->where('product_id', $this->product->id)->firstOrFail()->counted_qty,
        );
    }

    public function test_box_barcode_is_converted_to_reference_unit_server_side(): void
    {
        $this->push(['lines' => [['barcode' => '2222222222222', 'qty' => '2']]])->assertOk();

        // 2 koli x 12 = 24 adet (referans birimde)
        $this->assertSame(
            '24.0000',
            $this->adjustment->lines()->where('product_id', $this->product->id)->firstOrFail()->counted_qty,
        );
    }

    public function test_same_batch_uuid_is_processed_only_once(): void
    {
        $uuid = (string) Str::uuid();

        $this->push(['batch_uuid' => $uuid])->assertOk();
        $this->push(['batch_uuid' => $uuid])->assertOk();
        $this->push(['batch_uuid' => $uuid])->assertOk();

        $this->assertSame(
            '5.0000',
            $this->adjustment->lines()->where('product_id', $this->product->id)->firstOrFail()->counted_qty,
        );
        $this->assertSame(1, SyncBatch::where('batch_uuid', $uuid)->count());
    }

    public function test_two_different_chunks_merge_additively(): void
    {
        $this->push(['batch_uuid' => (string) Str::uuid(), 'lines' => [['barcode' => '1111111111111', 'qty' => '4']]])->assertOk();
        $this->push(['batch_uuid' => (string) Str::uuid(), 'lines' => [['barcode' => '1111111111111', 'qty' => '8']]])->assertOk();

        $this->assertSame(
            '12.0000',
            $this->adjustment->lines()->where('product_id', $this->product->id)->firstOrFail()->counted_qty,
        );
    }

    public function test_more_than_1000_lines_is_rejected(): void
    {
        $lines = array_fill(0, 1001, ['barcode' => '1111111111111', 'qty' => '1']);

        $this->push(['lines' => $lines])->assertStatus(422);
    }

    public function test_unknown_barcode_rejects_whole_batch_without_partial_writes(): void
    {
        $this->push(['lines' => [
            ['barcode' => '1111111111111', 'qty' => '5'],
            ['barcode' => '9999999999999', 'qty' => '1'],
        ]])->assertStatus(422);

        $this->assertDatabaseMissing('inventory_adjustment_lines', ['inventory_adjustment_id' => $this->adjustment->id]);
    }

    public function test_pushing_to_a_non_counting_adjustment_is_rejected(): void
    {
        app(InventoryAdjustmentService::class)->submitForApproval($this->adjustment);

        $this->push()->assertStatus(422);
    }

    public function test_guest_cannot_push_counts(): void
    {
        $this->postJson('/api/inventory/sync/counts', [
            'batch_uuid' => (string) Str::uuid(),
            'inventory_adjustment_id' => $this->adjustment->id,
            'lines' => [['barcode' => '1111111111111', 'qty' => '1']],
        ])->assertUnauthorized();
    }
}
