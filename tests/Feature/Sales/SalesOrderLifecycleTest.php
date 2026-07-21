<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Sales\Services\SalesOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class SalesOrderLifecycleTest extends TenantTestCase
{
    private Partner $customer;

    private Location $location;

    private Product $product;

    private Uom $unit;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $this->customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id]);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        StockQuant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'location_id' => $this->location->id,
            'lot_id' => null,
            'qty' => '1000.0000',
            'reserved_qty' => '0.0000',
        ]);

        setPermissionsTeamId($this->tenant->id);
        $this->rep = User::factory()->for($this->tenant)->create();
        $this->rep->assignRole('Sales Representative');
    }

    private function service(): SalesOrderService
    {
        return app(SalesOrderService::class);
    }

    public function test_full_lifecycle_draft_to_confirmed(): void
    {
        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
        $this->service()->addLine($so, $this->product->id, $this->unit->id, '20', '5.0000');
        $this->service()->sendQuotation($so);

        $this->assertSame('quotation_sent', $so->fresh()->status);

        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $this->assertSame('confirmed', $so->fresh()->status);
    }

    public function test_creator_cannot_confirm_own_sales_order(): void
    {
        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->tenantAdmin);
        $this->service()->addLine($so, $this->product->id, $this->unit->id, '10', '5.0000');
        $this->service()->sendQuotation($so);

        try {
            $this->service()->confirm($so->fresh(), $this->tenantAdmin);
            $this->fail('403 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_user_without_confirm_permission_cannot_confirm_someone_elses_order(): void
    {
        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
        $this->service()->addLine($so, $this->product->id, $this->unit->id, '10', '5.0000');
        $this->service()->sendQuotation($so);

        setPermissionsTeamId($this->tenant->id);
        $otherRep = User::factory()->for($this->tenant)->create();
        $otherRep->assignRole('Sales Representative');

        try {
            $this->service()->confirm($so->fresh(), $otherRep);
            $this->fail('403 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_draft_sales_order_cannot_be_confirmed_directly(): void
    {
        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
        $this->service()->addLine($so, $this->product->id, $this->unit->id, '10', '5.0000');

        try {
            $this->service()->confirm($so->fresh(), $this->tenantAdmin);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_already_confirmed_order_cannot_be_confirmed_again(): void
    {
        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
        $this->service()->addLine($so, $this->product->id, $this->unit->id, '10', '5.0000');
        $this->service()->sendQuotation($so);
        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        try {
            $this->service()->confirm($so->fresh(), $this->tenantAdmin);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_cancelling_an_already_done_or_cancelled_order_fails(): void
    {
        $so = $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $this->rep);
        $this->service()->addLine($so, $this->product->id, $this->unit->id, '10', '5.0000');
        $this->service()->sendQuotation($so);
        $this->service()->confirm($so->fresh(), $this->tenantAdmin);
        $this->service()->cancel($so->fresh());

        try {
            $this->service()->cancel($so->fresh());
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
