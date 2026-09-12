<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Services\SalesOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class ReservationPolicyTest extends TenantTestCase
{
    private Uom $uom;

    private Location $stock;

    private Partner $customer;

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
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->stock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal']);
        $this->customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_at_confirmation_product_is_reserved_on_confirm(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->uom->id,
            'track_by' => 'none',
            'reservation_method' => 'at_confirmation',
        ]);
        StockQuant::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $this->stock->id, 'qty' => '10']);
        [$so, $line] = $this->quotationLine($product);

        $confirmer = $this->makeConfirmer();
        app(SalesOrderService::class)->confirm($so, $confirmer);

        $this->assertSame('5.0000', (string) $line->fresh()->reserved_qty);
    }

    public function test_manual_product_is_not_auto_reserved(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->uom->id,
            'track_by' => 'none',
            'reservation_method' => 'manual',
        ]);
        StockQuant::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $this->stock->id, 'qty' => '10']);
        [$so, $line] = $this->quotationLine($product);

        app(SalesOrderService::class)->confirm($so, $this->makeConfirmer());

        $this->assertSame('0.0000', (string) $line->fresh()->reserved_qty);
    }

    public function test_manual_reserve_locks_the_requested_quantity(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->uom->id,
            'track_by' => 'none',
            'reservation_method' => 'manual',
        ]);
        StockQuant::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $this->stock->id, 'qty' => '10']);
        [$so, $line] = $this->quotationLine($product);
        app(SalesOrderService::class)->confirm($so, $this->makeConfirmer());

        app(SalesOrderService::class)->reserveManually($line->fresh(), '3');

        $this->assertSame('3.0000', (string) $line->fresh()->reserved_qty);
    }

    public function test_manual_reserve_cannot_exceed_ordered_qty(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->uom->id,
            'track_by' => 'none',
            'reservation_method' => 'manual',
        ]);
        StockQuant::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $this->stock->id, 'qty' => '10']);
        [$so, $line] = $this->quotationLine($product);
        app(SalesOrderService::class)->confirm($so, $this->makeConfirmer());

        $this->expectException(HttpException::class);
        app(SalesOrderService::class)->reserveManually($line->fresh(), '99');
    }

    public function test_unreserve_frees_the_reserved_quantity(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $this->uom->id,
            'track_by' => 'none',
            'reservation_method' => 'at_confirmation',
        ]);
        StockQuant::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $this->stock->id, 'qty' => '10']);
        [$so, $line] = $this->quotationLine($product);
        app(SalesOrderService::class)->confirm($so, $this->makeConfirmer());

        app(SalesOrderService::class)->unreserveManually($line->fresh());

        $this->assertSame('0.0000', (string) $line->fresh()->reserved_qty);
    }

    private function quotationLine(Product $product): array
    {
        $so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $this->customer->id,
            'location_id' => $this->stock->id,
            'status' => 'quotation_sent',
            'created_by' => $this->tenantAdmin->id,
        ]);
        $line = SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'product_id' => $product->id,
            'uom_id' => $product->uom_id,
            'qty' => '5',
            'unit_price' => '1',
            'delivered_qty' => '0',
            'reserved_qty' => '0',
        ]);

        return [$so, $line];
    }

    private function makeConfirmer(): User
    {
        setPermissionsTeamId($this->tenant->id);
        $confirmer = User::factory()->for($this->tenant)->create();
        $confirmer->assignRole('Tenant Admin');

        return $confirmer;
    }
}
