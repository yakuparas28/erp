<?php

namespace Tests\Feature\Purchase;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Purchase\Services\PurchaseOrderService;
use Tests\TenantTestCase;

class PurchaseOrderScreensTest extends TenantTestCase
{
    private Partner $supplier;

    private Product $product;

    private Uom $unit;

    private Location $dock;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal']);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);

        setPermissionsTeamId($this->tenant->id);
        $this->officer = User::factory()->for($this->tenant)->create();
        $this->officer->assignRole('Purchasing Officer');
    }

    private function service(): PurchaseOrderService
    {
        return app(PurchaseOrderService::class);
    }

    private function draftOrder(?User $creator = null): PurchaseOrder
    {
        return $this->service()->create($this->tenant->id, $this->supplier->id, $creator ?? $this->officer);
    }

    private function addLine(PurchaseOrder $po): PurchaseOrderLine
    {
        return $this->service()->addLine($po, $this->product->id, $this->unit->id, '10', '5.0000');
    }

    public function test_index_page_lists_purchase_orders(): void
    {
        $this->actingAs($this->officer);
        $po = $this->draftOrder();

        $response = $this->get(route('app.purchase.orders.index'));

        $response->assertOk();
        $response->assertSee($this->supplier->name);
    }

    public function test_officer_can_create_a_draft_purchase_order(): void
    {
        $this->actingAs($this->officer);

        $response = $this->post(route('app.purchase.orders.store'), ['partner_id' => $this->supplier->id]);

        $po = PurchaseOrder::firstOrFail();
        $response->assertRedirect(route('app.purchase.orders.show', $po));
        $this->assertSame('draft', $po->status);
        $this->assertSame($this->officer->id, $po->created_by);
    }

    public function test_show_page_displays_the_add_line_form_for_a_draft_order(): void
    {
        $this->actingAs($this->officer);
        $po = $this->draftOrder();

        $response = $this->get(route('app.purchase.orders.show', $po));

        $response->assertOk();
        $response->assertSee($this->product->name);
        $response->assertSee(route('app.purchase.orders.lines.store', $po), false);
    }

    public function test_line_can_be_added_to_a_draft_order(): void
    {
        $this->actingAs($this->officer);
        $po = $this->draftOrder();

        $response = $this->post(route('app.purchase.orders.lines.store', $po), [
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'qty' => '10',
            'unit_price' => '5.5',
        ]);

        $response->assertRedirect(route('app.purchase.orders.show', $po));
        $this->assertDatabaseHas('purchase_order_lines', [
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'qty' => '10.0000',
            'unit_price' => '5.5000',
        ]);
    }

    public function test_order_can_be_sent_as_rfq(): void
    {
        $this->actingAs($this->officer);
        $po = $this->draftOrder();
        $this->addLine($po);

        $response = $this->followingRedirects()->post(route('app.purchase.orders.send-rfq', $po));

        $response->assertOk();
        $response->assertSee(__('Waiting for confirmation from another user (you cannot confirm your own purchase order).'));
        $this->assertSame('rfq_sent', $po->fresh()->status);
    }

    public function test_creator_cannot_confirm_own_purchase_order(): void
    {
        $this->actingAs($this->tenantAdmin);
        $po = $this->draftOrder($this->tenantAdmin);
        $this->addLine($po);
        $this->post(route('app.purchase.orders.send-rfq', $po));

        $response = $this->post(route('app.purchase.orders.confirm', $po));

        $response->assertSessionHasErrors('po');
        $response->assertRedirect();
        $this->assertSame('rfq_sent', $po->fresh()->status);
    }

    public function test_another_user_can_confirm_the_purchase_order(): void
    {
        $po = $this->draftOrder($this->officer);
        $this->addLine($po);
        $this->service()->sendRfq($po);

        $this->actingAs($this->tenantAdmin);
        $response = $this->followingRedirects()->post(route('app.purchase.orders.confirm', $po));

        $response->assertOk();
        $response->assertSee(route('app.purchase.lines.receive', $po->lines()->firstOrFail()), false);
        $this->assertSame('confirmed', $po->fresh()->status);
    }

    public function test_receiving_against_a_confirmed_order_creates_a_stock_move(): void
    {
        $po = $this->draftOrder($this->officer);
        $line = $this->addLine($po);
        $this->service()->sendRfq($po);
        $this->service()->confirm($po->fresh(), $this->tenantAdmin);

        $this->actingAs($this->officer);
        $response = $this->post(route('app.purchase.lines.receive', $line), [
            'qty' => '10',
            'receiving_location_id' => $this->dock->id,
        ]);

        $response->assertRedirect(route('app.purchase.orders.show', $po));
        $this->assertDatabaseHas('stock_moves', [
            'reference_type' => 'purchase_order_line',
            'reference_id' => $line->id,
            'to_location_id' => $this->dock->id,
        ]);
        $this->assertSame('10.0000', $line->fresh()->receivedQty());
    }

    public function test_purchase_order_can_be_cancelled(): void
    {
        $this->actingAs($this->officer);
        $po = $this->draftOrder();

        $response = $this->post(route('app.purchase.orders.cancel', $po));

        $response->assertRedirect(route('app.purchase.orders.index'));
        $this->assertSame('cancelled', $po->fresh()->status);
    }
}
