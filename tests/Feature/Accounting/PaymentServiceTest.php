<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\AccountingDefaultsService;
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

class PaymentServiceTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingDefaultsService::class)->provision($this->tenant);
    }

    private function service(): PaymentService
    {
        return app(PaymentService::class);
    }

    private function invoices(): InvoiceService
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

    private function journalOfType(string $type): Journal
    {
        return Journal::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', $type)->firstOrFail();
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

    /**
     * Posted bir satınalma faturası (KDV'li) döner: subtotal 50, tax 10, total 60.
     */
    private function postedPurchaseInvoice(Partner $supplier): Invoice
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $supplier->id,
            'bill_control_policy' => 'ordered_qty',
        ]);
        PurchaseOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'uom_id' => $product->uom_id,
            'qty' => '10',
        ]);

        $invoice = $this->invoices()->create($this->tenant->id, $supplier->id, 'purchase', $po);
        $this->invoices()->addLine($invoice, $product->id, '10', '5.0000', $this->purchaseTaxRate()->id);
        $this->invoices()->post($invoice, $this->accountant());

        return $invoice->fresh();
    }

    /**
     * Posted bir satış faturası döner: total = qty * unitPrice (KDV'siz).
     */
    private function postedSaleInvoice(Partner $partner, string $qty, string $unitPrice): Invoice
    {
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();

        $invoice = $this->invoices()->create($this->tenant->id, $partner->id, 'sale', $salesOrder);
        $this->invoices()->addLine($invoice, $product->id, $qty, $unitPrice, null);
        $this->invoices()->post($invoice, $this->accountant());

        return $invoice->fresh();
    }

    public function test_full_payment_to_a_supplier_marks_the_purchase_invoice_as_paid_and_debits_payables(): void
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $invoice = $this->postedPurchaseInvoice($supplier);
        $this->assertSame('60.0000', $invoice->total());

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->service()->create($this->tenant->id, $supplier->id, $cashJournal->id, '60.0000', now()->toDateString());

        $this->service()->allocate($payment, $invoice, '60.0000');

        $this->assertSame('paid', $invoice->fresh()->status);

        $defaults = app(AccountingDefaultsService::class);
        $payables = $defaults->accountByCode($this->tenant->id, '320');
        $cash = $defaults->accountByCode($this->tenant->id, '100');

        $entry = JournalEntry::withoutGlobalScopes()
            ->where('reference_type', 'payment')->where('reference_id', $payment->id)->firstOrFail();

        $debitLine = $entry->lines->firstWhere('account_id', $payables->id);
        $creditLine = $entry->lines->firstWhere('account_id', $cash->id);

        $this->assertSame('60.0000', $debitLine->debit);
        $this->assertSame('0.0000', $debitLine->credit);
        $this->assertSame('0.0000', $creditLine->debit);
        $this->assertSame('60.0000', $creditLine->credit);
    }

    public function test_partial_payment_from_a_customer_keeps_the_sale_invoice_posted(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = $this->postedSaleInvoice($partner, '10', '5.0000');
        $this->assertSame('50.0000', $invoice->total());

        $bankJournal = $this->journalOfType('bank');
        $payment = $this->service()->create($this->tenant->id, $partner->id, $bankJournal->id, '25.0000', now()->toDateString());

        $this->service()->allocate($payment, $invoice, '25.0000');

        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertSame('25.0000', $invoice->fresh()->remainingBalance());

        $defaults = app(AccountingDefaultsService::class);
        $receivables = $defaults->accountByCode($this->tenant->id, '120');
        $bank = $defaults->accountByCode($this->tenant->id, '102');

        $entry = JournalEntry::withoutGlobalScopes()
            ->where('reference_type', 'payment')->where('reference_id', $payment->id)->firstOrFail();

        $debitLine = $entry->lines->firstWhere('account_id', $bank->id);
        $creditLine = $entry->lines->firstWhere('account_id', $receivables->id);

        $this->assertSame('25.0000', $debitLine->debit);
        $this->assertSame('0.0000', $debitLine->credit);
        $this->assertSame('0.0000', $creditLine->debit);
        $this->assertSame('25.0000', $creditLine->credit);
    }

    public function test_a_single_payment_can_be_allocated_across_two_invoices(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $firstInvoice = $this->postedSaleInvoice($partner, '8', '5.0000');
        $secondInvoice = $this->postedSaleInvoice($partner, '4', '5.0000');

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->service()->create($this->tenant->id, $partner->id, $cashJournal->id, '60.0000', now()->toDateString());

        $this->service()->allocate($payment, $firstInvoice, '40.0000');
        $this->service()->allocate($payment, $secondInvoice, '20.0000');

        $this->assertSame('paid', $firstInvoice->fresh()->status);
        $this->assertSame('paid', $secondInvoice->fresh()->status);
        $this->assertSame('0.0000', $payment->fresh()->unallocatedAmount());
    }

    public function test_allocation_exceeding_the_payments_unallocated_amount_fails(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = $this->postedSaleInvoice($partner, '40', '5.0000');

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->service()->create($this->tenant->id, $partner->id, $cashJournal->id, '100.0000', now()->toDateString());

        try {
            $this->service()->allocate($payment, $invoice, '150.0000');
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_allocation_exceeding_the_invoices_remaining_balance_fails(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = $this->postedSaleInvoice($partner, '20', '5.0000');

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->service()->create($this->tenant->id, $partner->id, $cashJournal->id, '200.0000', now()->toDateString());

        try {
            $this->service()->allocate($payment, $invoice, '150.0000');
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_creating_a_payment_with_a_journal_that_is_not_cash_or_bank_fails(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $saleJournal = $this->journalOfType('sale');

        try {
            $this->service()->create($this->tenant->id, $partner->id, $saleJournal->id, '100.0000', now()->toDateString());
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('payments', 0);
    }
}
