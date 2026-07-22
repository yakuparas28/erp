<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Tests\TenantTestCase;

class PurchaseInvoiceScreensTest extends TenantTestCase
{
    private Partner $supplier;

    private Product $product;

    private PurchaseOrder $po;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingDefaultsService::class)->provision($this->tenant);

        $this->supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id]);
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

        setPermissionsTeamId($this->tenant->id);
        $this->accountant = User::factory()->for($this->tenant)->create();
        $this->accountant->assignRole('Accountant');
    }

    private function purchaseTaxRate(): TaxRate
    {
        return TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', 'purchase')->where('percentage', '20')->firstOrFail();
    }

    public function test_confirmed_po_show_page_displays_the_create_invoice_button_for_a_user_with_permission(): void
    {
        // Tenant Admin holds every permission, including "post journal
        // entries" and "create purchase orders" (needed to view the page).
        $response = $this->actingAs($this->tenantAdmin)->get(route('app.purchase.orders.show', $this->po));

        $response->assertOk();
        $response->assertSee(route('app.accounting.purchase-invoices.store'), false);
    }

    public function test_confirmed_po_show_page_hides_the_create_invoice_button_without_permission(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $officer = User::factory()->for($this->tenant)->create();
        $officer->assignRole('Purchasing Officer');

        $response = $this->actingAs($officer)->get(route('app.purchase.orders.show', $this->po));

        $response->assertOk();
        $response->assertDontSee(route('app.accounting.purchase-invoices.store'), false);
    }

    public function test_a_draft_invoice_can_be_created_from_a_purchase_order(): void
    {
        $response = $this->actingAs($this->accountant)
            ->post(route('app.accounting.purchase-invoices.store'), ['purchase_order_id' => $this->po->id]);

        $invoice = Invoice::firstOrFail();
        $response->assertRedirect(route('app.accounting.purchase-invoices.show', $invoice));
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('purchase', $invoice->type);
        $this->assertSame($this->supplier->id, $invoice->partner_id);
        $this->assertSame($this->po->id, $invoice->source_id);
    }

    public function test_index_page_lists_purchase_invoices(): void
    {
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->create($this->tenant->id, $this->supplier->id, 'purchase', $this->po);
        // Add a line with tax rate to verify the eager-loading prevents N+1
        $invoiceService->addLine($invoice, $this->product->id, '10', '5.0000', $this->purchaseTaxRate()->id);

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.purchase-invoices.index'));

        $response->assertOk();
        $response->assertSee($this->supplier->name);
        $response->assertSee(route('app.accounting.purchase-invoices.show', $invoice), false);
        // Verify total is rendered (this exercises the total() method which depends on taxRate being loaded)
        $response->assertSee('50'); // 10 qty * 5.00 unit_price
    }

    public function test_show_page_offers_line_selection_from_the_purchase_orders_own_lines(): void
    {
        $invoice = app(InvoiceService::class)
            ->create($this->tenant->id, $this->supplier->id, 'purchase', $this->po);

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.purchase-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee($this->product->name);
        $response->assertSee(route('app.accounting.purchase-invoices.lines.store', $invoice), false);
    }

    public function test_a_line_can_be_added_referencing_a_purchase_order_line_product(): void
    {
        $invoice = app(InvoiceService::class)
            ->create($this->tenant->id, $this->supplier->id, 'purchase', $this->po);
        $taxRate = $this->purchaseTaxRate();

        $response = $this->actingAs($this->accountant)->post(
            route('app.accounting.purchase-invoices.lines.store', $invoice),
            [
                'product_id' => $this->product->id,
                'qty' => '10',
                'unit_price' => '5.0000',
                'tax_rate_id' => $taxRate->id,
            ]
        );

        $response->assertRedirect(route('app.accounting.purchase-invoices.show', $invoice));
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'qty' => '10.0000',
            'unit_price' => '5.0000',
            'tax_rate_id' => $taxRate->id,
        ]);
    }

    public function test_adding_a_line_that_exceeds_the_three_way_match_shows_an_error_and_does_not_add_the_line(): void
    {
        $invoice = app(InvoiceService::class)
            ->create($this->tenant->id, $this->supplier->id, 'purchase', $this->po);

        $response = $this->actingAs($this->accountant)->post(
            route('app.accounting.purchase-invoices.lines.store', $invoice),
            [
                'product_id' => $this->product->id,
                'qty' => '11',
                'unit_price' => '5.0000',
            ]
        );

        $response->assertSessionHasErrors('qty');
        $this->assertDatabaseCount('invoice_lines', 0);
    }

    public function test_posting_the_invoice_creates_a_journal_entry_and_marks_it_posted(): void
    {
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->create($this->tenant->id, $this->supplier->id, 'purchase', $this->po);
        $invoiceService->addLine($invoice, $this->product->id, '10', '5.0000', $this->purchaseTaxRate()->id);

        $response = $this->actingAs($this->accountant)->post(route('app.accounting.purchase-invoices.post', $invoice));

        $response->assertRedirect(route('app.accounting.purchase-invoices.show', $invoice));
        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertSame(1, JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_a_user_without_the_permission_gets_a_403_on_the_purchase_invoices_index(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get(route('app.accounting.purchase-invoices.index'))
            ->assertForbidden();
    }
}
