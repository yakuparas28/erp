<?php

namespace Tests\Feature\Sales;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Tests\TenantTestCase;

class PortalQuoteTest extends TenantTestCase
{
    private SalesOrder $so;

    protected function setUp(): void
    {
        parent::setUp();

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $uom->id,
            'name' => 'Test Ürün',
            'reservation_method' => 'manual',
        ]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $stock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal']);
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);

        $this->so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $customer->id,
            'location_id' => $stock->id,
            'status' => 'quotation_sent',
            'sent_at' => now(),
            'access_token' => 'test-token-123456',
            'created_by' => $this->tenantAdmin->id,
        ]);
        SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $this->so->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'qty' => '2',
            'unit_price' => '100',
        ]);
    }

    public function test_portal_quote_page_is_publicly_accessible_via_token(): void
    {
        $this->get(route('portal.quote', ['token' => 'test-token-123456']))
            ->assertOk()
            ->assertSee('SO-'.str_pad((string) $this->so->id, 5, '0', STR_PAD_LEFT))
            ->assertSee('Test Ürün');
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->get(route('portal.quote', ['token' => 'unknown']))
            ->assertNotFound();
    }

    public function test_customer_can_accept_quotation_and_confirms_the_order(): void
    {
        $this->post(route('portal.quote.accept', ['token' => 'test-token-123456']))
            ->assertRedirect(route('portal.quote', ['token' => 'test-token-123456']));

        $this->so->refresh();
        $this->assertSame('confirmed', $this->so->status);
        $this->assertNotNull($this->so->customer_confirmed_at);
    }

    public function test_customer_can_decline_quotation(): void
    {
        $this->post(route('portal.quote.decline', ['token' => 'test-token-123456']))
            ->assertRedirect(route('portal.quote', ['token' => 'test-token-123456']));

        $this->so->refresh();
        $this->assertSame('cancelled', $this->so->status);
        $this->assertNotNull($this->so->customer_declined_at);
    }

    public function test_accept_on_already_confirmed_shows_status_without_error(): void
    {
        $this->so->update(['status' => 'confirmed', 'customer_confirmed_at' => now()->subDay()]);

        $this->post(route('portal.quote.accept', ['token' => 'test-token-123456']))
            ->assertRedirect(route('portal.quote', ['token' => 'test-token-123456']));

        $this->assertSame('confirmed', $this->so->fresh()->status);
    }
}
