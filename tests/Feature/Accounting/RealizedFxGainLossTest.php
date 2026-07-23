<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\ExchangeRateService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Accounting\Services\PaymentService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Sales\Models\SalesOrder;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class RealizedFxGainLossTest extends TenantTestCase
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

    private function accountant(): User
    {
        setPermissionsTeamId($this->tenant->id);
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('Accountant');

        return $user;
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

    private function purchaseTaxRate(): TaxRate
    {
        return TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', 'purchase')->where('percentage', '20')->firstOrFail();
    }

    public function test_a_sale_invoice_paid_at_a_higher_rate_records_the_gain_in_646(): void
    {
        $usd = $this->currency('USD');
        $this->recordRate($usd, now()->toDateString(), '30.000000');
        $this->recordRate($usd, now()->addDay()->toDateString(), '32.000000');

        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();

        $invoice = $this->invoices()->create($this->tenant->id, $partner->id, 'sale', $salesOrder, $usd->id);
        $this->assertSame('30.000000', $invoice->exchange_rate_used);
        $this->invoices()->addLine($invoice, $product->id, '1', '100.0000', null);
        $this->invoices()->post($invoice, $this->accountant());

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create(
            $this->tenant->id, $partner->id, $cashJournal->id, '100.0000', now()->addDay()->toDateString(), $usd->id
        );
        $this->assertSame('32.000000', $payment->exchange_rate_used);

        $this->payments()->allocate($payment, $invoice, '100.0000');

        $entry = JournalEntry::withoutGlobalScopes()
            ->where('reference_type', 'payment')->where('reference_id', $payment->id)->firstOrFail();

        $this->assertCount(3, $entry->lines);

        $gainAccount = app(AccountingDefaultsService::class)->accountByCode($this->tenant->id, '646');
        $gainLine = $entry->lines->firstWhere('account_id', $gainAccount->id);

        // (32 - 30) * 100 = 200 kambiyo karı, 646'ya alacak (kredi).
        $this->assertSame('200.0000', $gainLine->credit);
        $this->assertSame('0.0000', $gainLine->debit);

        $this->assertDatabaseHas('fx_revaluations', [
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'type' => 'realized',
            'difference_amount' => '200.0000',
        ]);
    }

    public function test_a_purchase_invoice_paid_at_a_higher_rate_records_the_loss_in_656(): void
    {
        $usd = $this->currency('USD');
        $this->recordRate($usd, now()->toDateString(), '30.000000');
        $this->recordRate($usd, now()->addDay()->toDateString(), '32.000000');

        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $supplier->id,
            'bill_control_policy' => 'ordered_qty',
        ]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        PurchaseOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'uom_id' => $product->uom_id,
            'qty' => '1',
        ]);

        $invoice = $this->invoices()->create($this->tenant->id, $supplier->id, 'purchase', $po, $usd->id);
        $this->invoices()->addLine($invoice, $product->id, '1', '100.0000', $this->purchaseTaxRate()->id);
        $this->invoices()->post($invoice, $this->accountant());

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create(
            $this->tenant->id, $supplier->id, $cashJournal->id, '100.0000', now()->addDay()->toDateString(), $usd->id
        );

        $this->payments()->allocate($payment, $invoice, '100.0000');

        $entry = JournalEntry::withoutGlobalScopes()
            ->where('reference_type', 'payment')->where('reference_id', $payment->id)->firstOrFail();

        $this->assertCount(3, $entry->lines);

        $lossAccount = app(AccountingDefaultsService::class)->accountByCode($this->tenant->id, '656');
        $lossLine = $entry->lines->firstWhere('account_id', $lossAccount->id);

        // Alışta kur artışı zarardır: (32 - 30) * 100 = 200, 656'ya borç.
        $this->assertSame('200.0000', $lossLine->debit);
        $this->assertSame('0.0000', $lossLine->credit);

        $this->assertDatabaseHas('fx_revaluations', [
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'type' => 'realized',
            'difference_amount' => '-200.0000',
        ]);
    }

    public function test_no_fx_difference_line_is_added_when_the_payment_rate_matches_the_invoice_rate(): void
    {
        $usd = $this->currency('USD');
        $this->recordRate($usd, now()->toDateString(), '30.000000');

        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();

        $invoice = $this->invoices()->create($this->tenant->id, $partner->id, 'sale', $salesOrder, $usd->id);
        $this->invoices()->addLine($invoice, $product->id, '1', '50.0000', null);
        $this->invoices()->post($invoice, $this->accountant());

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create($this->tenant->id, $partner->id, $cashJournal->id, '50.0000', now()->toDateString(), $usd->id);

        $this->payments()->allocate($payment, $invoice, '50.0000');

        $entry = JournalEntry::withoutGlobalScopes()
            ->where('reference_type', 'payment')->where('reference_id', $payment->id)->firstOrFail();

        $this->assertCount(2, $entry->lines);
        $this->assertDatabaseCount('fx_revaluations', 0);
    }

    public function test_allocating_a_payment_to_an_invoice_in_a_different_currency_fails(): void
    {
        $usd = $this->currency('USD');
        $eur = $this->currency('EUR');
        $this->recordRate($usd, now()->toDateString(), '30.000000');
        $this->recordRate($eur, now()->toDateString(), '35.000000');

        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();

        $invoice = $this->invoices()->create($this->tenant->id, $partner->id, 'sale', $salesOrder, $usd->id);
        $this->invoices()->addLine($invoice, $product->id, '1', '50.0000', null);
        $this->invoices()->post($invoice, $this->accountant());

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create($this->tenant->id, $partner->id, $cashJournal->id, '50.0000', now()->toDateString(), $eur->id);

        try {
            $this->payments()->allocate($payment, $invoice, '50.0000');
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertDatabaseCount('fx_revaluations', 0);
    }

    public function test_a_local_currency_invoice_payment_never_produces_a_fx_difference(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();

        $invoice = $this->invoices()->create($this->tenant->id, $partner->id, 'sale', $salesOrder);
        $this->invoices()->addLine($invoice, $product->id, '10', '5.0000', null);
        $this->invoices()->post($invoice, $this->accountant());

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create($this->tenant->id, $partner->id, $cashJournal->id, '50.0000', now()->toDateString());

        $this->payments()->allocate($payment, $invoice, '50.0000');

        $entry = JournalEntry::withoutGlobalScopes()
            ->where('reference_type', 'payment')->where('reference_id', $payment->id)->firstOrFail();

        $this->assertCount(2, $entry->lines);
        $this->assertDatabaseCount('fx_revaluations', 0);
    }
}
