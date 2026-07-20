<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Route;
use Modules\Inventory\Models\RouteRule;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\RouteService;
use Modules\Inventory\Services\StockMoveService;
use Tests\TenantTestCase;

class RouteServiceTest extends TenantTestCase
{
    private Warehouse $warehouse;

    private Product $product;

    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
    }

    private function location(string $name): Location
    {
        return Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $this->warehouse->id, 'name' => $name]);
    }

    public function test_two_step_push_route_chains_moves_through_intermediate_location(): void
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

        app(RouteService::class)->executePush($route, $this->product, '8', $this->unit);

        $this->assertSame('12.0000', StockQuant::where('location_id', $dock->id)->firstOrFail()->qty);
        $this->assertSame(0, StockQuant::where('location_id', $staging->id)->where('qty', '>', 0)->count());
        $this->assertSame('8.0000', StockQuant::where('location_id', $shipping->id)->firstOrFail()->qty);
    }

    public function test_pull_steps_are_not_executed_automatically(): void
    {
        $dock = $this->location('Mal Kabul');
        $shipping = $this->location('Sevk Alanı');

        app(StockMoveService::class)->move(
            tenantId: $this->tenant->id, product: $this->product,
            fromLocationId: null, toLocationId: $dock->id,
            qty: '10', uom: $this->unit,
            referenceType: 'inventory_adjustment', referenceId: 2,
        );

        $route = Route::factory()->create(['tenant_id' => $this->tenant->id]);
        RouteRule::factory()->create([
            'tenant_id' => $this->tenant->id, 'route_id' => $route->id,
            'from_location_id' => $dock->id, 'to_location_id' => $shipping->id, 'action' => 'pull', 'sequence' => 1,
        ]);

        app(RouteService::class)->executePush($route, $this->product, '5', $this->unit);

        $this->assertSame(0, StockMove::where('from_location_id', $dock->id)->where('to_location_id', $shipping->id)->count());
    }
}
