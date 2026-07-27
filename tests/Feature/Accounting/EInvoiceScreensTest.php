<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\EInvoiceService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Tests\TenantTestCase;

class EInvoiceScreensTest extends TenantTestCase
{
    private Partner $supplier;

    private Partner $customer;

    private Product $product;

    private Product $salesProduct;

    private PurchaseOrder $po;

    private SalesOrder $so;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingDefaultsService::class)->provision($this->tenant);

        $this->supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id]);

        $productCategory = ProductCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'income_account_id' => app(AccountingDefaultsService::class)->accountByCode($this->tenant->id, '600')->id,
        ]);
        $this->salesProduct = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $unit->id,
            'product_category_id' => $productCategory->id,
        ]);

        $this->po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $this->supplier->id,
            'status' => 'confirmed',
            'bill_control_policy' => 'ordered_qty',
        ]);
        PurchaseOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $this->po->id,
            'product_id' => $this->product->id,
            'uom_id' => $this->product->uom_id,
            'qty' => '10',
            'unit_price' => '5.0000',
        ]);

        $this->so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $this->customer->id,
            'status' => 'confirmed',
        ]);
        SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $this->so->id,
            'product_id' => $this->salesProduct->id,
            'uom_id' => $this->salesProduct->uom_id,
            'qty' => '10',
            'unit_price' => '5.0000',
        ]);

        setPermissionsTeamId($this->tenant->id);
        $this->accountant = User::factory()->for($this->tenant)->create();
        $this->accountant->assignRole('Accountant');
    }

    private function purchaseTaxRate(): TaxRate
    {
        return TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', 'purchase')->where('percentage', '20')->firstOrFail();
    }

    private function postedPurchaseInvoice(): Invoice
    {
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->create($this->tenant->id, $this->supplier->id, 'purchase', $this->po);
        $invoiceService->addLine($invoice, $this->product->id, '10', '5.0000', $this->purchaseTaxRate()->id);
        $invoiceService->post($invoice, $this->accountant);

        return $invoice->fresh();
    }

    private function postedSalesInvoice(): Invoice
    {
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->create($this->tenant->id, $this->customer->id, 'sale', $this->so);
        $invoiceService->addLine($invoice, $this->salesProduct->id, '10', '5.0000', null);
        $invoiceService->post($invoice, $this->accountant);

        return $invoice->fresh();
    }

    // -- Purchase invoices ---------------------------------------------

    public function test_draft_purchase_invoice_show_page_does_not_display_the_send_e_invoice_button(): void
    {
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->create($this->tenant->id, $this->supplier->id, 'purchase', $this->po);

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.purchase-invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee(route('app.accounting.purchase-invoices.e-invoice.send', $invoice), false);
    }

    public function test_sending_the_e_invoice_for_a_posted_purchase_invoice_marks_it_sent(): void
    {
        $invoice = $this->postedPurchaseInvoice();

        $response = $this->actingAs($this->accountant)
            ->post(route('app.accounting.purchase-invoices.e-invoice.send', $invoice));

        $response->assertRedirect(route('app.accounting.purchase-invoices.show', $invoice));
        $this->assertSame('sent', $invoice->fresh()->e_invoice_status);
    }

    public function test_sent_purchase_invoice_show_page_displays_accept_and_reject_buttons(): void
    {
        $invoice = $this->postedPurchaseInvoice();
        app(EInvoiceService::class)->send($invoice);

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.purchase-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee(route('app.accounting.purchase-invoices.e-invoice.accept', $invoice), false);
        $response->assertSee(route('app.accounting.purchase-invoices.e-invoice.reject', $invoice), false);
    }

    public function test_accepting_a_sent_purchase_e_invoice_marks_it_accepted(): void
    {
        $invoice = $this->postedPurchaseInvoice();
        app(EInvoiceService::class)->send($invoice);

        $response = $this->actingAs($this->accountant)
            ->post(route('app.accounting.purchase-invoices.e-invoice.accept', $invoice));

        $response->assertRedirect(route('app.accounting.purchase-invoices.show', $invoice));
        $this->assertSame('accepted', $invoice->fresh()->e_invoice_status);
    }

    public function test_rejecting_a_sent_purchase_e_invoice_marks_it_rejected(): void
    {
        $invoice = $this->postedPurchaseInvoice();
        app(EInvoiceService::class)->send($invoice);

        $response = $this->actingAs($this->accountant)
            ->post(route('app.accounting.purchase-invoices.e-invoice.reject', $invoice));

        $response->assertRedirect(route('app.accounting.purchase-invoices.show', $invoice));
        $this->assertSame('rejected', $invoice->fresh()->e_invoice_status);
    }

    public function test_a_user_without_the_permission_gets_a_403_when_sending_a_purchase_e_invoice(): void
    {
        $invoice = $this->postedPurchaseInvoice();

        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->post(route('app.accounting.purchase-invoices.e-invoice.send', $invoice))
            ->assertForbidden();
    }

    // -- Sales invoices ---------------------------------------------

    public function test_draft_sales_invoice_show_page_does_not_display_the_send_e_invoice_button(): void
    {
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->create($this->tenant->id, $this->customer->id, 'sale', $this->so);

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.sales-invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee(route('app.accounting.sales-invoices.e-invoice.send', $invoice), false);
    }

    public function test_sending_the_e_invoice_for_a_posted_sales_invoice_marks_it_sent(): void
    {
        $invoice = $this->postedSalesInvoice();

        $response = $this->actingAs($this->accountant)
            ->post(route('app.accounting.sales-invoices.e-invoice.send', $invoice));

        $response->assertRedirect(route('app.accounting.sales-invoices.show', $invoice));
        $this->assertSame('sent', $invoice->fresh()->e_invoice_status);
    }

    public function test_sent_sales_invoice_show_page_displays_accept_and_reject_buttons(): void
    {
        $invoice = $this->postedSalesInvoice();
        app(EInvoiceService::class)->send($invoice);

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.sales-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee(route('app.accounting.sales-invoices.e-invoice.accept', $invoice), false);
        $response->assertSee(route('app.accounting.sales-invoices.e-invoice.reject', $invoice), false);
    }

    public function test_accepting_a_sent_sales_e_invoice_marks_it_accepted(): void
    {
        $invoice = $this->postedSalesInvoice();
        app(EInvoiceService::class)->send($invoice);

        $response = $this->actingAs($this->accountant)
            ->post(route('app.accounting.sales-invoices.e-invoice.accept', $invoice));

        $response->assertRedirect(route('app.accounting.sales-invoices.show', $invoice));
        $this->assertSame('accepted', $invoice->fresh()->e_invoice_status);
    }

    public function test_rejecting_a_sent_sales_e_invoice_marks_it_rejected(): void
    {
        $invoice = $this->postedSalesInvoice();
        app(EInvoiceService::class)->send($invoice);

        $response = $this->actingAs($this->accountant)
            ->post(route('app.accounting.sales-invoices.e-invoice.reject', $invoice));

        $response->assertRedirect(route('app.accounting.sales-invoices.show', $invoice));
        $this->assertSame('rejected', $invoice->fresh()->e_invoice_status);
    }

    public function test_a_user_without_the_permission_gets_a_403_when_sending_a_sales_e_invoice(): void
    {
        $invoice = $this->postedSalesInvoice();

        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->post(route('app.accounting.sales-invoices.e-invoice.send', $invoice))
            ->assertForbidden();
    }
}
