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
use Tests\TenantTestCase;

class SalesOrderScreensTest extends TenantTestCase
{
    private Partner $customer;

    private Product $product;

    private Uom $unit;

    private Location $location;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal']);
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

    private function draftOrder(?User $creator = null): SalesOrder
    {
        return $this->service()->create($this->tenant->id, $this->customer->id, $this->location->id, $creator ?? $this->rep);
    }

    private function addLine(SalesOrder $so): SalesOrderLine
    {
        return $this->service()->addLine($so, $this->product->id, $this->unit->id, '10', '5.0000');
    }

    public function test_index_page_lists_sales_orders(): void
    {
        $this->actingAs($this->rep);
        $so = $this->draftOrder();

        $response = $this->get(route('app.sales.orders.index'));

        $response->assertOk();
        $response->assertSee($this->customer->name);
    }

    public function test_rep_can_create_a_draft_sales_order(): void
    {
        $this->actingAs($this->rep);

        $response = $this->post(route('app.sales.orders.store'), [
            'partner_id' => $this->customer->id,
            'location_id' => $this->location->id,
        ]);

        $so = SalesOrder::firstOrFail();
        $response->assertRedirect(route('app.sales.orders.show', $so));
        $this->assertSame('draft', $so->status);
        $this->assertSame($this->rep->id, $so->created_by);
    }

    public function test_show_page_displays_the_add_line_form_for_a_draft_order(): void
    {
        $this->actingAs($this->rep);
        $so = $this->draftOrder();

        $response = $this->get(route('app.sales.orders.show', $so));

        $response->assertOk();
        $response->assertSee($this->product->name);
        $response->assertSee(route('app.sales.orders.lines.store', $so), false);
    }

    public function test_line_can_be_added_to_a_draft_order(): void
    {
        $this->actingAs($this->rep);
        $so = $this->draftOrder();

        $response = $this->post(route('app.sales.orders.lines.store', $so), [
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'qty' => '10',
            'unit_price' => '5.5',
        ]);

        $response->assertRedirect(route('app.sales.orders.show', $so));
        $this->assertDatabaseHas('sales_order_lines', [
            'sales_order_id' => $so->id,
            'product_id' => $this->product->id,
            'qty' => '10.0000',
            'unit_price' => '5.5000',
        ]);
    }

    public function test_adding_a_line_with_a_uom_from_a_different_category_fails(): void
    {
        $this->actingAs($this->rep);
        $so = $this->draftOrder();
        $otherCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $mismatchedUom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $otherCategory->id]);

        $response = $this->post(route('app.sales.orders.lines.store', $so), [
            'product_id' => $this->product->id,
            'uom_id' => $mismatchedUom->id,
            'qty' => '10',
            'unit_price' => '5.5',
        ]);

        $response->assertSessionHasErrors('qty');
        $this->assertDatabaseMissing('sales_order_lines', ['sales_order_id' => $so->id]);
    }

    public function test_order_can_be_sent_as_quotation(): void
    {
        $this->actingAs($this->rep);
        $so = $this->draftOrder();
        $this->addLine($so);

        $response = $this->followingRedirects()->post(route('app.sales.orders.send-quotation', $so));

        $response->assertOk();
        $response->assertSee(__('Waiting for confirmation from another user (you cannot confirm your own sales order).'));
        $this->assertSame('quotation_sent', $so->fresh()->status);
    }

    public function test_creator_cannot_confirm_own_sales_order(): void
    {
        $this->actingAs($this->tenantAdmin);
        $so = $this->draftOrder($this->tenantAdmin);
        $this->addLine($so);
        $this->post(route('app.sales.orders.send-quotation', $so));

        $response = $this->post(route('app.sales.orders.confirm', $so));

        $response->assertSessionHasErrors('so');
        $response->assertRedirect();
        $this->assertSame('quotation_sent', $so->fresh()->status);
    }

    public function test_another_user_can_confirm_the_sales_order(): void
    {
        $so = $this->draftOrder($this->rep);
        $this->addLine($so);
        $this->service()->sendQuotation($so);

        $this->actingAs($this->tenantAdmin);
        $response = $this->followingRedirects()->post(route('app.sales.orders.confirm', $so));

        $response->assertOk();
        $response->assertSee(route('app.sales.lines.deliver', $so->lines()->firstOrFail()), false);
        $this->assertSame('confirmed', $so->fresh()->status);
    }

    public function test_delivering_against_a_confirmed_order_creates_a_stock_move_and_releases_reservation(): void
    {
        $so = $this->draftOrder($this->rep);
        $line = $this->addLine($so);
        $this->service()->sendQuotation($so);
        $this->service()->confirm($so->fresh(), $this->tenantAdmin);

        $this->actingAs($this->rep);
        $response = $this->post(route('app.sales.lines.deliver', $line), ['qty' => '10']);

        $response->assertRedirect(route('app.sales.orders.show', $so));
        $this->assertDatabaseHas('stock_moves', [
            'reference_type' => 'sales_order_line',
            'reference_id' => $line->id,
            'from_location_id' => $this->location->id,
        ]);
        $this->assertSame('10.0000', $line->fresh()->delivered_qty);
        $this->assertSame('done', $so->fresh()->status);
    }

    public function test_sales_order_can_be_cancelled(): void
    {
        $this->actingAs($this->rep);
        $so = $this->draftOrder();

        $response = $this->post(route('app.sales.orders.cancel', $so));

        $response->assertRedirect(route('app.sales.orders.index'));
        $this->assertSame('cancelled', $so->fresh()->status);
    }
}
