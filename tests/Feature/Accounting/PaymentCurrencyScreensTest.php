<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\ExchangeRateService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Accounting\Services\PaymentService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Sales\Models\SalesOrder;
use Tests\TenantTestCase;

class PaymentCurrencyScreensTest extends TenantTestCase
{
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingDefaultsService::class)->provision($this->tenant);

        setPermissionsTeamId($this->tenant->id);
        $this->accountant = User::factory()->for($this->tenant)->create();
        $this->accountant->assignRole('Accountant');
    }

    private function invoices(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    private function journalOfType(string $type): Journal
    {
        return Journal::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', $type)->firstOrFail();
    }

    private function currency(string $code): Currency
    {
        return Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', $code)->firstOrFail();
    }

    private function recordRate(Currency $currency, string $date, string $rate): void
    {
        app(ExchangeRateService::class)->recordManualRate($this->tenant->id, $currency->id, $date, $rate, $rate);
    }

    private function productWithIncomeCategory(): Product
    {
        $defaults = app(AccountingDefaultsService::class);

        $category = ProductCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'income_account_id' => $defaults->accountByCode($this->tenant->id, '600')->id,
        ]);

        return Product::factory()->create(['tenant_id' => $this->tenant->id, 'product_category_id' => $category->id]);
    }

    /**
     * A posted sale invoice in the given currency (or TRY when null): total
     * = qty * unitPrice (no VAT).
     */
    private function postedSaleInvoice(Partner $partner, string $qty, string $unitPrice, ?Currency $currency = null): Invoice
    {
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();

        $invoice = $this->invoices()->create($this->tenant->id, $partner->id, 'sale', $salesOrder, $currency?->id);
        $this->invoices()->addLine($invoice, $product->id, $qty, $unitPrice, null);
        $this->invoices()->post($invoice, $this->accountant);

        return $invoice->fresh();
    }

    public function test_index_page_offers_a_currency_select_in_the_new_payment_form(): void
    {
        $usd = $this->currency('USD');

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.payments.index'));

        $response->assertOk();
        $response->assertSee('<option value="'.$usd->id.'">USD</option>', false);
        $response->assertSee(route('app.accounting.payments.store'), false);
    }

    public function test_a_payment_can_be_created_in_a_foreign_currency_and_locks_the_exchange_rate(): void
    {
        $usd = $this->currency('USD');
        $this->recordRate($usd, now()->toDateString(), '32.000000');

        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $cashJournal = $this->journalOfType('cash');

        $response = $this->actingAs($this->accountant)->post(route('app.accounting.payments.store'), [
            'partner_id' => $partner->id,
            'journal_id' => $cashJournal->id,
            'amount' => '100.0000',
            'payment_date' => now()->toDateString(),
            'currency_id' => $usd->id,
        ]);

        $payment = Payment::firstOrFail();
        $response->assertRedirect(route('app.accounting.payments.show', $payment));
        $this->assertSame($usd->id, $payment->currency_id);
        $this->assertSame('32.000000', $payment->exchange_rate_used);
    }

    public function test_open_invoices_list_only_shows_invoices_in_the_same_currency_as_the_payment(): void
    {
        $usd = $this->currency('USD');
        $eur = $this->currency('EUR');
        $this->recordRate($usd, now()->toDateString(), '30.000000');
        $this->recordRate($eur, now()->toDateString(), '35.000000');

        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $usdInvoice = $this->postedSaleInvoice($partner, '10', '5.0000', $usd);
        $eurInvoice = $this->postedSaleInvoice($partner, '4', '5.0000', $eur);

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create(
            $this->tenant->id, $partner->id, $cashJournal->id, '50.0000', now()->toDateString(), $usd->id
        );

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.payments.show', $payment));

        $response->assertOk();
        $response->assertSee('#'.$usdInvoice->id);
        $response->assertDontSee('#'.$eurInvoice->id);
    }

    public function test_show_page_displays_the_realized_fx_difference_for_an_allocation(): void
    {
        $usd = $this->currency('USD');
        $this->recordRate($usd, now()->toDateString(), '30.000000');
        $this->recordRate($usd, now()->addDay()->toDateString(), '32.000000');

        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = $this->postedSaleInvoice($partner, '1', '100.0000', $usd);

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create(
            $this->tenant->id, $partner->id, $cashJournal->id, '100.0000', now()->addDay()->toDateString(), $usd->id
        );
        $this->payments()->allocate($payment, $invoice, '100.0000');

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.payments.show', $payment));

        $response->assertOk();
        $response->assertSee('200.0000');
    }

    public function test_show_page_displays_an_em_dash_when_an_allocation_has_no_fx_difference(): void
    {
        $usd = $this->currency('USD');
        $this->recordRate($usd, now()->toDateString(), '30.000000');

        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = $this->postedSaleInvoice($partner, '1', '50.0000', $usd);

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create(
            $this->tenant->id, $partner->id, $cashJournal->id, '50.0000', now()->toDateString(), $usd->id
        );
        $this->payments()->allocate($payment, $invoice, '50.0000');

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.payments.show', $payment));

        $response->assertOk();
        $response->assertSee('—');
    }
}
