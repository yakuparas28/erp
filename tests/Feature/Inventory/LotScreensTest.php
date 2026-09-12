<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductLot;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Tests\TenantTestCase;

class LotScreensTest extends TenantTestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $uomCategory->id,
            'is_reference' => true,
            'factor' => '1',
        ]);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $uom->id,
            'track_by' => 'lot',
        ]);
    }

    public function test_lot_index_lists_lots_with_expiry_badge(): void
    {
        ProductLot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-001',
            'expiry_date' => now()->addYear(),
        ]);
        ProductLot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-002',
            'expiry_date' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->tenantAdmin)->get('/app/inventory/lots');

        $response->assertOk()
            ->assertSee('LOT-001')
            ->assertSee('LOT-002')
            ->assertSee(__('Expired'));
    }

    public function test_expired_filter_limits_results(): void
    {
        ProductLot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'lot_number' => 'FRESH',
            'expiry_date' => now()->addYear(),
        ]);
        ProductLot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'lot_number' => 'OLD',
            'expiry_date' => now()->subMonth(),
        ]);

        $response = $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/lots?expired_only=1');

        $response->assertOk()
            ->assertSee('OLD')
            ->assertDontSee('FRESH');
    }

    public function test_new_lot_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/lots', [
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-NEW',
            'expiry_date' => now()->addYear()->toDateString(),
        ])->assertRedirect(route('app.inventory.lots.index'));

        $this->assertDatabaseHas('product_lots', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-NEW',
        ]);
    }

    public function test_lot_number_must_be_unique_per_product(): void
    {
        ProductLot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'lot_number' => 'DUP',
        ]);

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/lots', [
            'product_id' => $this->product->id,
            'lot_number' => 'DUP',
        ])->assertSessionHasErrors('lot_number');
    }

    public function test_lot_with_stock_moves_cannot_be_deleted(): void
    {
        $lot = ProductLot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);
        $location = Location::factory()->create(['tenant_id' => $this->tenant->id]);
        StockMove::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'lot_id' => $lot->id,
            'to_location_id' => $location->id,
            'qty' => '10',
            'uom_id' => $this->product->uom_id,
            'reference_type' => 'seed',
            'reference_id' => 0,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/lots/{$lot->id}")
            ->assertSessionHasErrors('lot');

        $this->assertDatabaseHas('product_lots', ['id' => $lot->id]);
    }

    public function test_unused_lot_can_be_deleted(): void
    {
        $lot = ProductLot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/lots/{$lot->id}")
            ->assertRedirect(route('app.inventory.lots.index'));

        $this->assertDatabaseMissing('product_lots', ['id' => $lot->id]);
    }

    public function test_trace_page_shows_upstream_and_downstream_moves(): void
    {
        $lot = ProductLot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'lot_number' => 'TRACE-1',
        ]);
        $stock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Stok']);

        StockMove::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'lot_id' => $lot->id,
            'from_location_id' => null,
            'to_location_id' => $stock->id,
            'qty' => '10',
            'uom_id' => $this->product->uom_id,
            'reference_type' => 'purchase_order_line',
            'reference_id' => 42,
        ]);

        StockMove::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'lot_id' => $lot->id,
            'from_location_id' => $stock->id,
            'to_location_id' => null,
            'qty' => '-4',
            'uom_id' => $this->product->uom_id,
            'reference_type' => 'sales_order_line',
            'reference_id' => 99,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get(route('app.inventory.lots.trace', $lot))
            ->assertOk()
            ->assertSee('TRACE-1')
            ->assertSee('purchase_order_line #42', false)
            ->assertSee('sales_order_line #99', false);
    }

    public function test_user_without_permission_cannot_access(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/inventory/lots')
            ->assertForbidden();
    }
}
