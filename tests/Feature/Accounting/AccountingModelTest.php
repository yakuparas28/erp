<?php

namespace Tests\Feature\Accounting;

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Models\PaymentAllocation;
use Modules\Accounting\Models\TaxRate;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Sales\Models\SalesOrderLine;
use Tests\TenantTestCase;

class AccountingModelTest extends TenantTestCase
{
    public function test_journal_entry_belongs_to_journal_has_lines_and_polymorphic_reference(): void
    {
        $journal = Journal::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'general']);
        $entry = JournalEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'journal_id' => $journal->id,
            'reference_type' => 'stock_move',
            'reference_id' => 1,
        ]);
        JournalEntryLine::factory()->create(['tenant_id' => $this->tenant->id, 'journal_entry_id' => $entry->id]);

        $this->assertTrue($entry->journal->is($journal));
        $this->assertCount(1, $entry->lines);
        $this->assertSame('stock_move', $entry->reference_type);
    }

    public function test_journal_entry_line_belongs_to_journal_entry_and_account(): void
    {
        $account = ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $entry = JournalEntry::factory()->create(['tenant_id' => $this->tenant->id]);
        $line = JournalEntryLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => '100.0000',
            'credit' => '0.0000',
        ]);

        $this->assertTrue($line->journalEntry->is($entry));
        $this->assertTrue($line->account->is($account));
    }

    public function test_tax_rate_belongs_to_tax_account(): void
    {
        $account = ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $taxRate = TaxRate::factory()->create(['tenant_id' => $this->tenant->id, 'tax_account_id' => $account->id]);

        $this->assertTrue($taxRate->taxAccount->is($account));
    }

    public function test_invoice_has_partner_lines_source_and_allocations(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        InvoiceLine::factory()->create(['tenant_id' => $this->tenant->id, 'invoice_id' => $invoice->id]);
        $payment = Payment::factory()->create(['tenant_id' => $this->tenant->id]);
        PaymentAllocation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
        ]);

        $this->assertTrue($invoice->partner->is($partner));
        $this->assertCount(1, $invoice->lines);
        $this->assertNotNull($invoice->source);
        $this->assertCount(1, $invoice->allocations);
    }

    public function test_invoice_line_belongs_to_invoice_product_and_tax_rate(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $taxRate = TaxRate::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
        $line = InvoiceLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'tax_rate_id' => $taxRate->id,
        ]);

        $this->assertTrue($line->invoice->is($invoice));
        $this->assertTrue($line->product->is($product));
        $this->assertTrue($line->taxRate->is($taxRate));
    }

    public function test_invoice_total_and_remaining_balance_with_two_lines_and_tax(): void
    {
        $taxRate = TaxRate::factory()->create(['tenant_id' => $this->tenant->id, 'percentage' => '20.00']);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);

        InvoiceLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'qty' => '2.0000',
            'unit_price' => '100.0000',
            'tax_rate_id' => $taxRate->id,
        ]);
        InvoiceLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'qty' => '1.0000',
            'unit_price' => '50.0000',
            'tax_rate_id' => null,
        ]);

        // subtotal: 2*100 + 1*50 = 250.0000; tax: 200 * 0.20 = 40.0000; total: 290.0000
        $this->assertSame('250.0000', $invoice->subtotal());
        $this->assertSame('40.0000', $invoice->taxTotal());
        $this->assertSame('290.0000', $invoice->total());

        $payment = Payment::factory()->create(['tenant_id' => $this->tenant->id, 'amount' => '100.0000']);
        PaymentAllocation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => '90.0000',
        ]);

        $this->assertSame('200.0000', $invoice->remainingBalance());
    }

    public function test_payment_has_partner_journal_and_allocations(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $journal = Journal::factory()->create(['tenant_id' => $this->tenant->id]);
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'partner_id' => $partner->id,
            'journal_id' => $journal->id,
        ]);
        PaymentAllocation::factory()->create(['tenant_id' => $this->tenant->id, 'payment_id' => $payment->id]);

        $this->assertTrue($payment->partner->is($partner));
        $this->assertTrue($payment->journal->is($journal));
        $this->assertCount(1, $payment->allocations);
    }

    public function test_payment_unallocated_amount(): void
    {
        $payment = Payment::factory()->create(['tenant_id' => $this->tenant->id, 'amount' => '100.0000']);
        PaymentAllocation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'allocated_amount' => '35.0000',
        ]);

        $this->assertSame('35.0000', $payment->allocatedTotal());
        $this->assertSame('65.0000', $payment->unallocatedAmount());
    }

    public function test_payment_allocation_belongs_to_payment_and_invoice(): void
    {
        $payment = Payment::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
        $allocation = PaymentAllocation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
        ]);

        $this->assertTrue($allocation->payment->is($payment));
        $this->assertTrue($allocation->invoice->is($invoice));
    }

    public function test_product_category_exposes_the_four_accounting_relations(): void
    {
        $stockInput = ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'asset']);
        $stockOutput = ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'asset']);
        $expense = ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'expense']);
        $income = ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'income']);

        $category = ProductCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_input_account_id' => $stockInput->id,
            'stock_output_account_id' => $stockOutput->id,
            'expense_account_id' => $expense->id,
            'income_account_id' => $income->id,
        ]);

        $this->assertTrue($category->stockInputAccount->is($stockInput));
        $this->assertTrue($category->stockOutputAccount->is($stockOutput));
        $this->assertTrue($category->expenseAccount->is($expense));
        $this->assertTrue($category->incomeAccount->is($income));
    }

    public function test_purchase_and_sales_order_lines_expose_tax_rate_relation(): void
    {
        $taxRate = TaxRate::factory()->create(['tenant_id' => $this->tenant->id]);

        $purchaseLine = PurchaseOrderLine::factory()->create(['tenant_id' => $this->tenant->id, 'tax_rate_id' => $taxRate->id]);
        $salesLine = SalesOrderLine::factory()->create(['tenant_id' => $this->tenant->id, 'tax_rate_id' => $taxRate->id]);

        $this->assertTrue($purchaseLine->taxRate->is($taxRate));
        $this->assertTrue($salesLine->taxRate->is($taxRate));
    }
}
