<?php

namespace Tests\Feature\Inventory;

use App\Models\Tenant;
use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Route;
use Modules\Inventory\Models\RouteRule;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\StockMoveService;
use Tests\TenantTestCase;

class RouteScreensTest extends TenantTestCase
{
    private Warehouse $warehouse;

    private Product $product;

    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'name' => 'Rota Ürünü']);
    }

    private function location(string $name): Location
    {
        return Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $this->warehouse->id, 'type' => 'internal', 'name' => $name]);
    }

    public function test_routes_page_lists_existing_routes_with_rule_count(): void
    {
        $route = Route::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'İki Adımlı Teslimat']);
        RouteRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'route_id' => $route->id,
            'from_location_id' => $this->location('Mal Kabul')->id,
            'to_location_id' => $this->location('Sevk Alanı')->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/routes')
            ->assertOk()
            ->assertSee('İki Adımlı Teslimat')
            ->assertSee('1');
    }

    public function test_route_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/routes', [
            'name' => 'Doğrudan Teslimat',
        ])->assertRedirect();

        $this->assertDatabaseHas('routes', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Doğrudan Teslimat',
        ]);
    }

    public function test_show_page_lists_rules_ordered_by_sequence(): void
    {
        $route = Route::factory()->create(['tenant_id' => $this->tenant->id]);
        $dock = $this->location('Mal Kabul');
        $staging = $this->location('Ara Bölge');
        $shipping = $this->location('Sevk Alanı');

        RouteRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'route_id' => $route->id,
            'from_location_id' => $staging->id, 'to_location_id' => $shipping->id, 'action' => 'push', 'sequence' => 2,
        ]);
        RouteRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'route_id' => $route->id,
            'from_location_id' => $dock->id, 'to_location_id' => $staging->id, 'action' => 'push', 'sequence' => 1,
        ]);

        $response = $this->actingAs($this->tenantAdmin)
            ->get("/app/inventory/routes/{$route->id}")
            ->assertOk();

        $content = $response->getContent();
        $firstPos = strpos($content, $dock->name);
        $secondPos = strpos($content, $staging->name);

        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertLessThan($secondPos, $firstPos, 'Expected sequence 1 rule to render before sequence 2 rule.');
    }

    public function test_rule_can_be_added_to_route(): void
    {
        $route = Route::factory()->create(['tenant_id' => $this->tenant->id]);
        $dock = $this->location('Mal Kabul');
        $shipping = $this->location('Sevk Alanı');

        $this->actingAs($this->tenantAdmin)->post("/app/inventory/routes/{$route->id}/rules", [
            'from_location_id' => $dock->id,
            'to_location_id' => $shipping->id,
            'action' => 'push',
            'procure_method' => 'make_to_stock',
            'sequence' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('route_rules', [
            'tenant_id' => $this->tenant->id,
            'route_id' => $route->id,
            'from_location_id' => $dock->id,
            'to_location_id' => $shipping->id,
            'action' => 'push',
            'procure_method' => 'make_to_stock',
            'sequence' => 1,
        ]);
    }

    public function test_rule_can_be_created_as_buy_action(): void
    {
        $route = Route::factory()->create(['tenant_id' => $this->tenant->id]);
        $supplier = Location::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'supplier']);
        $stock = $this->location('Stok');

        $this->actingAs($this->tenantAdmin)->post("/app/inventory/routes/{$route->id}/rules", [
            'name' => 'Tedarikçiden Al',
            'from_location_id' => $supplier->id,
            'to_location_id' => $stock->id,
            'action' => 'buy',
            'procure_method' => 'make_to_order',
            'sequence' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('route_rules', [
            'route_id' => $route->id,
            'name' => 'Tedarikçiden Al',
            'action' => 'buy',
            'procure_method' => 'make_to_order',
        ]);
    }

    public function test_cross_tenant_rule_update_returns_not_found(): void
    {
        $otherTenant = Tenant::factory()->create();
        $route = Route::factory()->create(['tenant_id' => $otherTenant->id]);
        $loc = Location::factory()->create(['tenant_id' => $otherTenant->id]);
        $rule = RouteRule::factory()->create([
            'tenant_id' => $otherTenant->id,
            'route_id' => $route->id,
            'from_location_id' => $loc->id,
            'to_location_id' => $loc->id,
            'action' => 'push',
            'procure_method' => 'make_to_stock',
            'sequence' => 1,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->patch("/app/inventory/routes/rules/{$rule->id}", [
                'from_location_id' => $loc->id,
                'to_location_id' => $loc->id,
                'action' => 'push',
                'procure_method' => 'make_to_stock',
                'sequence' => 5,
            ])
            ->assertNotFound();

        $this->assertSame(1, $rule->fresh()->sequence);
    }

    public function test_cross_tenant_rule_delete_returns_not_found(): void
    {
        $otherTenant = Tenant::factory()->create();
        $route = Route::factory()->create(['tenant_id' => $otherTenant->id]);
        $loc = Location::factory()->create(['tenant_id' => $otherTenant->id]);
        $rule = RouteRule::factory()->create([
            'tenant_id' => $otherTenant->id,
            'route_id' => $route->id,
            'from_location_id' => $loc->id,
            'to_location_id' => $loc->id,
            'action' => 'push',
            'procure_method' => 'make_to_stock',
            'sequence' => 1,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/routes/rules/{$rule->id}")
            ->assertNotFound();

        $this->assertNotNull($rule->fresh());
    }

    public function test_rule_can_be_updated_and_deleted(): void
    {
        $route = Route::factory()->create(['tenant_id' => $this->tenant->id]);
        $dock = $this->location('Mal Kabul');
        $stock = $this->location('Stok');
        $rule = RouteRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'route_id' => $route->id,
            'from_location_id' => $dock->id,
            'to_location_id' => $stock->id,
            'action' => 'push',
            'procure_method' => 'make_to_stock',
            'sequence' => 1,
        ]);

        $this->actingAs($this->tenantAdmin)->patch("/app/inventory/routes/rules/{$rule->id}", [
            'name' => 'Güncel Ad',
            'from_location_id' => $dock->id,
            'to_location_id' => $stock->id,
            'action' => 'pull',
            'procure_method' => 'make_to_order',
            'sequence' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('route_rules', ['id' => $rule->id, 'name' => 'Güncel Ad', 'action' => 'pull', 'sequence' => 5]);

        $this->actingAs($this->tenantAdmin)->delete("/app/inventory/routes/rules/{$rule->id}")->assertRedirect();
        $this->assertDatabaseMissing('route_rules', ['id' => $rule->id]);
    }

    public function test_executing_two_step_push_route_chains_moves_to_final_location(): void
    {
        $dock = $this->location('Mal Kabul');
        $staging = $this->location('Ara Bölge');
        $shipping = $this->location('Sevk Alanı');

        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $this->product,
            fromLocationId: null, toLocationId: $dock->id,
            qty: '20', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 1,
        );

        $route = Route::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'İki Adımlı Teslimat']);
        RouteRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'route_id' => $route->id,
            'from_location_id' => $dock->id, 'to_location_id' => $staging->id, 'action' => 'push', 'sequence' => 1,
        ]);
        RouteRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'route_id' => $route->id,
            'from_location_id' => $staging->id, 'to_location_id' => $shipping->id, 'action' => 'push', 'sequence' => 2,
        ]);

        $this->actingAs($this->tenantAdmin)->post("/app/inventory/routes/{$route->id}/execute", [
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'qty' => '8',
        ])->assertRedirect();

        $this->assertSame('12.0000', StockQuant::where('location_id', $dock->id)->firstOrFail()->qty);
        $this->assertSame('8.0000', StockQuant::where('location_id', $shipping->id)->firstOrFail()->qty);
    }

    public function test_warehouse_operator_cannot_manage_routes(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)->get('/app/inventory/routes')->assertForbidden();
    }
}
