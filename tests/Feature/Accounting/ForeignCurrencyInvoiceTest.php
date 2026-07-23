<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\ExchangeRate;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Sales\Models\SalesOrder;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class ForeignCurrencyInvoiceTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingDefaultsService::class)->provision($this->tenant);
    }

    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function accountant(): User
    {
        setPermissionsTeamId($this->tenant->id);
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('Accountant');

        return $user;
    }

    private function saleTaxRate(): TaxRate
    {
        return TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', 'sale')->where('percentage', '20')->firstOrFail();
    }

    private function purchaseTaxRate(): TaxRate
    {
        return TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', 'purchase')->where('percentage', '20')->firstOrFail();
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

    private function usd(): Currency
    {
        return Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'USD')->firstOrFail();
    }

    private function usdWithRateOf30(): Currency
    {
        $usd = $this->usd();

        ExchangeRate::factory()->for($this->tenant)->create([
            'currency_id' => $usd->id,
            'rate_date' => now()->toDateString(),
            'buy_rate' => '30.000000',
            'sell_rate' => '30.100000',
        ]);

        return $usd;
    }

    public function test_posting_a_foreign_currency_sale_invoice_converts_amounts_to_tl(): void
    {
        $usd = $this->usdWithRateOf30();
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();
        $taxRate = $this->saleTaxRate();

        $invoice = $this->service()->create($this->tenant->id, $partner->id, 'sale', $salesOrder, $usd->id);
        $this->assertSame('30.000000', $invoice->exchange_rate_used);

        $this->service()->addLine($invoice, $product->id, '1', '100.0000', $taxRate->id);

        $this->service()->post($invoice, $this->accountant());

        $entry = JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $defaults = app(AccountingDefaultsService::class);
        $receivables = $defaults->accountByCode($this->tenant->id, '120');
        $incomeAccount = $defaults->accountByCode($this->tenant->id, '600');
        $outputTax = $defaults->accountByCode($this->tenant->id, '391');

        $debitLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $receivables->id)->firstOrFail();
        $incomeLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $incomeAccount->id)->firstOrFail();
        $taxLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $outputTax->id)->firstOrFail();

        // $100 * 20% KDV = $20 tax, $120 gross. Kur 30 ile TL: 3000 subtotal, 600 KDV, 3600 brüt.
        $this->assertSame('3600.0000', $debitLine->debit);
        $this->assertSame('3000.0000', $incomeLine->credit);
        $this->assertSame('600.0000', $taxLine->credit);
    }

    public function test_posting_a_foreign_currency_purchase_invoice_converts_the_tax_amount_to_tl(): void
    {
        $usd = $this->usdWithRateOf30();
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
        $taxRate = $this->purchaseTaxRate();

        $invoice = $this->service()->create($this->tenant->id, $supplier->id, 'purchase', $po, $usd->id);
        $this->service()->addLine($invoice, $product->id, '1', '100.0000', $taxRate->id);

        $this->service()->post($invoice, $this->accountant());

        $entry = JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $defaults = app(AccountingDefaultsService::class);
        $taxAccount = $defaults->accountByCode($this->tenant->id, '191');
        $payablesAccount = $defaults->accountByCode($this->tenant->id, '320');

        $debitLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $taxAccount->id)->firstOrFail();
        $creditLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $payablesAccount->id)->firstOrFail();

        // $100 * 20% KDV = $20 tax. Kur 30 ile TL: 600.0000.
        $this->assertSame('600.0000', $debitLine->debit);
        $this->assertSame('600.0000', $creditLine->credit);
    }

    public function test_posting_a_local_currency_invoice_is_unaffected_by_the_currency_conversion(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();
        $taxRate = $this->saleTaxRate();

        $invoice = $this->service()->create($this->tenant->id, $partner->id, 'sale', $salesOrder);
        $this->assertNull($invoice->currency_id);
        $this->assertNull($invoice->exchange_rate_used);

        $this->service()->addLine($invoice, $product->id, '10', '5.0000', $taxRate->id);

        $this->service()->post($invoice, $this->accountant());

        $entry = JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $defaults = app(AccountingDefaultsService::class);
        $receivables = $defaults->accountByCode($this->tenant->id, '120');
        $incomeAccount = $defaults->accountByCode($this->tenant->id, '600');
        $outputTax = $defaults->accountByCode($this->tenant->id, '391');

        $debitLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $receivables->id)->firstOrFail();
        $incomeLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $incomeAccount->id)->firstOrFail();
        $taxLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $outputTax->id)->firstOrFail();

        // 10 * 5 = 50 subtotal, tax rate 20% => 10.0000 tax, total 60.0000 (kur yok, çevrim yok).
        $this->assertSame('60.0000', $debitLine->debit);
        $this->assertSame('50.0000', $incomeLine->credit);
        $this->assertSame('10.0000', $taxLine->credit);
    }

    public function test_creating_an_invoice_with_a_currency_missing_an_exchange_rate_fails_with_422(): void
    {
        $usd = $this->usd();
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);

        try {
            $this->service()->create($this->tenant->id, $partner->id, 'sale', $salesOrder, $usd->id);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('invoices', 0);
    }
}
