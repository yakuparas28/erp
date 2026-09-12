<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Route as InvRoute;
use Modules\Inventory\Models\RouteRule;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Services\SalesOrderService;
use Tests\TenantTestCase;

class PullRouteConfirmTest extends TenantTestCase
{
    public function test_pull_route_is_executed_on_so_confirm(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $stock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal', 'name' => 'Stok']);
        $staging = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal', 'name' => 'Sevk Bekliyor']);

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true, 'factor' => '1']);

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $uom->id,
            'track_by' => 'none',
            'reservation_method' => 'manual',
        ]);

        StockQuant::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $stock->id, 'qty' => '10']);
        StockQuant::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $staging->id, 'qty' => '0']);

        $route = InvRoute::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'İki Adımlı Sevk (Pull)']);
        RouteRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'route_id' => $route->id,
            'from_location_id' => $stock->id,
            'to_location_id' => $staging->id,
            'action' => 'pull',
            'procure_method' => 'make_to_stock',
            'sequence' => 1,
        ]);

        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $customer->id,
            'location_id' => $staging->id,
            'route_id' => $route->id,
            'status' => 'quotation_sent',
            'created_by' => $this->tenantAdmin->id,
        ]);
        SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'qty' => '3',
            'unit_price' => '10',
            'delivered_qty' => '0',
            'reserved_qty' => '0',
        ]);

        setPermissionsTeamId($this->tenant->id);
        $confirmer = User::factory()->for($this->tenant)->create();
        $confirmer->assignRole('Tenant Admin');

        app(SalesOrderService::class)->confirm($so, $confirmer);

        // Pull route should have moved 3 from Stok to Sevk Bekliyor
        $this->assertSame('7.0000', (string) StockQuant::where('location_id', $stock->id)->where('product_id', $product->id)->value('qty'));
        $this->assertSame('3.0000', (string) StockQuant::where('location_id', $staging->id)->where('product_id', $product->id)->value('qty'));

        $this->assertGreaterThan(0, StockMove::where('reference_type', 'route_rule')->count());
    }

    public function test_confirm_without_route_id_skips_pull(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $stock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal']);

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true, 'factor' => '1']);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id, 'track_by' => 'none', 'reservation_method' => 'manual']);
        StockQuant::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $stock->id, 'qty' => '5']);

        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $customer->id,
            'location_id' => $stock->id,
            'route_id' => null,
            'status' => 'quotation_sent',
            'created_by' => $this->tenantAdmin->id,
        ]);
        SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'qty' => '2',
            'unit_price' => '10',
            'delivered_qty' => '0',
            'reserved_qty' => '0',
        ]);

        setPermissionsTeamId($this->tenant->id);
        $confirmer = User::factory()->for($this->tenant)->create();
        $confirmer->assignRole('Tenant Admin');

        app(SalesOrderService::class)->confirm($so, $confirmer);

        $this->assertSame(0, StockMove::where('reference_type', 'route_rule')->count());
    }
}
