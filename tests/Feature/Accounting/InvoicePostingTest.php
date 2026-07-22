<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Purchase\Services\PurchaseOrderService;
use Modules\Sales\Models\SalesOrder;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class InvoicePostingTest extends TenantTestCase
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

    private function purchaseTaxRate(): TaxRate
    {
        return TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', 'purchase')->where('percentage', '20')->firstOrFail();
    }

    private function saleTaxRate(): TaxRate
    {
        return TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', 'sale')->where('percentage', '20')->firstOrFail();
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

    public function test_posting_a_purchase_invoice_records_only_the_tax_amount(): void
    {
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
            'qty' => '10',
        ]);
        $taxRate = $this->purchaseTaxRate();

        $invoice = $this->service()->create($this->tenant->id, $supplier->id, 'purchase', $po);
        $this->service()->addLine($invoice, $product->id, '10', '5.0000', $taxRate->id);

        $this->service()->post($invoice, $this->accountant());

        $this->assertSame('posted', $invoice->fresh()->status);

        $entry = JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $defaults = app(AccountingDefaultsService::class);
        $taxAccount = $defaults->accountByCode($this->tenant->id, '191');
        $payablesAccount = $defaults->accountByCode($this->tenant->id, '320');

        $this->assertSame(2, JournalEntryLine::withoutGlobalScopes()->where('journal_entry_id', $entry->id)->count());

        $debitLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $taxAccount->id)->firstOrFail();
        $creditLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $payablesAccount->id)->firstOrFail();

        // 10 * 5 = 50 subtotal, tax rate 20% => 10.0000 tax
        $this->assertSame('10.0000', $debitLine->debit);
        $this->assertSame('0.0000', $debitLine->credit);
        $this->assertSame('0.0000', $creditLine->debit);
        $this->assertSame('10.0000', $creditLine->credit);
    }

    public function test_posting_a_purchase_invoice_after_receipt_does_not_double_count_the_goods_value(): void
    {
        $defaults = app(AccountingDefaultsService::class);

        $category = ProductCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_input_account_id' => $defaults->accountByCode($this->tenant->id, '153')->id,
            'expense_account_id' => $defaults->accountByCode($this->tenant->id, '621')->id,
        ]);

        setPermissionsTeamId($this->tenant->id);
        $admin = User::factory()->for($this->tenant)->create();
        $admin->assignRole('Tenant Admin');
        $officer = User::factory()->for($this->tenant)->create();
        $officer->assignRole('Purchasing Officer');

        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $dock = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $unit->id,
            'cost_method' => 'fifo',
            'product_category_id' => $category->id,
        ]);

        $purchaseOrders = app(PurchaseOrderService::class);
        $po = $purchaseOrders->create($this->tenant->id, $supplier->id, $officer);
        $line = $purchaseOrders->addLine($po, $product->id, $unit->id, '10', '5.0000');
        $purchaseOrders->sendRfq($po);
        $purchaseOrders->confirm($po->fresh(), $admin);

        // Fiili teslim alım: net mal değeri (10 * 5.0000 = 50.0000) burada,
        // postForPurchaseReceipt() tarafından, 320'ye ZATEN alacak yazılır.
        $purchaseOrders->receive($line->fresh(), '10', $dock->id);

        $taxRate = $this->purchaseTaxRate();

        $invoice = $this->service()->create($this->tenant->id, $supplier->id, 'purchase', $po->fresh());
        $this->service()->addLine($invoice, $product->id, '10', '5.0000', $taxRate->id);

        $this->service()->post($invoice, $this->accountant());

        $this->assertSame('posted', $invoice->fresh()->status);

        $payablesAccount = $defaults->accountByCode($this->tenant->id, '320');

        $totalDebit = JournalEntryLine::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('account_id', $payablesAccount->id)
            ->sum('debit');
        $totalCredit = JournalEntryLine::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('account_id', $payablesAccount->id)
            ->sum('credit');

        $netPayables = bcsub((string) $totalCredit, (string) $totalDebit, 4);

        // 10 * 5.0000 = 50.0000 mal değeri (receive'de kaydedildi) + 10.0000
        // KDV (invoice post'ta kaydedildi) = 60.0000. Eğer invoice post
        // sırasında mal değeri TEKRAR kaydedilseydi bu 100.0000 olurdu.
        $this->assertSame('60.0000', $netPayables);
    }

    public function test_posting_a_purchase_invoice_with_no_tax_fails(): void
    {
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
            'qty' => '10',
        ]);

        $invoice = $this->service()->create($this->tenant->id, $supplier->id, 'purchase', $po);
        $this->service()->addLine($invoice, $product->id, '10', '5.0000', null);

        try {
            $this->service()->post($invoice, $this->accountant());
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_posting_a_sale_invoice_with_a_single_line_recognizes_revenue(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();
        $taxRate = $this->saleTaxRate();

        $invoice = $this->service()->create($this->tenant->id, $partner->id, 'sale', $salesOrder);
        $this->service()->addLine($invoice, $product->id, '10', '5.0000', $taxRate->id);

        $this->service()->post($invoice, $this->accountant());

        $this->assertSame('posted', $invoice->fresh()->status);

        $entry = JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $defaults = app(AccountingDefaultsService::class);
        $receivables = $defaults->accountByCode($this->tenant->id, '120');
        $incomeAccount = $defaults->accountByCode($this->tenant->id, '600');
        $outputTax = $defaults->accountByCode($this->tenant->id, '391');

        $this->assertSame(3, JournalEntryLine::withoutGlobalScopes()->where('journal_entry_id', $entry->id)->count());

        $debitLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $receivables->id)->firstOrFail();
        $incomeLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $incomeAccount->id)->firstOrFail();
        $taxLine = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $outputTax->id)->firstOrFail();

        // 10 * 5 = 50 subtotal, tax rate 20% => 10.0000 tax, total 60.0000
        $this->assertSame('60.0000', $debitLine->debit);
        $this->assertSame('0.0000', $debitLine->credit);
        $this->assertSame('50.0000', $incomeLine->credit);
        $this->assertSame('10.0000', $taxLine->credit);
    }

    public function test_posting_a_sale_invoice_with_two_different_categories_produces_a_balanced_entry(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $taxRate = $this->saleTaxRate();
        $defaults = app(AccountingDefaultsService::class);

        $categoryA = ProductCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'income_account_id' => $defaults->accountByCode($this->tenant->id, '600')->id,
        ]);
        $categoryB = ProductCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'income_account_id' => $defaults->accountByCode($this->tenant->id, '646')->id,
        ]);
        $productA = Product::factory()->create(['tenant_id' => $this->tenant->id, 'product_category_id' => $categoryA->id]);
        $productB = Product::factory()->create(['tenant_id' => $this->tenant->id, 'product_category_id' => $categoryB->id]);

        $invoice = $this->service()->create($this->tenant->id, $partner->id, 'sale', $salesOrder);
        $this->service()->addLine($invoice, $productA->id, '10', '5.0000', $taxRate->id);
        $this->service()->addLine($invoice, $productB->id, '2', '3.0000', $taxRate->id);

        $this->service()->post($invoice, $this->accountant());

        $entry = JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame(4, JournalEntryLine::withoutGlobalScopes()->where('journal_entry_id', $entry->id)->count());

        $totalDebit = JournalEntryLine::withoutGlobalScopes()->where('journal_entry_id', $entry->id)->sum('debit');
        $totalCredit = JournalEntryLine::withoutGlobalScopes()->where('journal_entry_id', $entry->id)->sum('credit');
        $this->assertSame(bcadd((string) $totalDebit, '0', 4), bcadd((string) $totalCredit, '0', 4));

        $incomeA = $defaults->accountByCode($this->tenant->id, '600');
        $incomeB = $defaults->accountByCode($this->tenant->id, '646');

        $lineA = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $incomeA->id)->firstOrFail();
        $lineB = JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->where('account_id', $incomeB->id)->firstOrFail();

        $this->assertSame('50.0000', $lineA->credit);
        $this->assertSame('6.0000', $lineB->credit);
    }

    public function test_posting_a_non_draft_invoice_fails(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();

        $invoice = $this->service()->create($this->tenant->id, $partner->id, 'sale', $salesOrder);
        $this->service()->addLine($invoice, $product->id, '10', '5.0000', null);
        $invoice->update(['status' => 'posted']);

        try {
            $this->service()->post($invoice, $this->accountant());
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_a_user_without_permission_cannot_post_an_invoice(): void
    {
        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();

        $invoice = $this->service()->create($this->tenant->id, $partner->id, 'sale', $salesOrder);
        $this->service()->addLine($invoice, $product->id, '10', '5.0000', null);

        setPermissionsTeamId($this->tenant->id);
        $unprivileged = User::factory()->for($this->tenant)->create();
        $unprivileged->assignRole('Sales Representative');

        try {
            $this->service()->post($invoice, $unprivileged);
            $this->fail('403 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame('draft', $invoice->fresh()->status);
    }
}
