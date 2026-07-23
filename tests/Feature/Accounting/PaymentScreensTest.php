<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Accounting\Services\PaymentService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Sales\Models\SalesOrder;
use Tests\TenantTestCase;

class PaymentScreensTest extends TenantTestCase
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
     * Posted bir satış faturası döner: total = qty * unitPrice (KDV'siz).
     */
    private function postedSaleInvoice(Partner $partner, string $qty, string $unitPrice): Invoice
    {
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();

        $invoice = $this->invoices()->create($this->tenant->id, $partner->id, 'sale', $salesOrder);
        $this->invoices()->addLine($invoice, $product->id, $qty, $unitPrice, null);
        $this->invoices()->post($invoice, $this->accountant);

        return $invoice->fresh();
    }

    public function test_index_page_offers_the_new_payment_form_with_cash_and_bank_journals_only(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.payments.index'));

        $response->assertOk();
        $response->assertSee($partner->name);
        $response->assertSee(route('app.accounting.payments.store'), false);
        $response->assertSee($this->journalOfType('cash')->name);
        $response->assertSee($this->journalOfType('bank')->name);
        // Not: journal adının (ör. "Satış") düz metin olarak assertDontSee ile
        // aranması yanlış pozitif verir, çünkü kenar menüsündeki "Satış
        // Faturaları" linki de aynı kelimeyi içerir. `<option value="ID">`
        // biçimindeki kontrol de tek başına yeterli değil, çünkü partner
        // select'indeki bir option'ın ID'si tesadüfen aynı olabilir (partner
        // ve journal ayrı sayaçlar). Bu yüzden hem ID HEM AD'ı aynı option
        // etiketinde birlikte arayarak sale journal'ın select'te HİÇ
        // render edilmediğini kesin olarak doğruluyoruz.
        $saleJournal = $this->journalOfType('sale');
        $response->assertDontSee('<option value="'.$saleJournal->id.'">'.$saleJournal->name.'</option>', false);
    }

    public function test_a_payment_can_be_created_with_a_cash_journal(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $cashJournal = $this->journalOfType('cash');

        $response = $this->actingAs($this->accountant)->post(route('app.accounting.payments.store'), [
            'partner_id' => $partner->id,
            'journal_id' => $cashJournal->id,
            'amount' => '150.0000',
            'payment_date' => now()->toDateString(),
        ]);

        $payment = Payment::firstOrFail();
        $response->assertRedirect(route('app.accounting.payments.show', $payment));
        $this->assertSame($partner->id, $payment->partner_id);
        $this->assertSame($cashJournal->id, $payment->journal_id);
        $this->assertSame('150.0000', $payment->amount);
    }

    public function test_creating_a_payment_with_a_journal_that_is_not_cash_or_bank_fails(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $saleJournal = $this->journalOfType('sale');

        $response = $this->actingAs($this->accountant)->post(route('app.accounting.payments.store'), [
            'partner_id' => $partner->id,
            'journal_id' => $saleJournal->id,
            'amount' => '100.0000',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('journal_id');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_full_allocation_marks_the_invoice_as_paid_and_creates_a_journal_entry(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = $this->postedSaleInvoice($partner, '10', '5.0000');
        $this->assertSame('50.0000', $invoice->total());

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create($this->tenant->id, $partner->id, $cashJournal->id, '50.0000', now()->toDateString());

        $response = $this->actingAs($this->accountant)->post(route('app.accounting.payments.allocations.store', $payment), [
            'invoice_id' => $invoice->id,
            'amount' => '50.0000',
        ]);

        $response->assertRedirect(route('app.accounting.payments.show', $payment));
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(
            1,
            JournalEntry::withoutGlobalScopes()->where('reference_type', 'payment')->where('reference_id', $payment->id)->count(),
        );
    }

    public function test_partial_allocation_keeps_the_invoice_posted_and_computes_the_unallocated_amount(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = $this->postedSaleInvoice($partner, '10', '5.0000');

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create($this->tenant->id, $partner->id, $cashJournal->id, '50.0000', now()->toDateString());

        $response = $this->actingAs($this->accountant)->post(route('app.accounting.payments.allocations.store', $payment), [
            'invoice_id' => $invoice->id,
            'amount' => '20.0000',
        ]);

        $response->assertRedirect(route('app.accounting.payments.show', $payment));
        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertSame('30.0000', $invoice->fresh()->remainingBalance());
        $this->assertSame('30.0000', $payment->fresh()->unallocatedAmount());
    }

    public function test_an_allocation_exceeding_the_invoices_remaining_balance_shows_an_error(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = $this->postedSaleInvoice($partner, '10', '5.0000');

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create($this->tenant->id, $partner->id, $cashJournal->id, '100.0000', now()->toDateString());

        $response = $this->actingAs($this->accountant)->post(route('app.accounting.payments.allocations.store', $payment), [
            'invoice_id' => $invoice->id,
            'amount' => '75.0000',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertSame('posted', $invoice->fresh()->status);
    }

    public function test_show_page_displays_the_computed_unallocated_amount(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = $this->postedSaleInvoice($partner, '10', '5.0000');

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create($this->tenant->id, $partner->id, $cashJournal->id, '133.0000', now()->toDateString());
        $this->payments()->allocate($payment, $invoice, '30.0000');

        // Chosen so the unallocated amount (103.0000) does not collide with
        // any other number rendered on the page (payment amount 133.0000,
        // allocated amount 30.0000, invoice remaining balance 20.0000) —
        // this proves `computed_unallocated_amount` itself is rendered,
        // rather than incidentally matching an unrelated figure.
        $this->assertSame('103.0000', $payment->fresh()->unallocatedAmount());

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.payments.show', $payment));

        $response->assertOk();
        $response->assertSee('103.0000');
    }

    public function test_show_page_only_lists_open_invoices_with_a_remaining_balance(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $openInvoice = $this->postedSaleInvoice($partner, '10', '5.0000');
        $paidInvoice = $this->postedSaleInvoice($partner, '4', '5.0000');

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create($this->tenant->id, $partner->id, $cashJournal->id, '100.0000', now()->toDateString());
        $this->payments()->allocate($payment, $paidInvoice, '20.0000');

        $response = $this->actingAs($this->accountant)->get(route('app.accounting.payments.show', $payment));

        $response->assertOk();
        $response->assertSee('#'.$openInvoice->id);
        $response->assertDontSee('#'.$paidInvoice->id);
    }

    public function test_a_user_without_the_permission_gets_a_403_on_the_payments_index(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get(route('app.accounting.payments.index'))
            ->assertForbidden();
    }

    public function test_index_page_does_not_n_plus_one_when_computing_unallocated_amounts(): void
    {
        $partnerOne = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoiceOne = $this->postedSaleInvoice($partnerOne, '10', '5.0000');
        $cashJournal = $this->journalOfType('cash');
        $paymentOne = $this->payments()->create($this->tenant->id, $partnerOne->id, $cashJournal->id, '50.0000', now()->toDateString());
        $this->payments()->allocate($paymentOne, $invoiceOne, '20.0000');

        // Warm up the permission/role cache with an untracked request first,
        // so the one-off Spatie permission queries don't pollute the query
        // counts we are about to compare.
        $this->actingAs($this->accountant)->get(route('app.accounting.payments.index'))->assertOk();

        DB::enableQueryLog();
        $this->actingAs($this->accountant)->get(route('app.accounting.payments.index'))->assertOk();
        $queryCountForOnePayment = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $partnerTwo = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoiceTwo = $this->postedSaleInvoice($partnerTwo, '4', '5.0000');
        $bankJournal = $this->journalOfType('bank');
        $paymentTwo = $this->payments()->create($this->tenant->id, $partnerTwo->id, $bankJournal->id, '20.0000', now()->toDateString());
        $this->payments()->allocate($paymentTwo, $invoiceTwo, '10.0000');

        DB::enableQueryLog();
        $this->actingAs($this->accountant)->get(route('app.accounting.payments.index'))->assertOk();
        $queryCountForTwoPayments = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->assertSame($queryCountForOnePayment, $queryCountForTwoPayments);
    }

    public function test_show_page_does_not_n_plus_one_when_computing_open_invoices_remaining_balance(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoiceOne = $this->postedSaleInvoice($partner, '10', '5.0000');
        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create($this->tenant->id, $partner->id, $cashJournal->id, '100.0000', now()->toDateString());

        $this->actingAs($this->accountant)->get(route('app.accounting.payments.show', $payment))->assertOk();

        DB::enableQueryLog();
        $this->actingAs($this->accountant)->get(route('app.accounting.payments.show', $payment))->assertOk();
        $queryCountForOneOpenInvoice = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $invoiceTwo = $this->postedSaleInvoice($partner, '4', '5.0000');

        DB::enableQueryLog();
        $this->actingAs($this->accountant)->get(route('app.accounting.payments.show', $payment))->assertOk();
        $queryCountForTwoOpenInvoices = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->assertSame($queryCountForOneOpenInvoice, $queryCountForTwoOpenInvoices);
        $this->assertNotNull($invoiceTwo);
    }
}
