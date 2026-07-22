<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Tests\TenantTestCase;

class SalesInvoiceScreensTest extends TenantTestCase
{
    private Partner $customer;

    private Product $product;

    private SalesOrder $so;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingDefaultsService::class)->provision($this->tenant);

        $this->customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id]);
        $productCategory = ProductCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'income_account_id' => app(AccountingDefaultsService::class)->accountByCode($this->tenant->id, '600')->id,
        ]);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $unit->id,
            'product_category_id' => $productCategory->id,
        ]);
        $this->so = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $this->customer->id,
            'status' => 'confirmed',
        ]);
        SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $this->so->id,
            'product_id' => $this->product->id,
            'uom_id' => $this->product->uom_id,
            'qty' => '10',
            'unit_price' => '5.0000',
        ]);

        setPermissionsTeamId($this->tenant->id);
        $this->accountant = User::factory()->for($this->tenant)->create();
        $this->accountant->assignRole('Accountant');
    }

    private function saleTaxRate(): TaxRate
    {
        return TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', 'sale')->where('percentage', '20')->firstOrFail();
    }

    public function test_confirmed_so_show_page_displays_the_create_invoice_button_for_a_user_with_permission(): void
    {
        // Tenant Admin holds every permission, including "post journal
        // entries" and "create sales orders" (needed to view the page).
        $response = $this->actingAs($this->tenantAdmin)->get(route('app.sales.orders.show', $this->so));

        $response->assertOk();
        $response->assertSee(route('app.accounting.sales-invoices.store'), false);
    }

    public function test_confirmed_so_show_page_hides_the_create_invoice_button_without_permission(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $representative = User::factory()->for($this->tenant)->create();
        $representative->assignRole('Sales Representative');

        $response = $this->actingAs($representative)->get(route('app.sales.orders.show', $this->so));

        $response->assertOk();
        $response->assertDontSee(route('app.accounting.sales-invoices.store'), false);
    }

    public function test_a_draft_invoice_can_be_created_from_a_sales_order(): void
    {
        $response = $this->actingAs($this->accountant)
            ->post(route('app.accounting.sales-invoices.store'), ['sales_order_id' => $this->so->id]);

        $invoice = Invoice::firstOrFail();
        $response->assertRedirect(route('app.accounting.sales-invoices.show', $invoice));
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('sale', $invoice->type);
        $this->assertSame($this->customer->id, $invoice->partner_id);
        $this->assertSame($this->so->id, $invoice->source_id);
    }

    public function test_index_page_lists_sales_invoices(): void
    {
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->create($this->tenant->id, $this->customer->id, 'sale', $this->so);
        // Add a line with tax rate to verify the eager-loading prevents N+1
        $invoiceService->addLine($invoice, $this->product->id, '10', '5.0000', $this->saleTaxRate()->id);

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.sales-invoices.index'));

        $response->assertOk();
        $response->assertSee($this->customer->name);
        $response->assertSee(route('app.accounting.sales-invoices.show', $invoice), false);
        // Verify total is rendered (this exercises the total() method which depends on taxRate being loaded)
        $response->assertSee('50'); // 10 qty * 5.00 unit_price
    }

    public function test_index_page_does_not_n_plus_one_when_loading_invoice_lines_and_tax_rates(): void
    {
        $invoiceService = app(InvoiceService::class);
        $taxRateId = $this->saleTaxRate()->id;

        $invoiceOne = $invoiceService->create($this->tenant->id, $this->customer->id, 'sale', $this->so);
        $invoiceService->addLine($invoiceOne, $this->product->id, '10', '5.0000', $taxRateId);

        // Warm up the permission/role cache with an untracked request first,
        // so the one-off Spatie permission queries (which only fire on the
        // very first authorization check in the process) don't pollute the
        // query counts we are about to compare.
        $this->actingAs($this->accountant)->get(route('app.accounting.sales-invoices.index'))->assertOk();

        DB::enableQueryLog();
        $this->actingAs($this->accountant)->get(route('app.accounting.sales-invoices.index'))->assertOk();
        $queryCountForOneInvoice = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        // A second customer/SO/product/line combination avoids the "same
        // invoice line" relation cache masking a real per-invoice N+1.
        $customerTwo = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $uomCategoryTwo = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unitTwo = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategoryTwo->id]);
        $productTwo = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unitTwo->id]);
        $soTwo = SalesOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $customerTwo->id,
            'status' => 'confirmed',
        ]);
        SalesOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $soTwo->id,
            'product_id' => $productTwo->id,
            'uom_id' => $productTwo->uom_id,
            'qty' => '10',
            'unit_price' => '5.0000',
        ]);
        $invoiceTwo = $invoiceService->create($this->tenant->id, $customerTwo->id, 'sale', $soTwo);
        $invoiceService->addLine($invoiceTwo, $productTwo->id, '10', '5.0000', $taxRateId);

        DB::enableQueryLog();
        $this->actingAs($this->accountant)->get(route('app.accounting.sales-invoices.index'))->assertOk();
        $queryCountForTwoInvoices = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        // If `lines.taxRate` were lazy-loaded instead of eager-loaded, adding
        // a second invoice with its own line/tax rate would add extra
        // queries. Eager-loading keeps the count constant regardless of how
        // many invoices/lines are rendered.
        $this->assertSame($queryCountForOneInvoice, $queryCountForTwoInvoices);
    }

    public function test_show_page_offers_line_selection_from_the_sales_orders_own_lines(): void
    {
        $invoice = app(InvoiceService::class)
            ->create($this->tenant->id, $this->customer->id, 'sale', $this->so);

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.sales-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee($this->product->name);
        $response->assertSee(route('app.accounting.sales-invoices.lines.store', $invoice), false);
    }

    public function test_a_line_can_be_added_referencing_a_sales_order_line_product(): void
    {
        $invoice = app(InvoiceService::class)
            ->create($this->tenant->id, $this->customer->id, 'sale', $this->so);
        $taxRate = $this->saleTaxRate();

        $response = $this->actingAs($this->accountant)->post(
            route('app.accounting.sales-invoices.lines.store', $invoice),
            [
                'product_id' => $this->product->id,
                'qty' => '10',
                'unit_price' => '5.0000',
                'tax_rate_id' => $taxRate->id,
            ]
        );

        $response->assertRedirect(route('app.accounting.sales-invoices.show', $invoice));
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'qty' => '10.0000',
            'unit_price' => '5.0000',
            'tax_rate_id' => $taxRate->id,
        ]);
    }

    public function test_a_line_exceeding_the_sales_order_quantity_is_still_accepted_because_no_three_way_match_applies_to_sales(): void
    {
        $invoice = app(InvoiceService::class)
            ->create($this->tenant->id, $this->customer->id, 'sale', $this->so);

        $response = $this->actingAs($this->accountant)->post(
            route('app.accounting.sales-invoices.lines.store', $invoice),
            [
                'product_id' => $this->product->id,
                'qty' => '11',
                'unit_price' => '5.0000',
            ]
        );

        $response->assertRedirect(route('app.accounting.sales-invoices.show', $invoice));
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'qty' => '11.0000',
            'unit_price' => '5.0000',
        ]);
    }

    public function test_posting_the_invoice_creates_a_journal_entry_and_marks_it_posted(): void
    {
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->create($this->tenant->id, $this->customer->id, 'sale', $this->so);
        $invoiceService->addLine($invoice, $this->product->id, '10', '5.0000', $this->saleTaxRate()->id);

        $response = $this->actingAs($this->accountant)->post(route('app.accounting.sales-invoices.post', $invoice));

        $response->assertRedirect(route('app.accounting.sales-invoices.show', $invoice));
        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertSame(1, JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_a_user_without_the_permission_gets_a_403_on_the_sales_invoices_index(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get(route('app.accounting.sales-invoices.index'))
            ->assertForbidden();
    }
}
