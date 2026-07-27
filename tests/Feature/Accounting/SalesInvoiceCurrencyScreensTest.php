<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\ExchangeRateService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Tests\TenantTestCase;

class SalesInvoiceCurrencyScreensTest extends TenantTestCase
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
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $unit->id]);
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

    private function usd(): Currency
    {
        return Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'USD')->firstOrFail();
    }

    public function test_index_page_lists_currency_options_and_invoice_currency_column(): void
    {
        $usd = $this->usd();
        app(ExchangeRateService::class)->recordManualRate(
            $this->tenant->id,
            $usd->id,
            now()->toDateString(),
            '30.000000',
            '30.100000',
        );

        $invoiceService = app(InvoiceService::class);
        $invoiceService->create($this->tenant->id, $this->customer->id, 'sale', $this->so, $usd->id);

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.sales-invoices.index'));

        $response->assertOk();
        // Currency select option in the "create invoice" modal.
        $response->assertSee('<option value="'.$usd->id.'">USD</option>', false);
        // Currency column for the created invoice's row.
        $response->assertSee('USD');
    }

    public function test_selecting_a_currency_with_a_locked_rate_creates_an_invoice_with_the_rate_locked(): void
    {
        $usd = $this->usd();
        app(ExchangeRateService::class)->recordManualRate(
            $this->tenant->id,
            $usd->id,
            now()->toDateString(),
            '30.000000',
            '30.100000',
        );

        $response = $this->actingAs($this->accountant)->post(route('app.accounting.sales-invoices.store'), [
            'sales_order_id' => $this->so->id,
            'currency_id' => $usd->id,
        ]);

        $invoice = Invoice::firstOrFail();
        $response->assertRedirect(route('app.accounting.sales-invoices.show', $invoice));
        $this->assertSame($usd->id, $invoice->currency_id);
        $this->assertSame('30.000000', $invoice->exchange_rate_used);
    }

    public function test_show_page_displays_the_locked_rate_and_the_tl_equivalent_total(): void
    {
        $usd = $this->usd();
        app(ExchangeRateService::class)->recordManualRate(
            $this->tenant->id,
            $usd->id,
            now()->toDateString(),
            '30.000000',
            '30.100000',
        );

        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->create($this->tenant->id, $this->customer->id, 'sale', $this->so, $usd->id);
        $invoiceService->addLine($invoice, $this->product->id, '10', '5.0000', null);

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.sales-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('USD');
        $response->assertSee('30.000000');
        // Total in invoice currency is 50.0000, TL equivalent at rate 30 is 1500.0000
        $response->assertSee('1500.0000');
    }

    public function test_creating_an_invoice_without_selecting_a_currency_leaves_currency_id_null(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('app.accounting.sales-invoices.store'), [
            'sales_order_id' => $this->so->id,
            'currency_id' => '',
        ]);

        $invoice = Invoice::firstOrFail();
        $response->assertRedirect(route('app.accounting.sales-invoices.show', $invoice));
        $this->assertNull($invoice->currency_id);
        $this->assertNull($invoice->exchange_rate_used);
    }

    public function test_selecting_a_currency_without_a_defined_exchange_rate_shows_an_error_and_does_not_create_an_invoice(): void
    {
        $usd = $this->usd();

        $response = $this->actingAs($this->accountant)->post(route('app.accounting.sales-invoices.store'), [
            'sales_order_id' => $this->so->id,
            'currency_id' => $usd->id,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('invoices', 0);
    }
}
