<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\LandedCost;
use Modules\Inventory\Models\LandedCostLine;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockValuationLayer;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Tests\TenantTestCase;

class LandedCostScreensTest extends TenantTestCase
{
    private Uom $uom;

    private Product $product;

    private Location $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->uom = Uom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $uomCategory->id,
            'is_reference' => true,
            'factor' => '1',
        ]);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->uom->id,
            'cost_method' => 'fifo',
        ]);
        $this->stock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Stok']);
    }

    public function test_index_lists_landed_costs(): void
    {
        LandedCost::factory()->create(['tenant_id' => $this->tenant->id, 'split_method' => 'by_quantity', 'status' => 'draft']);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/landed-costs')
            ->assertOk()
            ->assertSee('LC-');
    }

    public function test_new_landed_cost_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/landed-costs', [
            'split_method' => 'by_weight',
        ])->assertRedirect();

        $this->assertDatabaseHas('landed_costs', [
            'tenant_id' => $this->tenant->id,
            'split_method' => 'by_weight',
            'status' => 'draft',
        ]);
    }

    public function test_cost_lines_can_be_added_and_removed(): void
    {
        $lc = LandedCost::factory()->create(['tenant_id' => $this->tenant->id, 'split_method' => 'by_quantity', 'status' => 'draft']);

        $this->actingAs($this->tenantAdmin)->post(
            route('app.inventory.landed-costs.lines.store', $lc),
            ['description' => 'Nakliye', 'amount' => '150'],
        )->assertRedirect();

        $line = LandedCostLine::firstWhere('description', 'Nakliye');
        $this->assertNotNull($line);

        $this->actingAs($this->tenantAdmin)
            ->delete(route('app.inventory.landed-costs.lines.destroy', $line))
            ->assertRedirect();

        $this->assertDatabaseMissing('landed_cost_lines', ['id' => $line->id]);
    }

    public function test_lines_cannot_be_added_to_validated_landed_cost(): void
    {
        $lc = LandedCost::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'validated']);

        $this->actingAs($this->tenantAdmin)->post(
            route('app.inventory.landed-costs.lines.store', $lc),
            ['description' => 'X', 'amount' => '1'],
        )->assertStatus(422);
    }

    public function test_validate_distributes_across_selected_moves_and_updates_layers(): void
    {
        $lc = LandedCost::factory()->create(['tenant_id' => $this->tenant->id, 'split_method' => 'by_quantity', 'status' => 'draft']);
        LandedCostLine::factory()->create(['tenant_id' => $this->tenant->id, 'landed_cost_id' => $lc->id, 'description' => 'Kargo', 'amount' => '100']);

        $move = StockMove::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'to_location_id' => $this->stock->id,
            'qty' => '10',
            'uom_id' => $this->uom->id,
            'reference_type' => 'purchase_order_line',
            'reference_id' => 1,
        ]);
        $layer = StockValuationLayer::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'stock_move_id' => $move->id,
            'qty' => '10',
            'unit_cost' => '5',
            'remaining_value' => '50',
        ]);

        $this->actingAs($this->tenantAdmin)->post(
            route('app.inventory.landed-costs.validate', $lc),
            ['stock_move_ids' => [$move->id]],
        )->assertRedirect();

        $lc->refresh();
        $this->assertSame('validated', $lc->status);

        $layer->refresh();
        // 100 dağıtıldı 10 miktara → unit_cost 5+10=15, remaining_value 50+100=150
        $this->assertSame('15.0000', $layer->unit_cost);
        $this->assertSame('150.0000', $layer->remaining_value);
    }

    public function test_user_without_permission_cannot_access(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/inventory/landed-costs')
            ->assertForbidden();
    }
}
