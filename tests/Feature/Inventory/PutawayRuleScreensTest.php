<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\PutawayRule;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Tests\TenantTestCase;

class PutawayRuleScreensTest extends TenantTestCase
{
    private Location $source;

    private Location $destination;

    private Product $product;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->source = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal']);
        $this->destination = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal']);

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id, 'name' => 'Raf Ürünü']);
        $this->category = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Soğuk Zincir']);
    }

    public function test_putaway_page_lists_existing_rules(): void
    {
        PutawayRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'source_location_id' => $this->source->id,
            'dest_location_id' => $this->destination->id,
            'sequence' => 5,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/putaway')
            ->assertOk()
            ->assertSee('Raf Ürünü')
            ->assertSee($this->source->name)
            ->assertSee($this->destination->name);
    }

    public function test_product_based_rule_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/putaway', [
            'product_id' => $this->product->id,
            'product_category_id' => null,
            'source_location_id' => $this->source->id,
            'dest_location_id' => $this->destination->id,
            'sequence' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('putaway_rules', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'product_category_id' => null,
            'source_location_id' => $this->source->id,
            'dest_location_id' => $this->destination->id,
            'sequence' => 1,
        ]);
    }

    public function test_category_based_rule_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/putaway', [
            'product_category_id' => $this->category->id,
            'source_location_id' => $this->source->id,
            'dest_location_id' => $this->destination->id,
            'sequence' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('putaway_rules', [
            'tenant_id' => $this->tenant->id,
            'product_id' => null,
            'product_category_id' => $this->category->id,
            'source_location_id' => $this->source->id,
            'dest_location_id' => $this->destination->id,
            'sequence' => 2,
        ]);
    }

    public function test_rule_can_be_deleted(): void
    {
        $rule = PutawayRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'source_location_id' => $this->source->id,
            'dest_location_id' => $this->destination->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/putaway/{$rule->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('putaway_rules', ['id' => $rule->id]);
    }

    public function test_warehouse_operator_cannot_manage_putaway_rules(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)->get('/app/inventory/putaway')->assertForbidden();
    }
}
