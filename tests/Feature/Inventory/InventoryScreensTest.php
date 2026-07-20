<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseTransfer;
use Modules\Inventory\Models\WarehouseTransferLine;
use Modules\Inventory\Services\StockMoveService;
use Tests\TenantTestCase;

class InventoryScreensTest extends TenantTestCase
{
    private Location $location;

    private Product $product;

    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id, 'is_reference' => true]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'name' => 'Test Ürünü']);
    }

    public function test_products_page_lists_products_and_creates_new_ones(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/products')
            ->assertOk()
            ->assertSee('Test Ürünü');

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/products', [
            'name' => 'Yeni Ürün',
            'uom_id' => $this->unit->id,
            'product_type' => 'stockable',
            'track_by' => 'none',
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['name' => 'Yeni Ürün', 'tenant_id' => $this->tenant->id]);
    }

    public function test_warehouses_page_lists_locations_and_creates_new_warehouse(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/warehouses')
            ->assertOk()
            ->assertSee($this->location->name);

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/warehouses', [
            'code' => 'WH2',
            'name' => 'İkinci Depo',
        ])->assertRedirect();

        $this->assertDatabaseHas('warehouses', ['code' => 'WH2', 'tenant_id' => $this->tenant->id]);
    }

    public function test_stock_page_shows_quants(): void
    {
        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $this->product,
            fromLocationId: null, toLocationId: $this->location->id,
            qty: '15', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 1,
        );

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/stock')
            ->assertOk()
            ->assertSee('Test Ürünü')
            ->assertSee('15.0000');
    }

    public function test_full_adjustment_flow_through_http(): void
    {
        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $this->product,
            fromLocationId: null, toLocationId: $this->location->id,
            qty: '10', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 900,
        );

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/adjustments', [
            'location_id' => $this->location->id,
        ])->assertRedirect();

        $adjustment = InventoryAdjustment::firstOrFail();
        $this->assertTrue($this->location->fresh()->counting_lock);

        $this->actingAs($this->tenantAdmin)->post("/app/inventory/adjustments/{$adjustment->id}/counts", [
            'product_id' => $this->product->id,
            'qty' => '6',
        ])->assertRedirect();

        $this->actingAs($this->tenantAdmin)
            ->get("/app/inventory/adjustments/{$adjustment->id}")
            ->assertOk()
            ->assertDontSee('10.0000'); // teorik miktar sayım sırasında gizli

        $this->actingAs($this->tenantAdmin)->post("/app/inventory/adjustments/{$adjustment->id}/submit")->assertRedirect();

        $approver = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $approver->assignRole('Tenant Admin');

        $this->actingAs($approver)->post("/app/inventory/adjustments/{$adjustment->id}/approve")->assertRedirect();

        $this->assertSame('approved', $adjustment->fresh()->status);
        $this->assertFalse($this->location->fresh()->counting_lock);
    }

    public function test_self_approval_is_rejected_via_http_and_shows_error(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/adjustments', ['location_id' => $this->location->id])->assertRedirect();
        $adjustment = InventoryAdjustment::firstOrFail();

        $this->actingAs($this->tenantAdmin)->post("/app/inventory/adjustments/{$adjustment->id}/submit")->assertRedirect();

        $this->actingAs($this->tenantAdmin)
            ->from("/app/inventory/adjustments/{$adjustment->id}")
            ->post("/app/inventory/adjustments/{$adjustment->id}/approve")
            ->assertRedirect("/app/inventory/adjustments/{$adjustment->id}")
            ->assertSessionHasErrors('adjustment');
    }

    public function test_transfer_can_be_created_and_completed_via_http(): void
    {
        $destination = Location::factory()->create(['tenant_id' => $this->tenant->id]);

        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $this->product,
            fromLocationId: null, toLocationId: $this->location->id,
            qty: '10', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 901,
        );

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/transfers', [
            'from_location_id' => $this->location->id,
            'to_location_id' => $destination->id,
            'product_id' => $this->product->id,
            'qty' => '3',
        ])->assertRedirect();

        $transfer = WarehouseTransfer::firstOrFail();
        $this->assertSame(1, WarehouseTransferLine::where('warehouse_transfer_id', $transfer->id)->count());

        $this->actingAs($this->tenantAdmin)
            ->post("/app/inventory/transfers/{$transfer->id}/complete")
            ->assertRedirect();

        $this->assertSame('completed', $transfer->fresh()->status);
    }

    public function test_warehouse_operator_cannot_manage_products(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)->get('/app/inventory/products')->assertForbidden();
        $this->actingAs($operator)->get('/app/inventory/stock')->assertOk();
    }
}
