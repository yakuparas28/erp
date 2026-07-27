<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\FxRevaluation;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\ExchangeRateService;
use Modules\Accounting\Services\FxRevaluationService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Accounting\Services\PaymentService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Sales\Models\SalesOrder;
use Tests\TenantTestCase;

class UnrealizedFxRevaluationTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingDefaultsService::class)->provision($this->tenant);
    }

    private function invoices(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    private function revaluations(): FxRevaluationService
    {
        return app(FxRevaluationService::class);
    }

    private function accountant(): User
    {
        setPermissionsTeamId($this->tenant->id);
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('Accountant');

        return $user;
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

    private function openSaleInvoice(Currency $usd): Invoice
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();

        $invoice = $this->invoices()->create($this->tenant->id, $partner->id, 'sale', $salesOrder, $usd->id);
        $this->invoices()->addLine($invoice, $product->id, '1', '100.0000', null);
        $this->invoices()->post($invoice, $this->accountant());

        return $invoice;
    }

    public function test_an_open_foreign_currency_invoice_is_revalued_at_the_current_rate(): void
    {
        $usd = $this->currency('USD');
        $this->recordRate($usd, now()->toDateString(), '30.000000');
        $invoice = $this->openSaleInvoice($usd);
        $this->assertSame('30.000000', $invoice->exchange_rate_used);

        $asOfDate = now()->addDay()->toDateString();
        $this->recordRate($usd, $asOfDate, '33.000000');

        $created = $this->revaluations()->revaluateOpenBalances($this->tenant->id, $asOfDate);

        $this->assertCount(1, $created);

        $this->assertDatabaseHas('fx_revaluations', [
            'invoice_id' => $invoice->id,
            'payment_id' => null,
            'type' => 'unrealized',
            'difference_amount' => '300.0000',
        ]);

        $revaluation = FxRevaluation::withoutGlobalScopes()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame($asOfDate, $revaluation->revaluation_date->toDateString());

        $entry = JournalEntry::withoutGlobalScopes()
            ->where('reference_type', 'invoice')->where('reference_id', $invoice->id)
            ->where('journal_id', Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'general')->firstOrFail()->id)
            ->firstOrFail();

        $this->assertCount(2, $entry->lines);

        $receivables = app(AccountingDefaultsService::class)->accountByCode($this->tenant->id, '120');
        $gainAccount = app(AccountingDefaultsService::class)->accountByCode($this->tenant->id, '646');

        $receivableLine = $entry->lines->firstWhere('account_id', $receivables->id);
        $gainLine = $entry->lines->firstWhere('account_id', $gainAccount->id);

        $this->assertSame('300.0000', $receivableLine->debit);
        $this->assertSame('0.0000', $receivableLine->credit);
        $this->assertSame('300.0000', $gainLine->credit);
        $this->assertSame('0.0000', $gainLine->debit);
    }

    public function test_a_fully_paid_invoice_is_not_included_in_revaluation(): void
    {
        $usd = $this->currency('USD');
        $this->recordRate($usd, now()->toDateString(), '30.000000');
        $invoice = $this->openSaleInvoice($usd);

        $cashJournal = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'cash')->firstOrFail();
        $payment = $this->payments()->create($this->tenant->id, $invoice->partner_id, $cashJournal->id, '100.0000', now()->toDateString(), $usd->id);
        $this->payments()->allocate($payment, $invoice, '100.0000');

        $asOfDate = now()->addDay()->toDateString();
        $this->recordRate($usd, $asOfDate, '33.000000');

        $created = $this->revaluations()->revaluateOpenBalances($this->tenant->id, $asOfDate);

        $this->assertCount(0, $created);
        $this->assertDatabaseMissing('fx_revaluations', [
            'invoice_id' => $invoice->id,
            'type' => 'unrealized',
        ]);
    }

    public function test_no_revaluation_is_created_when_the_rate_is_unchanged(): void
    {
        $usd = $this->currency('USD');
        $this->recordRate($usd, now()->toDateString(), '30.000000');
        $invoice = $this->openSaleInvoice($usd);

        $asOfDate = now()->addDay()->toDateString();
        $this->recordRate($usd, $asOfDate, '30.000000');

        $created = $this->revaluations()->revaluateOpenBalances($this->tenant->id, $asOfDate);

        $this->assertCount(0, $created);
        $this->assertDatabaseCount('fx_revaluations', 0);
    }

    public function test_the_original_invoice_exchange_rate_used_is_not_modified_by_revaluation(): void
    {
        $usd = $this->currency('USD');
        $this->recordRate($usd, now()->toDateString(), '30.000000');
        $invoice = $this->openSaleInvoice($usd);

        $asOfDate = now()->addDay()->toDateString();
        $this->recordRate($usd, $asOfDate, '33.000000');

        $this->revaluations()->revaluateOpenBalances($this->tenant->id, $asOfDate);

        $this->assertSame('30.000000', $invoice->fresh()->exchange_rate_used);
    }
}
