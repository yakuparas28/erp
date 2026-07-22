<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ReorderingRule;
use Modules\Inventory\Models\ReplenishmentSuggestion;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Tests\TenantTestCase;

class ReorderingScreensTest extends TenantTestCase
{
    private Location $location;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal']);

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id, 'name' => 'Yeniden Sipariş Ürünü']);
    }

    public function test_reordering_page_lists_existing_rules_and_pending_suggestions(): void
    {
        $rule = ReorderingRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
            'min_qty' => '10.0000',
            'max_qty' => '50.0000',
        ]);

        ReplenishmentSuggestion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reordering_rule_id' => $rule->id,
            'suggested_qty' => '40.0000',
            'status' => 'pending',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/reordering')
            ->assertOk()
            ->assertSee('Yeniden Sipariş Ürünü')
            ->assertSee($this->location->name)
            ->assertSee('40.0000');
    }

    public function test_rule_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/reordering', [
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
            'min_qty' => 5,
            'max_qty' => 25,
            'trigger_type' => 'auto',
        ])->assertRedirect();

        $this->assertDatabaseHas('reordering_rules', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
            'trigger_type' => 'auto',
        ]);
    }

    public function test_max_qty_must_be_greater_than_min_qty(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/reordering', [
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
            'min_qty' => 25,
            'max_qty' => 5,
            'trigger_type' => 'auto',
        ])->assertSessionHasErrors('max_qty');

        $this->assertDatabaseMissing('reordering_rules', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_rule_can_be_deleted(): void
    {
        $rule = ReorderingRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/reordering/{$rule->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('reordering_rules', ['id' => $rule->id]);
    }

    public function test_pending_suggestion_can_be_acknowledged(): void
    {
        $rule = ReorderingRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
        ]);
        $suggestion = ReplenishmentSuggestion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reordering_rule_id' => $rule->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->post("/app/inventory/reordering/suggestions/{$suggestion->id}/acknowledge")
            ->assertRedirect();

        $this->assertDatabaseHas('replenishment_suggestions', [
            'id' => $suggestion->id,
            'status' => 'acknowledged',
        ]);
    }

    public function test_warehouse_operator_cannot_manage_reordering_rules(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)->get('/app/inventory/reordering')->assertForbidden();
    }
}
