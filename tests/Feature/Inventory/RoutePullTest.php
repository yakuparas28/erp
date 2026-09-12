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
use Modules\Inventory\Services\RouteService;
use Tests\TenantTestCase;

class RoutePullTest extends TenantTestCase
{
    public function test_pull_chains_rules_from_lowest_to_highest_sequence(): void
    {
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $uomCategory->id,
            'is_reference' => true,
            'factor' => '1',
        ]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id]);

        $stock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Stok']);
        $pack = Location::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Pack']);
        $out = Location::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Output']);

        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'location_id' => $stock->id,
            'qty' => '20',
        ]);
        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'location_id' => $pack->id,
            'qty' => '0',
        ]);

        $route = Route::factory()->create(['tenant_id' => $this->tenant->id]);
        RouteRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'route_id' => $route->id,
            'from_location_id' => $stock->id,
            'to_location_id' => $pack->id,
            'action' => 'pull',
            'sequence' => 1,
        ]);
        RouteRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'route_id' => $route->id,
            'from_location_id' => $pack->id,
            'to_location_id' => $out->id,
            'action' => 'pull',
            'sequence' => 2,
        ]);

        app(RouteService::class)->executePull($route, $product, '5', $uom);

        // Pull, push gibi kaynaktan hedefe doğru zincirlenir (Stok → Pack → Output).
        $moves = StockMove::where('reference_type', 'route_rule')->orderBy('id')->get();
        $this->assertCount(4, $moves);
        $this->assertSame($stock->id, $moves[0]->from_location_id);
        $this->assertSame($pack->id, $moves[0]->to_location_id);
        $this->assertSame($pack->id, $moves[2]->from_location_id);
        $this->assertSame($out->id, $moves[2]->to_location_id);
    }

    public function test_pull_ignores_push_rules(): void
    {
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $uomCategory->id,
            'is_reference' => true,
            'factor' => '1',
        ]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id]);
        $stock = Location::factory()->create(['tenant_id' => $this->tenant->id]);
        $pack = Location::factory()->create(['tenant_id' => $this->tenant->id]);
        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'location_id' => $stock->id,
            'qty' => '20',
        ]);

        $route = Route::factory()->create(['tenant_id' => $this->tenant->id]);
        RouteRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'route_id' => $route->id,
            'from_location_id' => $stock->id,
            'to_location_id' => $pack->id,
            'action' => 'push',
            'sequence' => 1,
        ]);

        app(RouteService::class)->executePull($route, $product, '5', $uom);

        $this->assertSame(0, StockMove::where('reference_type', 'route_rule')->count());
    }
}
