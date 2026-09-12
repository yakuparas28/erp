<?php

namespace Tests\Feature\Sales;

use App\Mail\TemplatedMail;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Services\SalesOrderService;
use Spatie\Activitylog\Models\Activity;
use Tests\TenantTestCase;

class QuotationSendTest extends TenantTestCase
{
    private SalesOrder $so;

    private Partner $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NotificationTemplateSeeder::class);

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $stock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'type' => 'internal']);
        $this->customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id, 'email' => 'musteri@example.test']);

        $this->so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $this->customer->id,
            'location_id' => $stock->id,
            'status' => 'draft',
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

    public function test_send_quotation_sets_sent_at_and_validity_date(): void
    {
        Mail::fake();

        $this->actingAs($this->tenantAdmin);

        app(SalesOrderService::class)->sendQuotation($this->so, now()->addDays(15)->toDateString());

        $this->so->refresh();
        $this->assertSame('quotation_sent', $this->so->status);
        $this->assertNotNull($this->so->sent_at);
        $this->assertSame(now()->addDays(15)->toDateString(), $this->so->validity_date?->toDateString());
    }

    public function test_send_quotation_emails_customer(): void
    {
        Mail::fake();

        $this->actingAs($this->tenantAdmin);
        app(SalesOrderService::class)->sendQuotation($this->so);

        Mail::assertSent(TemplatedMail::class, function ($mail) {
            return $mail->hasTo('musteri@example.test');
        });
    }

    public function test_send_quotation_skips_email_when_partner_has_no_email(): void
    {
        Mail::fake();
        $this->customer->update(['email' => null]);

        $this->actingAs($this->tenantAdmin);
        app(SalesOrderService::class)->sendQuotation($this->so);

        Mail::assertNothingSent();
        $this->assertSame('quotation_sent', $this->so->fresh()->status);
    }

    public function test_send_quotation_logs_activity(): void
    {
        Mail::fake();
        $this->actingAs($this->tenantAdmin);

        app(SalesOrderService::class)->sendQuotation($this->so);

        $this->assertTrue(
            Activity::where('log_name', 'default')->where('description', 'sales_order.quotation_sent')->exists()
        );
    }

    public function test_quotation_pdf_view_renders(): void
    {
        $this->so->update(['status' => 'quotation_sent', 'sent_at' => now(), 'validity_date' => now()->addDays(30)]);

        $this->actingAs($this->tenantAdmin)
            ->get(route('app.sales.orders.quotation', $this->so))
            ->assertOk()
            ->assertSee('SO-'.str_pad((string) $this->so->id, 5, '0', STR_PAD_LEFT))
            ->assertSee($this->customer->name);
    }
}
